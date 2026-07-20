<?php

namespace Tests\Feature;

use App\Exceptions\AvtodorCsvException;
use App\Models\Expense;
use App\Models\Trip;
use App\Services\AvtodorCsvImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AvtodorImportTest extends TestCase
{
    use RefreshDatabase;

    private function makeTrip(): Trip
    {
        $this->post('/trips', [
            'name' => 'Анапа',
            'travelers' => ['Анна', 'Борис'],
        ]);

        return Trip::query()->firstOrFail();
    }

    public function test_importer_creates_toll_expenses_skips_zero(): void
    {
        $trip = $this->makeTrip();
        $anna = $trip->travelers()->where('name', 'Анна')->firstOrFail();
        $csv = file_get_contents(base_path('tests/fixtures/avtodor-sample.csv'));

        $result = app(AvtodorCsvImporter::class)->import($trip, $anna->id, $csv);

        $this->assertSame(2, $result['created']);
        $this->assertSame(1, $result['skipped_zero']);
        $this->assertSame([], $result['duplicates']);
        $this->assertDatabaseCount('expenses', 2);
        $this->assertDatabaseHas('expenses', [
            'trip_id' => $trip->id,
            'traveler_id' => $anna->id,
            'type' => Expense::TYPE_TOLL,
            'amount' => 340.00,
            'comment' => 'М4-71М-Воронеж; тариф 400,00 ₽; скидка 15%',
            'has_time' => 1,
        ]);
    }

    public function test_importer_skips_duplicates_by_spent_at_and_amount(): void
    {
        $trip = $this->makeTrip();
        $anna = $trip->travelers()->where('name', 'Анна')->firstOrFail();

        $trip->expenses()->create([
            'traveler_id' => $anna->id,
            'amount' => 340.00,
            'type' => Expense::TYPE_TOLL,
            'comment' => 'already there',
            'spent_at' => '2026-07-18 13:45:59',
            'has_time' => true,
        ]);

        $csv = file_get_contents(base_path('tests/fixtures/avtodor-sample.csv'));
        $result = app(AvtodorCsvImporter::class)->import($trip, $anna->id, $csv);

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['skipped_zero']);
        $this->assertCount(1, $result['duplicates']);
        $this->assertSame(340.0, $result['duplicates'][0]['amount']);
        $this->assertDatabaseCount('expenses', 2);
    }

    public function test_http_import_avtodor_csv(): void
    {
        $trip = $this->makeTrip();
        $anna = $trip->travelers()->where('name', 'Анна')->firstOrFail();
        $file = UploadedFile::fake()->createWithContent(
            'contract-transactions.csv',
            file_get_contents(base_path('tests/fixtures/avtodor-sample.csv')),
        );

        $this->post(route('expenses.import-avtodor', $trip), [
            'traveler_id' => $anna->id,
            'file' => $file,
        ])
            ->assertRedirect(route('trips.show', $trip))
            ->assertSessionHas('avtodor_import.created', 2)
            ->assertSessionHas('avtodor_import.skipped_zero', 1);

        $this->assertDatabaseCount('expenses', 2);
    }

    public function test_http_rejects_non_csv_extension(): void
    {
        $trip = $this->makeTrip();
        $anna = $trip->travelers()->where('name', 'Анна')->firstOrFail();
        $file = UploadedFile::fake()->create('notes.txt', 100, 'text/plain');

        $this->post(route('expenses.import-avtodor', $trip), [
            'traveler_id' => $anna->id,
            'file' => $file,
        ])->assertSessionHasErrors('file');
    }

    public function test_http_import_reports_duplicates_in_session(): void
    {
        $trip = $this->makeTrip();
        $anna = $trip->travelers()->where('name', 'Анна')->firstOrFail();

        $trip->expenses()->create([
            'traveler_id' => $anna->id,
            'amount' => 340.00,
            'type' => Expense::TYPE_TOLL,
            'comment' => 'dup',
            'spent_at' => '2026-07-18 13:45:59',
            'has_time' => true,
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'contract-transactions.csv',
            file_get_contents(base_path('tests/fixtures/avtodor-sample.csv')),
        );

        $this->post(route('expenses.import-avtodor', $trip), [
            'traveler_id' => $anna->id,
            'file' => $file,
        ])
            ->assertRedirect(route('trips.show', $trip))
            ->assertSessionHas('avtodor_import.created', 1)
            ->assertSessionHas('avtodor_import.duplicates');
    }

    public function test_importer_rejects_unrelated_csv(): void
    {
        $trip = $this->makeTrip();
        $anna = $trip->travelers()->where('name', 'Анна')->firstOrFail();

        $this->expectException(AvtodorCsvException::class);
        $this->expectExceptionMessage('Файл не похож на экспорт Автодора.');

        app(AvtodorCsvImporter::class)->import($trip, $anna->id, "foo,bar\n1,2");
    }

    public function test_http_rejects_unrelated_csv(): void
    {
        $trip = $this->makeTrip();
        $anna = $trip->travelers()->where('name', 'Анна')->firstOrFail();
        $file = UploadedFile::fake()->createWithContent('contract-transactions.csv', "foo,bar\n1,2");

        $this->post(route('expenses.import-avtodor', $trip), [
            'traveler_id' => $anna->id,
            'file' => $file,
        ])->assertSessionHasErrors('file');
    }

    public function test_importer_accepts_header_only_avtodor_csv(): void
    {
        $trip = $this->makeTrip();
        $anna = $trip->travelers()->where('name', 'Анна')->firstOrFail();
        $header = "Дата;ПВП\\РВП выезда;Полоса;Электронное средство;Класс;Сумма тарифа, ₽;Скидка, %;Оплачено, ₽";

        $result = app(AvtodorCsvImporter::class)->import($trip, $anna->id, $header);

        $this->assertSame(0, $result['created']);
        $this->assertSame(0, $result['skipped_zero']);
        $this->assertSame([], $result['duplicates']);
        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_importer_rejects_malformed_date_row(): void
    {
        $trip = $this->makeTrip();
        $anna = $trip->travelers()->where('name', 'Анна')->firstOrFail();
        $csv = "Дата;ПВП\\РВП выезда;Полоса;Электронное средство;Класс;Сумма тарифа, ₽;Скидка, %;Оплачено, ₽\n"
            ."32.01.2026 13:45:59;М4;5;123;1;400,00;15;340,00";

        $this->expectException(AvtodorCsvException::class);
        $this->expectExceptionMessage('Некорректная дата');

        app(AvtodorCsvImporter::class)->import($trip, $anna->id, $csv);
    }

    public function test_http_rejects_malformed_date_row(): void
    {
        $trip = $this->makeTrip();
        $anna = $trip->travelers()->where('name', 'Анна')->firstOrFail();
        $csv = "Дата;ПВП\\РВП выезда;Полоса;Электронное средство;Класс;Сумма тарифа, ₽;Скидка, %;Оплачено, ₽\n"
            ."32.01.2026 13:45:59;М4;5;123;1;400,00;15;340,00";
        $file = UploadedFile::fake()->createWithContent('contract-transactions.csv', $csv);

        $this->post(route('expenses.import-avtodor', $trip), [
            'traveler_id' => $anna->id,
            'file' => $file,
        ])->assertSessionHasErrors('file');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Meal;
use App\Models\Trip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MealFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_meal_without_items_and_with_photo(): void
    {
        Storage::fake('public');

        $trip = $this->createTrip();
        $anna = $trip->travelers()->where('name', 'Анна')->firstOrFail();

        $this->post(route('meals.store', $trip), [
            'title' => 'Кафе',
            'amount' => 1000,
            'traveler_id' => $anna->id,
            'spent_date' => '2026-07-20',
            'spent_time' => '13:00',
            'items' => [],
            'photos' => [UploadedFile::fake()->image('receipt.jpg')],
        ])->assertRedirect(route('trips.show', ['trip' => $trip, 'tab' => 'meals']));

        $meal = Meal::query()->first();
        $this->assertNotNull($meal);
        $this->assertSame('Кафе', $meal->title);
        $this->assertCount(0, $meal->items);
        $this->assertCount(1, $meal->photos);
        Storage::disk('public')->assertExists($meal->photos->first()->path);
    }

    public function test_can_update_meal_with_items_matching_total(): void
    {
        $trip = $this->createTrip();
        $anna = $trip->travelers()->where('name', 'Анна')->firstOrFail();
        $boris = $trip->travelers()->where('name', 'Борис')->firstOrFail();

        $this->post(route('meals.store', $trip), [
            'title' => 'Кафе',
            'amount' => 1000,
            'traveler_id' => $anna->id,
            'spent_date' => '2026-07-20',
            'spent_time' => null,
            'items' => [],
        ])->assertRedirect(route('trips.show', ['trip' => $trip, 'tab' => 'meals']));

        $meal = Meal::query()->firstOrFail();

        $this->put(route('meals.update', [$trip, $meal]), [
            'title' => 'Кафе',
            'amount' => 1000,
            'traveler_id' => $anna->id,
            'spent_date' => '2026-07-20',
            'spent_time' => null,
            'items' => [
                ['name' => 'Суп', 'amount' => 300, 'traveler_id' => $anna->id],
                ['name' => 'Суп', 'amount' => 400, 'traveler_id' => $boris->id],
                ['name' => 'Компот', 'amount' => 150, 'traveler_id' => $anna->id],
                ['name' => 'Сок', 'amount' => 150, 'traveler_id' => $boris->id],
            ],
        ])->assertRedirect(route('trips.show', ['trip' => $trip, 'tab' => 'meals']));

        $meal->refresh();
        $this->assertCount(4, $meal->items);

        $this->get(route('trips.show', $trip))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Trips/Show')
                ->where('mealSettlement.total', 1000)
                ->where('mealSettlement.incomplete_count', 0)
                ->where('mealSettlement.settlements.0.from', 'Борис')
                ->where('mealSettlement.settlements.0.to', 'Анна')
                ->where('mealSettlement.settlements.0.amount', 550)
            );
    }

    public function test_rejects_items_that_do_not_sum_to_amount(): void
    {
        $trip = $this->createTrip();
        $anna = $trip->travelers()->where('name', 'Анна')->firstOrFail();
        $boris = $trip->travelers()->where('name', 'Борис')->firstOrFail();

        $this->post(route('meals.store', $trip), [
            'title' => 'Кафе',
            'amount' => 1000,
            'traveler_id' => $anna->id,
            'spent_date' => '2026-07-20',
            'spent_time' => null,
            'items' => [
                ['name' => 'Суп', 'amount' => 300, 'traveler_id' => $anna->id],
                ['name' => 'Суп', 'amount' => 400, 'traveler_id' => $boris->id],
            ],
        ])->assertSessionHasErrors('items');

        $this->assertSame(0, Meal::query()->count());
    }

    public function test_can_delete_meal(): void
    {
        Storage::fake('public');

        $trip = $this->createTrip();
        $anna = $trip->travelers()->where('name', 'Анна')->firstOrFail();

        $this->post(route('meals.store', $trip), [
            'title' => 'Кафе',
            'amount' => 500,
            'traveler_id' => $anna->id,
            'spent_date' => '2026-07-20',
            'spent_time' => null,
            'items' => [],
            'photos' => [UploadedFile::fake()->image('receipt.jpg')],
        ]);

        $meal = Meal::query()->firstOrFail();
        $path = $meal->photos->first()->path;

        $this->delete(route('meals.destroy', [$trip, $meal]))
            ->assertRedirect(route('trips.show', ['trip' => $trip, 'tab' => 'meals']));

        $this->assertDatabaseMissing('meals', ['id' => $meal->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_cannot_remove_traveler_with_meals(): void
    {
        $trip = $this->createTrip(['Анна', 'Борис', 'Вика']);
        $anna = $trip->travelers()->where('name', 'Анна')->firstOrFail();
        $boris = $trip->travelers()->where('name', 'Борис')->firstOrFail();
        $vika = $trip->travelers()->where('name', 'Вика')->firstOrFail();

        $this->post(route('meals.store', $trip), [
            'title' => 'Кафе',
            'amount' => 500,
            'traveler_id' => $anna->id,
            'spent_date' => '2026-07-20',
            'spent_time' => null,
            'items' => [],
        ]);

        $this->put(route('trips.update', $trip), [
            'name' => 'Крым',
            'travelers' => [
                ['id' => $boris->id, 'name' => 'Борис'],
                ['id' => $vika->id, 'name' => 'Вика'],
            ],
        ])->assertSessionHasErrors('travelers');

        $this->assertDatabaseHas('travelers', ['id' => $anna->id]);
    }

    /**
     * @param  list<string>  $names
     */
    private function createTrip(array $names = ['Анна', 'Борис']): Trip
    {
        $this->post('/trips', [
            'name' => 'Крым',
            'travelers' => $names,
        ]);

        return Trip::query()->firstOrFail();
    }
}

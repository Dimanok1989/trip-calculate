<?php

namespace Tests\Feature;

use App\Models\Trip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Home'));
    }

    public function test_can_create_trip_and_add_expense(): void
    {
        $response = $this->post('/trips', [
            'name' => 'Крым',
            'travelers' => ['Анна', 'Борис'],
        ]);

        $trip = Trip::query()->first();
        $this->assertNotNull($trip);
        $response->assertRedirect(route('trips.show', $trip));

        $anna = $trip->travelers()->where('name', 'Анна')->first();

        $this->post(route('expenses.store', $trip), [
            'amount' => 1000,
            'type' => 'fuel',
            'traveler_id' => $anna->id,
            'comment' => 'Заправка',
            'spent_date' => '2026-07-20',
            'spent_time' => '14:30',
        ])->assertRedirect(route('trips.show', $trip));

        $this->assertDatabaseHas('expenses', [
            'trip_id' => $trip->id,
            'traveler_id' => $anna->id,
            'type' => 'fuel',
            'comment' => 'Заправка',
            'has_time' => 1,
        ]);

        $expense = $trip->expenses()->first();

        $this->put(route('expenses.update', [$trip, $expense]), [
            'amount' => 1200,
            'type' => 'toll',
            'traveler_id' => $anna->id,
            'comment' => 'Платный участок',
            'spent_date' => '2026-07-21',
            'spent_time' => '',
        ])->assertRedirect(route('trips.show', $trip));

        $expense->refresh();
        $this->assertSame('1200.00', $expense->amount);
        $this->assertSame('toll', $expense->type);
        $this->assertFalse($expense->has_time);
        $this->assertSame('21.07.2026', $expense->spentLabel());

        $this->get(route('trips.show', $trip))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Trips/Show')
                ->where('settlement.total', 1200)
                ->where('settlement.share', 600)
                ->where('expenses.0.spent_label', '21.07.2026')
            );
    }

    public function test_trip_requires_at_least_two_travelers(): void
    {
        $this->post('/trips', [
            'name' => 'Крым',
            'travelers' => ['Анна'],
        ])->assertSessionHasErrors('travelers');
    }

    public function test_can_update_trip_name_and_travelers(): void
    {
        $this->post('/trips', [
            'name' => 'Крым',
            'travelers' => ['Анна', 'Борис'],
        ]);

        $trip = Trip::query()->firstOrFail();
        $anna = $trip->travelers()->where('name', 'Анна')->firstOrFail();
        $boris = $trip->travelers()->where('name', 'Борис')->firstOrFail();

        $this->put(route('trips.update', $trip), [
            'name' => 'Сочи',
            'travelers' => [
                ['id' => $anna->id, 'name' => 'Анна П.'],
                ['id' => $boris->id, 'name' => 'Борис'],
                ['id' => null, 'name' => 'Вика'],
            ],
        ])->assertRedirect(route('trips.show', $trip));

        $trip->refresh();
        $this->assertSame('Сочи', $trip->name);
        $this->assertCount(3, $trip->travelers);
        $this->assertDatabaseHas('travelers', [
            'id' => $anna->id,
            'name' => 'Анна П.',
        ]);
        $this->assertDatabaseHas('travelers', [
            'trip_id' => $trip->id,
            'name' => 'Вика',
        ]);
    }

    public function test_cannot_remove_traveler_with_expenses(): void
    {
        $this->post('/trips', [
            'name' => 'Крым',
            'travelers' => ['Анна', 'Борис', 'Вика'],
        ]);

        $trip = Trip::query()->firstOrFail();
        $anna = $trip->travelers()->where('name', 'Анна')->firstOrFail();
        $boris = $trip->travelers()->where('name', 'Борис')->firstOrFail();
        $vika = $trip->travelers()->where('name', 'Вика')->firstOrFail();

        $this->post(route('expenses.store', $trip), [
            'amount' => 500,
            'type' => 'fuel',
            'traveler_id' => $anna->id,
            'spent_date' => '2026-07-20',
            'spent_time' => null,
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
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTripRequest;
use App\Http\Requests\UpdateTripRequest;
use App\Models\Expense;
use App\Models\Meal;
use App\Models\Trip;
use App\Services\MealSettlement;
use App\Services\TripSettlement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TripController extends Controller
{
    public function index(): Response
    {
        $trips = Trip::query()
            ->withCount('travelers')
            ->latest()
            ->get(['id', 'name', 'created_at']);

        return Inertia::render('Home', [
            'trips' => $trips,
        ]);
    }

    public function store(StoreTripRequest $request): RedirectResponse
    {
        $trip = DB::transaction(function () use ($request) {
            $trip = Trip::query()->create([
                'name' => $request->validated('name'),
            ]);

            foreach ($request->validated('travelers') as $name) {
                $trip->travelers()->create([
                    'name' => trim($name),
                ]);
            }

            return $trip;
        });

        return redirect()->route('trips.show', $trip);
    }

    public function update(UpdateTripRequest $request, Trip $trip): RedirectResponse
    {
        DB::transaction(function () use ($request, $trip): void {
            $trip->update([
                'name' => $request->validated('name'),
            ]);

            $payload = collect($request->validated('travelers'));
            $keptIds = [];

            foreach ($payload as $row) {
                $name = trim($row['name']);

                if (! empty($row['id'])) {
                    $traveler = $trip->travelers()->whereKey($row['id'])->firstOrFail();
                    $traveler->update(['name' => $name]);
                    $keptIds[] = $traveler->id;

                    continue;
                }

                $traveler = $trip->travelers()->create(['name' => $name]);
                $keptIds[] = $traveler->id;
            }

            $trip->travelers()
                ->whereNotIn('id', $keptIds)
                ->whereDoesntHave('expenses')
                ->whereDoesntHave('meals')
                ->whereDoesntHave('mealItems')
                ->delete();
        });

        return redirect()->route('trips.show', $trip);
    }

    public function show(Trip $trip, TripSettlement $settlement, MealSettlement $mealSettlement): Response
    {
        $trip->load([
            'travelers' => fn ($query) => $query
                ->withCount('expenses')
                ->withCount('meals')
                ->withCount('mealItems'),
            'expenses' => fn ($query) => $query->with('traveler')->orderByDesc('spent_at')->orderByDesc('id'),
            'meals' => fn ($query) => $query
                ->with(['traveler', 'items.traveler', 'photos'])
                ->orderByDesc('spent_at')
                ->orderByDesc('id'),
        ]);

        $expenses = $trip->expenses->map(fn (Expense $expense) => [
            'id' => $expense->id,
            'amount' => (float) $expense->amount,
            'type' => $expense->type,
            'type_custom' => $expense->type_custom,
            'type_label' => $expense->typeLabel(),
            'comment' => $expense->comment,
            'payer' => $expense->traveler?->name,
            'traveler_id' => $expense->traveler_id,
            'spent_at' => $expense->spent_at?->toIso8601String(),
            'spent_date' => $expense->spent_at?->format('Y-m-d'),
            'spent_time' => $expense->has_time ? $expense->spent_at?->format('H:i') : '',
            'has_time' => (bool) $expense->has_time,
            'spent_label' => $expense->spentLabel(),
        ]);

        $meals = $trip->meals->map(fn (Meal $meal) => [
            'id' => $meal->id,
            'title' => $meal->title,
            'amount' => (float) $meal->amount,
            'payer' => $meal->traveler?->name,
            'traveler_id' => $meal->traveler_id,
            'spent_at' => $meal->spent_at?->toIso8601String(),
            'spent_date' => $meal->spent_at?->format('Y-m-d'),
            'spent_time' => $meal->has_time ? $meal->spent_at?->format('H:i') : '',
            'has_time' => (bool) $meal->has_time,
            'spent_label' => $meal->spentLabel(),
            'items_complete' => $meal->isFullyAllocated(),
            'items' => $meal->items->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'amount' => (float) $item->amount,
                'traveler_id' => $item->traveler_id,
                'traveler_name' => $item->traveler?->name,
            ])->values()->all(),
            'photos' => $meal->photos->map(fn ($photo) => [
                'id' => $photo->id,
                'url' => $photo->url(),
            ])->values()->all(),
        ]);

        return Inertia::render('Trips/Show', [
            'trip' => [
                'id' => $trip->id,
                'name' => $trip->name,
            ],
            'travelers' => $trip->travelers->map(fn ($traveler) => [
                'id' => $traveler->id,
                'name' => $traveler->name,
                'expenses_count' => $traveler->expenses_count,
                'meals_count' => $traveler->meals_count + $traveler->meal_items_count,
            ]),
            'expenses' => $expenses,
            'meals' => $meals,
            'expenseTypes' => Expense::TYPES,
            'settlement' => $settlement->calculate($trip->travelers, $trip->expenses),
            'mealSettlement' => $mealSettlement->calculate($trip->travelers, $trip->meals),
        ]);
    }
}

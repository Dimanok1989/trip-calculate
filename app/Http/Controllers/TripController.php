<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTripRequest;
use App\Http\Requests\UpdateTripRequest;
use App\Models\Expense;
use App\Models\Trip;
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
                ->delete();
        });

        return redirect()->route('trips.show', $trip);
    }

    public function show(Trip $trip, TripSettlement $settlement): Response
    {
        $trip->load([
            'travelers' => fn ($query) => $query->withCount('expenses'),
            'expenses' => fn ($query) => $query->with('traveler')->orderByDesc('spent_at')->orderByDesc('id'),
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

        return Inertia::render('Trips/Show', [
            'trip' => [
                'id' => $trip->id,
                'name' => $trip->name,
            ],
            'travelers' => $trip->travelers->map(fn ($traveler) => [
                'id' => $traveler->id,
                'name' => $traveler->name,
                'expenses_count' => $traveler->expenses_count,
            ]),
            'expenses' => $expenses,
            'expenseTypes' => Expense::TYPES,
            'settlement' => $settlement->calculate($trip->travelers, $trip->expenses),
        ]);
    }
}

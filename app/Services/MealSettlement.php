<?php

namespace App\Services;

use App\Models\Meal;
use App\Models\MealItem;
use App\Models\Traveler;
use Illuminate\Support\Collection;

class MealSettlement
{
    public function __construct(
        private SettlementNetting $netting = new SettlementNetting,
    ) {}

    /**
     * @param  Collection<int, Traveler>  $travelers
     * @param  Collection<int, Meal>  $meals
     * @return array{
     *     total: float,
     *     incomplete_count: int,
     *     balances: list<array{traveler_id: int, name: string, paid: float, consumed: float, balance: float}>,
     *     settlements: list<array{from: string, to: string, amount: float}>
     * }
     */
    public function calculate(Collection $travelers, Collection $meals): array
    {
        $complete = $meals->filter(fn (Meal $meal) => $meal->isFullyAllocated())->values();
        $incompleteCount = $meals->count() - $complete->count();

        $total = round((float) $complete->sum(fn (Meal $meal) => (float) $meal->amount), 2);

        $paidByTraveler = $complete
            ->groupBy('traveler_id')
            ->map(fn (Collection $group) => round((float) $group->sum(fn (Meal $meal) => (float) $meal->amount), 2));

        $consumedByTraveler = $complete
            ->flatMap(fn (Meal $meal) => $meal->items)
            ->groupBy('traveler_id')
            ->map(fn (Collection $group) => round((float) $group->sum(fn (MealItem $item) => (float) $item->amount), 2));

        $balances = $travelers->map(function (Traveler $traveler) use ($paidByTraveler, $consumedByTraveler) {
            $paid = (float) ($paidByTraveler[$traveler->id] ?? 0);
            $consumed = (float) ($consumedByTraveler[$traveler->id] ?? 0);
            $balance = round($paid - $consumed, 2);

            return [
                'traveler_id' => $traveler->id,
                'name' => $traveler->name,
                'paid' => $paid,
                'consumed' => $consumed,
                'balance' => $balance,
            ];
        })->values()->all();

        return [
            'total' => $total,
            'incomplete_count' => $incompleteCount,
            'balances' => $balances,
            'settlements' => $this->netting->buildSettlements($balances),
        ];
    }
}

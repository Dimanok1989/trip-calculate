<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Traveler;
use Illuminate\Support\Collection;

class TripSettlement
{
    public function __construct(
        private SettlementNetting $netting = new SettlementNetting,
    ) {}

    /**
     * @param  Collection<int, Traveler>  $travelers
     * @param  Collection<int, Expense>  $expenses
     * @return array{
     *     total: float,
     *     share: float,
     *     balances: list<array{traveler_id: int, name: string, paid: float, balance: float}>,
     *     settlements: list<array{from: string, to: string, amount: float}>
     * }
     */
    public function calculate(Collection $travelers, Collection $expenses): array
    {
        $count = $travelers->count();
        $total = round((float) $expenses->sum(fn (Expense $expense) => (float) $expense->amount), 2);
        $share = $count > 0 ? round($total / $count, 2) : 0.0;

        $paidByTraveler = $expenses
            ->groupBy('traveler_id')
            ->map(fn (Collection $group) => round((float) $group->sum(fn (Expense $expense) => (float) $expense->amount), 2));

        $balances = $travelers->map(function (Traveler $traveler) use ($paidByTraveler, $share) {
            $paid = (float) ($paidByTraveler[$traveler->id] ?? 0);
            $balance = round($paid - $share, 2);

            return [
                'traveler_id' => $traveler->id,
                'name' => $traveler->name,
                'paid' => $paid,
                'balance' => $balance,
            ];
        })->values()->all();

        return [
            'total' => $total,
            'share' => $share,
            'balances' => $balances,
            'settlements' => $this->netting->buildSettlements($balances),
        ];
    }
}

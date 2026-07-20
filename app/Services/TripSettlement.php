<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Traveler;
use Illuminate\Support\Collection;

class TripSettlement
{
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
            'settlements' => $this->buildSettlements($balances),
        ];
    }

    /**
     * @param  list<array{traveler_id: int, name: string, paid: float, balance: float}>  $balances
     * @return list<array{from: string, to: string, amount: float}>
     */
    private function buildSettlements(array $balances): array
    {
        $debtors = [];
        $creditors = [];

        foreach ($balances as $row) {
            if ($row['balance'] < -0.009) {
                $debtors[] = [
                    'name' => $row['name'],
                    'amount' => abs($row['balance']),
                ];
            } elseif ($row['balance'] > 0.009) {
                $creditors[] = [
                    'name' => $row['name'],
                    'amount' => $row['balance'],
                ];
            }
        }

        usort($debtors, fn ($a, $b) => $b['amount'] <=> $a['amount']);
        usort($creditors, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        $settlements = [];
        $i = 0;
        $j = 0;

        while ($i < count($debtors) && $j < count($creditors)) {
            $amount = round(min($debtors[$i]['amount'], $creditors[$j]['amount']), 2);

            if ($amount > 0) {
                $settlements[] = [
                    'from' => $debtors[$i]['name'],
                    'to' => $creditors[$j]['name'],
                    'amount' => $amount,
                ];
            }

            $debtors[$i]['amount'] = round($debtors[$i]['amount'] - $amount, 2);
            $creditors[$j]['amount'] = round($creditors[$j]['amount'] - $amount, 2);

            if ($debtors[$i]['amount'] <= 0.009) {
                $i++;
            }

            if ($creditors[$j]['amount'] <= 0.009) {
                $j++;
            }
        }

        return $settlements;
    }
}

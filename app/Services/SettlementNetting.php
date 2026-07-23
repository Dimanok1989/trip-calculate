<?php

namespace App\Services;

class SettlementNetting
{
    /**
     * @param  list<array{traveler_id: int, name: string, paid?: float, consumed?: float, balance: float}>  $balances
     * @return list<array{from: string, to: string, amount: float}>
     */
    public function buildSettlements(array $balances): array
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

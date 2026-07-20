<?php

namespace Tests\Unit;

use App\Models\Expense;
use App\Models\Traveler;
use App\Services\TripSettlement;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class TripSettlementTest extends TestCase
{
    public function test_it_calculates_balances_and_settlements(): void
    {
        $travelers = new Collection([
            $this->makeTraveler(1, 'Анна'),
            $this->makeTraveler(2, 'Борис'),
            $this->makeTraveler(3, 'Вика'),
        ]);

        $expenses = new Collection([
            $this->makeExpense(1, 300),
        ]);

        $result = (new TripSettlement)->calculate($travelers, $expenses);

        $this->assertSame(300.0, $result['total']);
        $this->assertSame(100.0, $result['share']);
        $this->assertSame(200.0, $result['balances'][0]['balance']);
        $this->assertSame(-100.0, $result['balances'][1]['balance']);
        $this->assertSame(-100.0, $result['balances'][2]['balance']);

        $this->assertCount(2, $result['settlements']);
        $this->assertSame('Борис', $result['settlements'][0]['from']);
        $this->assertSame('Анна', $result['settlements'][0]['to']);
        $this->assertSame(100.0, $result['settlements'][0]['amount']);
        $this->assertSame('Вика', $result['settlements'][1]['from']);
        $this->assertSame('Анна', $result['settlements'][1]['to']);
        $this->assertSame(100.0, $result['settlements'][1]['amount']);
    }

    private function makeTraveler(int $id, string $name): Traveler
    {
        $traveler = new Traveler;
        $traveler->id = $id;
        $traveler->name = $name;

        return $traveler;
    }

    private function makeExpense(int $travelerId, float $amount): Expense
    {
        $expense = new Expense;
        $expense->traveler_id = $travelerId;
        $expense->amount = $amount;

        return $expense;
    }
}

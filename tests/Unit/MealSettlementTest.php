<?php

namespace Tests\Unit;

use App\Models\Meal;
use App\Models\MealItem;
use App\Models\Traveler;
use App\Services\MealSettlement;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class MealSettlementTest extends TestCase
{
    public function test_it_nets_debts_across_meals(): void
    {
        $travelers = new Collection([
            $this->makeTraveler(1, 'Анна'),
            $this->makeTraveler(2, 'Борис'),
        ]);

        // Cafe 1000 paid by Anna; Boris consumed 550
        $meal1 = $this->makeMeal(1, 1000, [
            $this->makeItem(1, 450),
            $this->makeItem(2, 550),
        ]);

        // Cafe 400 paid by Boris; Anna consumed 400
        $meal2 = $this->makeMeal(2, 400, [
            $this->makeItem(1, 400),
        ]);

        $result = (new MealSettlement)->calculate($travelers, new Collection([$meal1, $meal2]));

        $this->assertSame(1400.0, $result['total']);
        $this->assertSame(0, $result['incomplete_count']);

        // Anna: paid 1000, consumed 850 → +150
        // Boris: paid 400, consumed 550 → -150
        $this->assertSame(150.0, $result['balances'][0]['balance']);
        $this->assertSame(-150.0, $result['balances'][1]['balance']);

        $this->assertCount(1, $result['settlements']);
        $this->assertSame('Борис', $result['settlements'][0]['from']);
        $this->assertSame('Анна', $result['settlements'][0]['to']);
        $this->assertSame(150.0, $result['settlements'][0]['amount']);
    }

    public function test_it_ignores_meals_without_items(): void
    {
        $travelers = new Collection([
            $this->makeTraveler(1, 'Анна'),
            $this->makeTraveler(2, 'Борис'),
        ]);

        $incomplete = $this->makeMeal(1, 1000, []);
        $complete = $this->makeMeal(1, 100, [
            $this->makeItem(1, 40),
            $this->makeItem(2, 60),
        ]);

        $result = (new MealSettlement)->calculate($travelers, new Collection([$incomplete, $complete]));

        $this->assertSame(100.0, $result['total']);
        $this->assertSame(1, $result['incomplete_count']);
        $this->assertSame(60.0, $result['balances'][0]['balance']);
        $this->assertSame(-60.0, $result['balances'][1]['balance']);
    }

    private function makeTraveler(int $id, string $name): Traveler
    {
        $traveler = new Traveler;
        $traveler->id = $id;
        $traveler->name = $name;

        return $traveler;
    }

    /**
     * @param  list<MealItem>  $items
     */
    private function makeMeal(int $payerId, float $amount, array $items): Meal
    {
        $meal = new Meal;
        $meal->traveler_id = $payerId;
        $meal->amount = $amount;
        $meal->setRelation('items', new Collection($items));

        return $meal;
    }

    private function makeItem(int $travelerId, float $amount): MealItem
    {
        $item = new MealItem;
        $item->traveler_id = $travelerId;
        $item->amount = $amount;

        return $item;
    }
}

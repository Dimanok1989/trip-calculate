<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['meal_id', 'traveler_id', 'name', 'amount'])]
class MealItem extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function meal(): BelongsTo
    {
        return $this->belongsTo(Meal::class);
    }

    public function traveler(): BelongsTo
    {
        return $this->belongsTo(Traveler::class);
    }
}

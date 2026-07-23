<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['trip_id', 'name'])]
class Traveler extends Model
{
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function meals(): HasMany
    {
        return $this->hasMany(Meal::class);
    }

    public function mealItems(): HasMany
    {
        return $this->hasMany(MealItem::class);
    }
}


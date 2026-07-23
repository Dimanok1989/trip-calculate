<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['trip_id', 'traveler_id', 'title', 'amount', 'spent_at', 'has_time'])]
class Meal extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'spent_at' => 'datetime',
            'has_time' => 'boolean',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function traveler(): BelongsTo
    {
        return $this->belongsTo(Traveler::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MealItem::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(MealPhoto::class);
    }

    public function spentLabel(): string
    {
        if ($this->has_time) {
            return $this->spent_at->format('d.m.Y H:i');
        }

        return $this->spent_at->format('d.m.Y');
    }

    public function isFullyAllocated(): bool
    {
        if ($this->items->isEmpty()) {
            return false;
        }

        $itemsTotal = round((float) $this->items->sum(fn (MealItem $item) => (float) $item->amount), 2);

        return abs($itemsTotal - (float) $this->amount) < 0.009;
    }
}

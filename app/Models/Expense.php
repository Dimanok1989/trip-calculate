<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['trip_id', 'traveler_id', 'amount', 'type', 'type_custom', 'comment', 'spent_at', 'has_time'])]
class Expense extends Model
{
    public const TYPE_FUEL = 'fuel';

    public const TYPE_TOLL = 'toll';

    public const TYPE_HOUSING = 'housing';

    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_FUEL => 'Бензин',
        self::TYPE_TOLL => 'Платная дорога',
        self::TYPE_HOUSING => 'Жильё',
        self::TYPE_OTHER => 'Другое',
    ];

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

    public function typeLabel(): string
    {
        if ($this->type === self::TYPE_OTHER && filled($this->type_custom)) {
            return $this->type_custom;
        }

        return self::TYPES[$this->type] ?? $this->type;
    }

    public function spentLabel(): string
    {
        if ($this->has_time) {
            return $this->spent_at->format('d.m.Y H:i');
        }

        return $this->spent_at->format('d.m.Y');
    }
}

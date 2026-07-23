<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['meal_id', 'path'])]
class MealPhoto extends Model
{
    public function meal(): BelongsTo
    {
        return $this->belongsTo(Meal::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}

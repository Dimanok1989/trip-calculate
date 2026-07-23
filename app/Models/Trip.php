<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class Trip extends Model
{
    public function travelers(): HasMany
    {
        return $this->hasMany(Traveler::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function meals(): HasMany
    {
        return $this->hasMany(Meal::class);
    }
}


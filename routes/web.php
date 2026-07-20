<?php

use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\TripController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TripController::class, 'index'])->name('home');
Route::post('/trips', [TripController::class, 'store'])->name('trips.store');
Route::put('/trips/{trip}', [TripController::class, 'update'])->name('trips.update');
Route::get('/trips/{trip}', [TripController::class, 'show'])->name('trips.show');
Route::post('/trips/{trip}/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
Route::put('/trips/{trip}/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
Route::post('/trips/{trip}/expenses/import-avtodor', [ExpenseController::class, 'importAvtodor'])
    ->name('expenses.import-avtodor');

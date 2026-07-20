<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseRequest;
use App\Models\Expense;
use App\Models\Trip;
use Illuminate\Http\RedirectResponse;

class ExpenseController extends Controller
{
    public function store(ExpenseRequest $request, Trip $trip): RedirectResponse
    {
        $trip->expenses()->create($request->expenseAttributes());

        return redirect()->route('trips.show', $trip);
    }

    public function update(ExpenseRequest $request, Trip $trip, Expense $expense): RedirectResponse
    {
        abort_unless($expense->trip_id === $trip->id, 404);

        $expense->update($request->expenseAttributes());

        return redirect()->route('trips.show', $trip);
    }
}

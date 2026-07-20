<?php

namespace App\Http\Controllers;

use App\Exceptions\AvtodorCsvException;
use App\Http\Requests\AutodorImportRequest;
use App\Http\Requests\ExpenseRequest;
use App\Models\Expense;
use App\Models\Trip;
use App\Services\AvtodorCsvImporter;
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

    public function importAvtodor(
        AutodorImportRequest $request,
        Trip $trip,
        AvtodorCsvImporter $importer,
    ): RedirectResponse {
        $path = $request->file('file')->getRealPath();
        $contents = file_get_contents($path);
        if ($contents === false || trim($contents) === '') {
            return back()->withErrors(['file' => 'Не удалось прочитать CSV-файл.']);
        }

        try {
            $result = $importer->import($trip, (int) $request->validated('traveler_id'), $contents);
        } catch (AvtodorCsvException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return redirect()
            ->route('trips.show', $trip)
            ->with('avtodor_import', $result);
    }
}

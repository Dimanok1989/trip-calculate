<?php

namespace App\Http\Controllers;

use App\Http\Requests\MealRequest;
use App\Models\Meal;
use App\Models\Trip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MealController extends Controller
{
    public function store(MealRequest $request, Trip $trip): RedirectResponse
    {
        DB::transaction(function () use ($request, $trip): void {
            /** @var Meal $meal */
            $meal = $trip->meals()->create($request->mealAttributes());

            foreach ($request->itemAttributes() as $item) {
                $meal->items()->create($item);
            }

            $this->storePhotos($request, $trip, $meal);
        });

        return redirect()->route('trips.show', $trip);
    }

    public function update(MealRequest $request, Trip $trip, Meal $meal): RedirectResponse
    {
        abort_unless($meal->trip_id === $trip->id, 404);

        DB::transaction(function () use ($request, $trip, $meal): void {
            $meal->update($request->mealAttributes());

            $meal->items()->delete();
            foreach ($request->itemAttributes() as $item) {
                $meal->items()->create($item);
            }

            $removeIds = $request->removePhotoIds();
            if ($removeIds !== []) {
                $photos = $meal->photos()->whereIn('id', $removeIds)->get();
                foreach ($photos as $photo) {
                    Storage::disk('public')->delete($photo->path);
                    $photo->delete();
                }
            }

            $this->storePhotos($request, $trip, $meal);
        });

        return redirect()->route('trips.show', $trip);
    }

    public function destroy(Trip $trip, Meal $meal): RedirectResponse
    {
        abort_unless($meal->trip_id === $trip->id, 404);

        DB::transaction(function () use ($meal): void {
            foreach ($meal->photos as $photo) {
                Storage::disk('public')->delete($photo->path);
            }

            $meal->delete();
        });

        return redirect()->route('trips.show', $trip);
    }

    private function storePhotos(MealRequest $request, Trip $trip, Meal $meal): void
    {
        $files = $request->file('photos', []);

        if (! is_array($files)) {
            $files = $files ? [$files] : [];
        }

        foreach ($files as $file) {
            if ($file === null) {
                continue;
            }

            $path = $file->store('meals/'.$trip->id, 'public');
            $meal->photos()->create(['path' => $path]);
        }
    }
}

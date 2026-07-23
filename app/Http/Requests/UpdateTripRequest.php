<?php

namespace App\Http\Requests;

use App\Models\Traveler;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'travelers' => ['required', 'array', 'min:2'],
            'travelers.*.id' => ['nullable', 'integer'],
            'travelers.*.name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название поездки.',
            'travelers.required' => 'Добавьте путешественников.',
            'travelers.min' => 'Нужно минимум два путешественника.',
            'travelers.*.name.required' => 'Имя путешественника не может быть пустым.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var \App\Models\Trip $trip */
            $trip = $this->route('trip');
            $payload = collect($this->input('travelers', []));
            $keptIds = $payload->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();

            foreach ($keptIds as $id) {
                $belongs = Traveler::query()
                    ->where('trip_id', $trip->id)
                    ->whereKey($id)
                    ->exists();

                if (! $belongs) {
                    $validator->errors()->add('travelers', 'Участник не принадлежит этой поездке.');

                    return;
                }
            }

            $removedWithExpenses = Traveler::query()
                ->where('trip_id', $trip->id)
                ->whereNotIn('id', $keptIds ?: [0])
                ->where(function ($query) {
                    $query->whereHas('expenses')
                        ->orWhereHas('meals')
                        ->orWhereHas('mealItems');
                })
                ->pluck('name');

            if ($removedWithExpenses->isNotEmpty()) {
                $names = $removedWithExpenses->implode(', ');
                $validator->errors()->add(
                    'travelers',
                    "Нельзя удалить участников с расходами или питанием: {$names}."
                );
            }
        });
    }
}

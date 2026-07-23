<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('spent_time') === '') {
            $this->merge(['spent_time' => null]);
        }

        $items = $this->input('items');

        if (is_string($items)) {
            $decoded = json_decode($items, true);
            $this->merge(['items' => is_array($decoded) ? $decoded : []]);
        } elseif ($items === null) {
            $this->merge(['items' => []]);
        }

        $removePhotoIds = $this->input('remove_photo_ids');

        if (is_string($removePhotoIds)) {
            $decoded = json_decode($removePhotoIds, true);
            $this->merge(['remove_photo_ids' => is_array($decoded) ? $decoded : []]);
        } elseif ($removePhotoIds === null) {
            $this->merge(['remove_photo_ids' => []]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var \App\Models\Trip $trip */
        $trip = $this->route('trip');

        return [
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'traveler_id' => [
                'required',
                'integer',
                Rule::exists('travelers', 'id')->where('trip_id', $trip->id),
            ],
            'spent_date' => ['required', 'date'],
            'spent_time' => ['nullable', 'date_format:H:i'],
            'items' => ['nullable', 'array'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.amount' => ['required', 'numeric', 'gt:0'],
            'items.*.traveler_id' => [
                'required',
                'integer',
                Rule::exists('travelers', 'id')->where('trip_id', $trip->id),
            ],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['image', 'max:5120'],
            'remove_photo_ids' => ['nullable', 'array'],
            'remove_photo_ids.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Укажите название.',
            'amount.required' => 'Укажите сумму.',
            'amount.gt' => 'Сумма должна быть больше нуля.',
            'traveler_id.required' => 'Выберите плательщика.',
            'traveler_id.exists' => 'Плательщик должен быть участником поездки.',
            'spent_date.required' => 'Укажите дату.',
            'spent_date.date' => 'Некорректная дата.',
            'spent_time.date_format' => 'Некорректное время.',
            'items.*.name.required' => 'Укажите название позиции.',
            'items.*.amount.required' => 'Укажите сумму позиции.',
            'items.*.amount.gt' => 'Сумма позиции должна быть больше нуля.',
            'items.*.traveler_id.required' => 'Выберите участника для позиции.',
            'items.*.traveler_id.exists' => 'Участник позиции должен быть из поездки.',
            'photos.*.image' => 'Файл должен быть изображением.',
            'photos.*.max' => 'Размер фото не более 5 МБ.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $items = $this->input('items', []);

            if (! is_array($items) || count($items) === 0) {
                return;
            }

            $itemsTotal = round(array_sum(array_map(
                fn ($item) => (float) ($item['amount'] ?? 0),
                $items,
            )), 2);
            $amount = round((float) $this->input('amount'), 2);

            if (abs($itemsTotal - $amount) >= 0.009) {
                $validator->errors()->add(
                    'items',
                    'Сумма позиций должна равняться общей сумме (сейчас '.$itemsTotal.' из '.$amount.').'
                );
            }
        });
    }

    /**
     * @return array{traveler_id: int, title: string, amount: mixed, spent_at: string, has_time: bool}
     */
    public function mealAttributes(): array
    {
        $data = $this->validated();
        $hasTime = filled($data['spent_time'] ?? null);
        $spentAt = $hasTime
            ? "{$data['spent_date']} {$data['spent_time']}:00"
            : "{$data['spent_date']} 00:00:00";

        return [
            'traveler_id' => $data['traveler_id'],
            'title' => $data['title'],
            'amount' => $data['amount'],
            'spent_at' => $spentAt,
            'has_time' => $hasTime,
        ];
    }

    /**
     * @return list<array{traveler_id: int, name: string, amount: mixed}>
     */
    public function itemAttributes(): array
    {
        $items = $this->validated('items') ?? [];

        return array_values(array_map(fn (array $item) => [
            'traveler_id' => (int) $item['traveler_id'],
            'name' => $item['name'],
            'amount' => $item['amount'],
        ], $items));
    }

    /**
     * @return list<int>
     */
    public function removePhotoIds(): array
    {
        return array_values(array_map('intval', $this->validated('remove_photo_ids') ?? []));
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AutodorImportRequest extends FormRequest
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
        $trip = $this->route('trip');

        return [
            'traveler_id' => [
                'required',
                'integer',
                Rule::exists('travelers', 'id')->where('trip_id', $trip->id),
            ],
            'file' => ['required', 'file', 'extensions:csv', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'traveler_id.required' => 'Выберите плательщика.',
            'traveler_id.exists' => 'Плательщик должен быть участником поездки.',
            'file.required' => 'Выберите CSV-файл экспорта Автодора.',
            'file.extensions' => 'Принимается только файл в формате CSV.',
            'file.max' => 'Размер файла не должен превышать 2 МБ.',
        ];
    }
}

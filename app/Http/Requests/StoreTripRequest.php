<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTripRequest extends FormRequest
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
            'travelers.*' => ['required', 'string', 'max:255'],
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
            'travelers.*.required' => 'Имя путешественника не может быть пустым.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
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
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $trip = $this->route('trip');

        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'type' => ['required', 'string', Rule::in(array_keys(Expense::TYPES))],
            'type_custom' => ['nullable', 'required_if:type,'.Expense::TYPE_OTHER, 'string', 'max:255'],
            'traveler_id' => [
                'required',
                'integer',
                Rule::exists('travelers', 'id')->where('trip_id', $trip->id),
            ],
            'comment' => ['nullable', 'string', 'max:1000'],
            'spent_date' => ['required', 'date'],
            'spent_time' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Укажите сумму.',
            'amount.gt' => 'Сумма должна быть больше нуля.',
            'type.required' => 'Выберите тип расхода.',
            'type_custom.required_if' => 'Укажите название расхода.',
            'traveler_id.required' => 'Выберите плательщика.',
            'traveler_id.exists' => 'Плательщик должен быть участником поездки.',
            'spent_date.required' => 'Укажите дату траты.',
            'spent_date.date' => 'Некорректная дата.',
            'spent_time.date_format' => 'Некорректное время.',
        ];
    }

    /**
     * @return array{traveler_id: int, amount: mixed, type: string, type_custom: ?string, comment: ?string, spent_at: string, has_time: bool}
     */
    public function expenseAttributes(): array
    {
        $data = $this->validated();
        $hasTime = filled($data['spent_time'] ?? null);
        $spentAt = $hasTime
            ? "{$data['spent_date']} {$data['spent_time']}:00"
            : "{$data['spent_date']} 00:00:00";

        return [
            'traveler_id' => $data['traveler_id'],
            'amount' => $data['amount'],
            'type' => $data['type'],
            'type_custom' => $data['type'] === Expense::TYPE_OTHER
                ? ($data['type_custom'] ?? null)
                : null,
            'comment' => $data['comment'] ?? null,
            'spent_at' => $spentAt,
            'has_time' => $hasTime,
        ];
    }
}

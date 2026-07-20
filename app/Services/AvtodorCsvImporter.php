<?php

namespace App\Services;

use App\Exceptions\AvtodorCsvException;
use App\Models\Expense;
use App\Models\Trip;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AvtodorCsvImporter
{
    /**
     * @return array{
     *   created: int,
     *   skipped_zero: int,
     *   duplicates: list<array{spent_at: string, amount: float, label: string}>
     * }
     */
    public function import(Trip $trip, int $travelerId, string $contents): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($contents)) ?: [];
        if ($lines === [] || $lines[0] === '') {
            throw new AvtodorCsvException('Пустой CSV-файл.');
        }

        if (! $this->isAvtodorHeader($lines[0])) {
            throw new AvtodorCsvException('Файл не похож на экспорт Автодора.');
        }

        $existingKeys = $trip->expenses()
            ->get(['spent_at', 'amount'])
            ->mapWithKeys(function (Expense $expense) {
                $key = $this->duplicateKey($expense->spent_at?->format('Y-m-d H:i:s'), (float) $expense->amount);

                return [$key => true];
            })
            ->all();

        $created = 0;
        $skippedZero = 0;
        $duplicates = [];
        $toInsert = [];

        foreach ($lines as $index => $line) {
            if ($index === 0) {
                continue;
            }

            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (! $this->looksLikeDateRow($line)) {
                continue;
            }

            $row = str_getcsv($line, ';');
            if (count($row) < 8) {
                throw new AvtodorCsvException(
                    'Некорректная строка '.($index + 1).': недостаточно столбцов.',
                );
            }

            [$dateRaw, $plaza, , , , $tariff, $discount, $paid] = $row;
            $amount = $this->parseAmount($paid, $index + 1);

            if ($amount <= 0) {
                $skippedZero++;
                continue;
            }

            $spentAt = $this->parseDate(trim($dateRaw), $index + 1);
            $spentAtKey = $spentAt->format('Y-m-d H:i:s');
            $key = $this->duplicateKey($spentAtKey, $amount);

            if (isset($existingKeys[$key])) {
                $duplicates[] = [
                    'spent_at' => $spentAtKey,
                    'amount' => $amount,
                    'label' => $spentAt->format('d.m.Y H:i').' — '.$amount.' ₽',
                ];
                continue;
            }

            $existingKeys[$key] = true;
            $toInsert[] = [
                'trip_id' => $trip->id,
                'traveler_id' => $travelerId,
                'amount' => $amount,
                'type' => Expense::TYPE_TOLL,
                'type_custom' => null,
                'comment' => trim($plaza).'; тариф '.trim($tariff).' ₽; скидка '.trim($discount).'%',
                'spent_at' => $spentAtKey,
                'has_time' => true,
            ];
            $created++;
        }

        DB::transaction(function () use ($toInsert) {
            foreach ($toInsert as $attrs) {
                Expense::query()->create($attrs);
            }
        });

        return [
            'created' => $created,
            'skipped_zero' => $skippedZero,
            'duplicates' => $duplicates,
        ];
    }

    private function isAvtodorHeader(string $line): bool
    {
        $row = str_getcsv($line, ';');
        $firstColumn = trim($row[0] ?? '');

        return str_starts_with($firstColumn, 'Дата') || str_contains($line, 'Оплачено');
    }

    private function looksLikeDateRow(string $line): bool
    {
        return (bool) preg_match('/^\d{2}\.\d{2}\.\d{4}/', $line);
    }

    private function parseDate(string $raw, int $lineNumber): Carbon
    {
        $spentAt = Carbon::createFromFormat('d.m.Y H:i:s', $raw);
        $errors = Carbon::getLastErrors();

        if ($spentAt === false || ($errors['error_count'] ?? 0) > 0 || ($errors['warning_count'] ?? 0) > 0) {
            throw new AvtodorCsvException(
                'Некорректная дата в строке '.$lineNumber.': «'.$raw.'».',
            );
        }

        return $spentAt;
    }

    private function parseAmount(string $raw, int $lineNumber): float
    {
        $trimmed = trim($raw);

        if ($trimmed === '' || ! preg_match('/^\d+([,.]\d+)?$/', $trimmed)) {
            throw new AvtodorCsvException(
                'Некорректная сумма в строке '.$lineNumber.': «'.$raw.'».',
            );
        }

        return (float) str_replace(',', '.', $trimmed);
    }

    private function duplicateKey(string $spentAt, float $amount): string
    {
        return $spentAt.'|'.number_format($amount, 2, '.', '');
    }
}

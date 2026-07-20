# Avtodor CSV import — design

Date: 2026-07-20  
Scope: import toll expenses from Avtodor export CSV into a trip

## Goal

Allow importing toll road payments from an Avtodor website CSV export into the current trip’s expenses, with payer selection, CSV-only validation, zero-amount skip, and duplicate detection by date+amount.

## Approach

Server-side import (recommended):

- Frontend: button + modal (file + traveler) next to «Добавить расход».
- Backend: `POST` multipart with CSV; parse, validate, insert; flash summary with warnings.
- Single source of truth for CSV format and duplicate rules.

Alternatives rejected: client-only parse (weaker validation); preview-then-confirm (extra step for a known export format).

## UI

### Trigger

- On `Trips/Show.vue`, next to «Добавить расход»: secondary outline button **«Импорт Автодор»**.

### Modal (`AutodorImportModal.vue`)

- Short explanation: принимается только CSV-экспорт с сайта Автодора.
- Required select: плательщик (`traveler_id` from trip travelers).
- File input: `accept=".csv,text/csv"`; client check that extension is `.csv` before submit.
- Submit: «Импортировать»; Cancel/close like existing modals.
- Match existing modal patterns (`ExpenseModal.vue`: overlay, stone/teal styles).

### Feedback

After redirect back to trip show, flash messages:

- Success: сколько расходов добавлено.
- Warning (if any): сколько/какие дубликаты пропущены (дата + сумма).
- Info optional: сколько нулевых строк проигнорировано (можно кратко в success).
- Validation errors: неверный тип файла, пустой файл, нет плательщика, нечитаемый CSV.

## Backend

### Route

`POST /trips/{trip}/expenses/import-avtodor` → `ExpenseController@importAvtodor`  
Name: `expenses.import-avtodor`

### Request validation

- `traveler_id`: required, exists, belongs to the trip.
- `file`: required, file, `mimes:csv,txt` (and/or extension `csv`), max size reasonable (e.g. 2MB).

### Parsing

Expected Avtodor export columns (`;` separator):

| Column | Use |
|--------|-----|
| Дата | `spent_at` (`d.m.Y H:i:s`), `has_time = true` |
| ПВП\РВП выезда | part of `comment` |
| Сумма тарифа, ₽ | part of `comment` |
| Скидка, % | part of `comment` |
| Оплачено, ₽ | `amount` (parse `400,00` → `400.00`) |

Other columns ignored. Header row skipped (detect by first column looking like a date, or skip first line).

### Business rules

- `type` = `toll`.
- `comment` format: `{plaza}; тариф {tariff} ₽; скидка {discount}%`.
- Rows with `amount ≤ 0`: ignore (no warning required beyond optional count).
- Duplicate within trip: same `spent_at` datetime **and** same `amount` as an existing expense (any type/payer) → skip + include in warning list.
- New rows: create with selected `traveler_id`.
- Partial import: insert non-duplicates even if some rows are duplicates.

### Response

Redirect to `trips.show` with session flash, e.g.:

- `avtodor_import.created` (int)
- `avtodor_import.skipped_zero` (int)
- `avtodor_import.duplicates` (array of `{spent_at, amount}` or human labels)

UI reads flash and shows banner(s) on the trip page.

## Components / files (expected)

| Area | Files |
|------|--------|
| UI | `Show.vue`, new `AutodorImportModal.vue` |
| Route | `routes/web.php` |
| Controller | `ExpenseController@importAvtodor` |
| Request | `AutodorImportRequest` (or inline FormRequest) |
| Service (optional) | `AvtodorCsvImporter` for parse + dedupe (keeps controller thin) |
| Tests | Feature test: import valid CSV, skip zeros, skip duplicates, reject non-csv |

## Out of scope

- Updating existing expenses on re-import.
- Other toll operators / formats.
- Choosing amount = tariff instead of paid.
- Mapping each row to a different payer.

## Self-review

- No placeholders left.
- Duplicate key is datetime + amount within the trip (not unique index in DB — application-level).
- Zero rows ignored; duplicates warned; new rows inserted.
- CSV-only enforced client + server.
- Scope limited to Autodor export format already used in this project.

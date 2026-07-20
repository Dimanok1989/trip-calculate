# Avtodor CSV Import Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add «Импорт Автодор» on the trip expenses page: modal with payer + CSV upload, server-side parse/insert of toll expenses, skip zeros, warn on date+amount duplicates.

**Architecture:** Multipart `POST` to a new trip-scoped endpoint. `AvtodorCsvImporter` parses the semicolon CSV, applies business rules, and returns create counts + duplicate list. Controller flashes results; Inertia shares them; `Show.vue` displays banners. New `AutodorImportModal.vue` mirrors `ExpenseModal.vue` patterns.

**Tech Stack:** Laravel 12, Inertia + Vue 3, PHPUnit feature tests, `Illuminate\Http\UploadedFile`.

## Global Constraints

- Accept only Avtodor website CSV export (`;` separator, columns as in design spec).
- Amount = «Оплачено»; comment includes plaza + tariff + discount.
- `type` = `toll`; `has_time` = `true`.
- Zero/negative amounts: ignore (count in `skipped_zero`).
- Duplicates: same `spent_at` + `amount` already on the trip → skip + list in flash warning; still import non-duplicates.
- Payer: required `traveler_id` selected in modal (must belong to trip).
- Client + server: only `.csv` files.
- Do not commit unless the user explicitly asks for a commit.

---

## File map

| File | Role |
|------|------|
| `app/Services/AvtodorCsvImporter.php` | Parse CSV string/path; return rows to create + stats |
| `app/Http/Requests/AutodorImportRequest.php` | Validate `traveler_id` + `file` |
| `app/Http/Controllers/ExpenseController.php` | `importAvtodor` action |
| `routes/web.php` | Register route |
| `app/Http/Middleware/HandleInertiaRequests.php` | Share `flash.avtodor_import` |
| `resources/js/Components/AutodorImportModal.vue` | Modal UI + form submit |
| `resources/js/Pages/Trips/Show.vue` | Button, modal wiring, flash banners |
| `tests/Feature/AvtodorImportTest.php` | Feature tests |
| `tests/fixtures/avtodor-sample.csv` | Sample CSV for tests |

---

### Task 1: Importer service + unit-level feature coverage via pure service tests in Feature file helpers

**Files:**
- Create: `app/Services/AvtodorCsvImporter.php`
- Create: `tests/Feature/AvtodorImportTest.php` (first tests call the service directly; HTTP tests in Task 2)
- Create: `tests/fixtures/avtodor-sample.csv`

**Interfaces:**
- Consumes: CSV contents string; `Trip` (or collection of existing expenses with `spent_at` + `amount`); `int $travelerId`
- Produces: `AvtodorCsvImporter::import(Trip $trip, int $travelerId, string $csvContents): array{created: int, skipped_zero: int, duplicates: list<array{spent_at: string, amount: float, label: string}>}` — and persists new expenses inside the method (or return attributes and let controller persist). Prefer **persist inside importer** for one transaction boundary:

```php
/**
 * @return array{
 *   created: int,
 *   skipped_zero: int,
 *   duplicates: list<array{spent_at: string, amount: float, label: string}>
 * }
 */
public function import(Trip $trip, int $travelerId, string $contents): array
```

- [ ] **Step 1: Add fixture CSV**

Create `tests/fixtures/avtodor-sample.csv`:

```csv
Дата;ПВП\РВП выезда;Полоса;Электронное средство;Класс;Сумма тарифа, ₽;Скидка, %;Оплачено, ₽
18.07.2026 13:45:59;М4-71М-Воронеж;5;3086595000003325088;1;400,00;15;340,00
18.07.2026 19:50:26;ПВП-460M км на Ростов;12;3086595000003325088;1;0,00;15;0,00
18.07.2026 20:22:51;М4-515-Вор;9;3086595000003325088;1;170,00;15;144,50
```

- [ ] **Step 2: Write failing tests for the importer**

In `tests/Feature/AvtodorImportTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Trip;
use App\Services\AvtodorCsvImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvtodorImportTest extends TestCase
{
    use RefreshDatabase;

    private function makeTrip(): Trip
    {
        $this->post('/trips', [
            'name' => 'Анапа',
            'travelers' => ['Анна', 'Борис'],
        ]);

        return Trip::query()->firstOrFail();
    }

    public function test_importer_creates_toll_expenses_skips_zero(): void
    {
        $trip = $this->makeTrip();
        $anna = $trip->travelers()->where('name', 'Анна')->firstOrFail();
        $csv = file_get_contents(base_path('tests/fixtures/avtodor-sample.csv'));

        $result = app(AvtodorCsvImporter::class)->import($trip, $anna->id, $csv);

        $this->assertSame(2, $result['created']);
        $this->assertSame(1, $result['skipped_zero']);
        $this->assertSame([], $result['duplicates']);
        $this->assertDatabaseCount('expenses', 2);
        $this->assertDatabaseHas('expenses', [
            'trip_id' => $trip->id,
            'traveler_id' => $anna->id,
            'type' => Expense::TYPE_TOLL,
            'amount' => 340.00,
            'comment' => 'М4-71М-Воронеж; тариф 400,00 ₽; скидка 15%',
            'has_time' => 1,
        ]);
    }

    public function test_importer_skips_duplicates_by_spent_at_and_amount(): void
    {
        $trip = $this->makeTrip();
        $anna = $trip->travelers()->where('name', 'Анна')->firstOrFail();

        $trip->expenses()->create([
            'traveler_id' => $anna->id,
            'amount' => 340.00,
            'type' => Expense::TYPE_TOLL,
            'comment' => 'already there',
            'spent_at' => '2026-07-18 13:45:59',
            'has_time' => true,
        ]);

        $csv = file_get_contents(base_path('tests/fixtures/avtodor-sample.csv'));
        $result = app(AvtodorCsvImporter::class)->import($trip, $anna->id, $csv);

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['skipped_zero']);
        $this->assertCount(1, $result['duplicates']);
        $this->assertSame(340.0, $result['duplicates'][0]['amount']);
        $this->assertDatabaseCount('expenses', 2);
    }
}
```

- [ ] **Step 3: Run tests — expect FAIL**

Run: `php artisan test --filter=AvtodorImportTest`

Expected: FAIL (class `AvtodorCsvImporter` not found)

- [ ] **Step 4: Implement `AvtodorCsvImporter`**

Create `app/Services/AvtodorCsvImporter.php`:

```php
<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Trip;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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
            throw new RuntimeException('Пустой CSV-файл.');
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
            if ($index === 0 && ! preg_match('/^\d{2}\.\d{2}\.\d{4}/', $line)) {
                continue; // header
            }

            $row = str_getcsv($line, ';');
            if (count($row) < 8) {
                continue;
            }

            [$dateRaw, $plaza, , , , $tariff, $discount, $paid] = $row;
            $amount = $this->parseAmount($paid);

            if ($amount <= 0) {
                $skippedZero++;
                continue;
            }

            $spentAt = Carbon::createFromFormat('d.m.Y H:i:s', trim($dateRaw));
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
                'created_at' => now(),
                'updated_at' => now(),
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

    private function parseAmount(string $raw): float
    {
        return (float) str_replace([' ', ','], ['', '.'], trim($raw));
    }

    private function duplicateKey(string $spentAt, float $amount): string
    {
        return $spentAt.'|'.number_format($amount, 2, '.', '');
    }
}
```

Note: if `Expense::create` rejects mass-assignment of timestamps, omit `created_at`/`updated_at` and use model create in a loop (preferred with `#[Fillable]` — timestamps auto). Prefer:

```php
foreach ($toInsert as $attrs) {
    unset($attrs['created_at'], $attrs['updated_at']);
    Expense::query()->create($attrs);
}
```

- [ ] **Step 5: Run tests — expect PASS**

Run: `php artisan test --filter=AvtodorImportTest`

Expected: PASS (2 tests)

---

### Task 2: HTTP endpoint — FormRequest, controller, route

**Files:**
- Create: `app/Http/Requests/AutodorImportRequest.php`
- Modify: `app/Http/Controllers/ExpenseController.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/AvtodorImportTest.php` (add HTTP tests)

**Interfaces:**
- Consumes: `AutodorImportRequest` validated `traveler_id`, `file`; `AvtodorCsvImporter::import`
- Produces: redirect to `trips.show` with session key `avtodor_import` = importer result array; on parse error, validation/session error on `file`

- [ ] **Step 1: Write failing HTTP tests**

Append to `AvtodorImportTest.php`:

```php
use Illuminate\Http\UploadedFile;

public function test_http_import_avtodor_csv(): void
{
    $trip = $this->makeTrip();
    $anna = $trip->travelers()->where('name', 'Анна')->firstOrFail();
    $file = UploadedFile::fake()->createWithContent(
        'contract-transactions.csv',
        file_get_contents(base_path('tests/fixtures/avtodor-sample.csv')),
    );

    $this->post(route('expenses.import-avtodor', $trip), [
        'traveler_id' => $anna->id,
        'file' => $file,
    ])
        ->assertRedirect(route('trips.show', $trip))
        ->assertSessionHas('avtodor_import.created', 2)
        ->assertSessionHas('avtodor_import.skipped_zero', 1);

    $this->assertDatabaseCount('expenses', 2);
}

public function test_http_rejects_non_csv_extension(): void
{
    $trip = $this->makeTrip();
    $anna = $trip->travelers()->where('name', 'Анна')->firstOrFail();
    $file = UploadedFile::fake()->create('notes.txt', 100, 'text/plain');

    $this->post(route('expenses.import-avtodor', $trip), [
        'traveler_id' => $anna->id,
        'file' => $file,
    ])->assertSessionHasErrors('file');
}

public function test_http_import_reports_duplicates_in_session(): void
{
    $trip = $this->makeTrip();
    $anna = $trip->travelers()->where('name', 'Анна')->firstOrFail();

    $trip->expenses()->create([
        'traveler_id' => $anna->id,
        'amount' => 340.00,
        'type' => Expense::TYPE_TOLL,
        'comment' => 'dup',
        'spent_at' => '2026-07-18 13:45:59',
        'has_time' => true,
    ]);

    $file = UploadedFile::fake()->createWithContent(
        'contract-transactions.csv',
        file_get_contents(base_path('tests/fixtures/avtodor-sample.csv')),
    );

    $this->post(route('expenses.import-avtodor', $trip), [
        'traveler_id' => $anna->id,
        'file' => $file,
    ])
        ->assertRedirect(route('trips.show', $trip))
        ->assertSessionHas('avtodor_import.created', 1)
        ->assertSessionHas('avtodor_import.duplicates');
}
```

- [ ] **Step 2: Run HTTP tests — expect FAIL**

Run: `php artisan test --filter=AvtodorImportTest`

Expected: FAIL (route `expenses.import-avtodor` not defined)

- [ ] **Step 3: Add FormRequest**

Create `app/Http/Requests/AutodorImportRequest.php`:

```php
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
```

- [ ] **Step 4: Add controller method + route**

In `ExpenseController.php`:

```php
use App\Http\Requests\AutodorImportRequest;
use App\Services\AvtodorCsvImporter;
use RuntimeException;
use Throwable;

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
    } catch (Throwable $e) {
        return back()->withErrors(['file' => 'Некорректный CSV-файл экспорта Автодора.']);
    }

    return redirect()
        ->route('trips.show', $trip)
        ->with('avtodor_import', $result);
}
```

In `routes/web.php` after expense update route:

```php
Route::post('/trips/{trip}/expenses/import-avtodor', [ExpenseController::class, 'importAvtodor'])
    ->name('expenses.import-avtodor');
```

- [ ] **Step 5: Run tests — expect PASS**

Run: `php artisan test --filter=AvtodorImportTest`

Expected: all PASS

If `extensions:csv` rejects `createWithContent` fakes, switch rule to `mimes:csv,txt` **and** custom closure checking `str_ends_with(strtolower($file->getClientOriginalName()), '.csv')`, matching the design (“only csv”). Prefer original-name `.csv` check so `.txt` always fails.

---

### Task 3: Share flash + UI modal + Show page

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Create: `resources/js/Components/AutodorImportModal.vue`
- Modify: `resources/js/Pages/Trips/Show.vue`

**Interfaces:**
- Consumes: `page.props.flash.avtodor_import` shaped as `{ created, skipped_zero, duplicates: [{ label, ... }] }`
- Produces: button + modal posting multipart to `/trips/{id}/expenses/import-avtodor` with `forceFormData: true`

- [ ] **Step 1: Share flash in Inertia middleware**

```php
'flash' => [
    'success' => fn () => $request->session()->get('success'),
    'avtodor_import' => fn () => $request->session()->get('avtodor_import'),
],
```

- [ ] **Step 2: Create `AutodorImportModal.vue`**

```vue
<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    tripId: { type: [Number, String], required: true },
    travelers: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const form = useForm({
    traveler_id: '',
    file: null,
});

const clientError = ref('');

watch(
    () => props.show,
    (open) => {
        if (open) {
            form.defaults({
                traveler_id: props.travelers[0]?.id ?? '',
                file: null,
            });
            form.reset();
            form.clearErrors();
            clientError.value = '';
            return;
        }
        form.reset();
        clientError.value = '';
    },
);

function onFileChange(event) {
    const file = event.target.files?.[0] ?? null;
    clientError.value = '';
    if (file && !file.name.toLowerCase().endsWith('.csv')) {
        clientError.value = 'Принимается только файл в формате CSV.';
        form.file = null;
        event.target.value = '';
        return;
    }
    form.file = file;
}

function close() {
    form.reset();
    clientError.value = '';
    emit('close');
}

function submit() {
    if (!form.file) {
        clientError.value = 'Выберите CSV-файл экспорта Автодора.';
        return;
    }
    if (!String(form.file.name).toLowerCase().endsWith('.csv')) {
        clientError.value = 'Принимается только файл в формате CSV.';
        return;
    }

    form.post(`/trips/${props.tripId}/expenses/import-avtodor`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => close(),
    });
}
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/50 p-4"
        @click.self="close"
    >
        <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl" role="dialog" aria-modal="true">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-stone-800">Импорт Автодор</h3>
                <button type="button" class="text-stone-400 hover:text-stone-700" @click="close">×</button>
            </div>

            <p class="mb-4 text-sm text-stone-600">
                Загрузите CSV-файл, сделанный экспортом на сайте Автодора. Другие форматы не принимаются.
            </p>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium text-stone-700" for="avtodor_traveler_id">
                        Плательщик
                    </label>
                    <select
                        id="avtodor_traveler_id"
                        v-model="form.traveler_id"
                        class="w-full rounded-lg border border-stone-300 px-3 py-2 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                        required
                    >
                        <option disabled value="">Выберите участника</option>
                        <option v-for="traveler in travelers" :key="traveler.id" :value="traveler.id">
                            {{ traveler.name }}
                        </option>
                    </select>
                    <p v-if="form.errors.traveler_id" class="mt-1 text-sm text-red-600">
                        {{ form.errors.traveler_id }}
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-stone-700" for="avtodor_file">CSV-файл</label>
                    <input
                        id="avtodor_file"
                        type="file"
                        accept=".csv,text/csv"
                        class="block w-full text-sm text-stone-700"
                        @change="onFileChange"
                    />
                    <p v-if="clientError" class="mt-1 text-sm text-red-600">{{ clientError }}</p>
                    <p v-if="form.errors.file" class="mt-1 text-sm text-red-600">{{ form.errors.file }}</p>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button
                        type="button"
                        class="rounded-lg border border-stone-300 px-4 py-2 text-stone-700 hover:bg-stone-50"
                        @click="close"
                    >
                        Отмена
                    </button>
                    <button
                        type="submit"
                        class="rounded-lg bg-teal-700 px-4 py-2 font-medium text-white hover:bg-teal-800 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        Импортировать
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
```

- [ ] **Step 3: Wire `Show.vue`**

1. Import `AutodorImportModal` and `usePage` from `@inertiajs/vue3`.
2. `const showAutodorModal = ref(false)`.
3. `const avtodorImport = computed(() => usePage().props.flash?.avtodor_import ?? null)`.
4. Next to «Добавить расход», add button group:

```vue
<div class="flex flex-wrap items-center gap-2">
    <button
        type="button"
        class="rounded-lg border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 hover:bg-stone-50"
        @click="showAutodorModal = true"
    >
        Импорт Автодор
    </button>
    <button
        type="button"
        class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800"
        @click="openCreateExpense"
    >
        Добавить расход
    </button>
</div>
```

5. Above the expenses table (or top of expenses section), banners:

```vue
<div
    v-if="avtodorImport"
    class="mb-4 space-y-2"
>
    <div class="rounded-lg bg-teal-50 px-4 py-3 text-sm text-teal-900">
        Импорт Автодор: добавлено {{ avtodorImport.created }}
        <span v-if="avtodorImport.skipped_zero">
            , нулевых пропущено {{ avtodorImport.skipped_zero }}
        </span>
    </div>
    <div
        v-if="avtodorImport.duplicates?.length"
        class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900"
    >
        <p class="font-medium">Предупреждение: пропущены дубликаты (дата и сумма уже есть):</p>
        <ul class="mt-1 list-disc pl-5">
            <li v-for="(dup, i) in avtodorImport.duplicates" :key="i">{{ dup.label }}</li>
        </ul>
    </div>
</div>
```

6. Mount modal:

```vue
<AutodorImportModal
    :show="showAutodorModal"
    :trip-id="trip.id"
    :travelers="travelers"
    @close="showAutodorModal = false"
/>
```

- [ ] **Step 4: Manual smoke check**

Run: `php artisan test --filter=AvtodorImportTest`  
Expected: PASS

Optionally open trip page, import `d:\Downloads\contract-transactions.csv` for Dmitry — expect new rows only for non-duplicates (prior import already filled trip 1).

---

## Spec coverage checklist

| Spec item | Task |
|-----------|------|
| Button next to add expense | Task 3 |
| Modal explanation + CSV only | Task 3 |
| Payer select | Task 3 |
| Server CSV validation | Task 2 |
| Parse Autodor columns; amount=paid; comment | Task 1 |
| type=toll; skip zeros | Task 1 |
| Duplicate by spent_at+amount; partial import; warning | Task 1–3 |
| Route + flash | Task 2–3 |
| Feature tests | Task 1–2 |

## Placeholder / consistency self-review

- No TBD/TODO left.
- Flash key consistently `avtodor_import` (session + Inertia share + Vue).
- Route name `expenses.import-avtodor`.
- Duplicate key format `Y-m-d H:i:s|0.00` via `number_format`.

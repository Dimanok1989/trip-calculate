# Trip Expense-Type Pie Chart Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** On the trip detail page, when expenses span two or more `type_label` values, show a static pie chart of spend by type between the settlement and expenses sections.

**Architecture:** Pure JS helper aggregates expenses by `type_label` (sum, percent, color). A small presentational Vue component renders a CSS `conic-gradient` pie plus legend. `Show.vue` inserts the card between existing sections and gates visibility on `segments.length >= 2`.

**Tech Stack:** Vue 3 + Inertia, Tailwind CSS v4, Vite. No new npm dependencies.

## Global Constraints

- Scope: trip detail page (`resources/js/Pages/Trips/Show.vue`) and new helper/component under `resources/js/`.
- Group by `type_label` (custom «Другое» names are separate sectors).
- Show section only when distinct labels ≥ 2; otherwise omit entirely.
- Static chart only — no hover, tooltips, or clicks.
- No chart libraries; no backend/API/settlement changes.
- Title copy: «По типам расходов».
- Do not commit unless the user explicitly asks for a commit.

---

## File map

| File | Role |
|------|------|
| `resources/js/utils/expenseTypeBreakdown.js` | Aggregate expenses → sorted segments with color + percent |
| `resources/js/Components/ExpenseTypePie.vue` | Pie (`conic-gradient`) + legend UI |
| `resources/js/Pages/Trips/Show.vue` | Mount section between «Расчёт» and «Расходы» |

No automated frontend test runner in this repo. Verify aggregation with `node --input-type=module`; verify UI manually in the browser.

---

### Task 1: Breakdown helper

**Files:**
- Create: `resources/js/utils/expenseTypeBreakdown.js`

**Interfaces:**
- Consumes: expense rows with `type_label` (string) and `amount` (number|string)
- Produces:
  - `EXPENSE_TYPE_COLORS: string[]` — fixed palette
  - `formatPercent(ratio: number): string` — `0..1` → `"33%"` or `"12.5%"`
  - `buildExpenseTypeBreakdown(expenses: Array<{type_label: string, amount: number|string}>): Array<{label: string, amount: number, percent: number, color: string}>`
    - Sorted by `amount` descending
    - `percent` is share of total (0..1)
    - Empty input → `[]`
    - Single label → one-element array (caller hides UI)

- [x] **Step 1: Write the helper with failing-style verification harness inline first (empty stubs)**

Create `resources/js/utils/expenseTypeBreakdown.js`:

```js
export const EXPENSE_TYPE_COLORS = [
    '#0f766e', // teal-700
    '#d97706', // amber-600
    '#57534e', // stone-600
    '#0d9488', // teal-600
    '#b45309', // amber-700
    '#78716c', // stone-500
    '#134e4a', // teal-900
    '#f59e0b', // amber-500
];

/**
 * @param {number} ratio fraction 0..1
 * @returns {string}
 */
export function formatPercent(ratio) {
    const pct = ratio * 100;
    const rounded = Math.round(pct * 10) / 10;
    if (Number.isInteger(rounded) || Math.abs(rounded - Math.round(rounded)) < 0.05) {
        return `${Math.round(rounded)}%`;
    }
    return `${rounded.toFixed(1)}%`;
}

/**
 * @param {Array<{ type_label?: string, amount?: number|string }>} expenses
 * @returns {Array<{ label: string, amount: number, percent: number, color: string }>}
 */
export function buildExpenseTypeBreakdown(expenses) {
    const list = Array.isArray(expenses) ? expenses : [];
    const totals = new Map();

    for (const expense of list) {
        const label = String(expense?.type_label ?? '').trim() || 'Без типа';
        const amount = Number(expense?.amount) || 0;
        totals.set(label, (totals.get(label) || 0) + amount);
    }

    const grandTotal = [...totals.values()].reduce((sum, n) => sum + n, 0);
    const segments = [...totals.entries()]
        .map(([label, amount]) => ({
            label,
            amount,
            percent: grandTotal > 0 ? amount / grandTotal : 0,
        }))
        .sort((a, b) => b.amount - a.amount || a.label.localeCompare(b.label, 'ru'));

    return segments.map((segment, index) => ({
        ...segment,
        color: EXPENSE_TYPE_COLORS[index % EXPENSE_TYPE_COLORS.length],
    }));
}
```

- [x] **Step 2: Verify aggregation with Node**

Run from repo root:

```bash
node --input-type=module -e "
import { buildExpenseTypeBreakdown, formatPercent } from './resources/js/utils/expenseTypeBreakdown.js';

const one = buildExpenseTypeBreakdown([
  { type_label: 'Бензин', amount: 1000 },
  { type_label: 'Бензин', amount: 500 },
]);
if (one.length !== 1 || one[0].amount !== 1500) throw new Error('single label failed');

const multi = buildExpenseTypeBreakdown([
  { type_label: 'Бензин', amount: 1000 },
  { type_label: 'Жильё', amount: 3000 },
  { type_label: 'СуSouvenir', amount: 500 },
  { type_label: 'СуSouvenir', amount: 500 },
]);
if (multi.length !== 3) throw new Error('expected 3 segments');
if (multi[0].label !== 'Жильё' || multi[0].amount !== 3000) throw new Error('sort/sum failed');
if (Math.abs(multi[0].percent - 0.6) > 1e-9) throw new Error('percent failed');
if (!multi[0].color) throw new Error('color missing');

if (formatPercent(0.33) !== '33%') throw new Error('formatPercent int');
if (formatPercent(0.125) !== '12.5%') throw new Error('formatPercent decimal');

console.log('OK');
"
```

Expected: `OK`

- [x] **Step 3: Stop — do not commit unless the user asks**

---

### Task 2: `ExpenseTypePie` component

**Files:**
- Create: `resources/js/Components/ExpenseTypePie.vue`

**Interfaces:**
- Consumes: `segments` from `buildExpenseTypeBreakdown` (`label`, `amount`, `percent`, `color`); `formatMoney(value: number|string): string` from parent
- Produces: presentational block with pie + legend (no section card chrome — parent owns the card)

- [x] **Step 1: Create the component**

```vue
<script setup>
import { computed } from 'vue';
import { formatPercent } from '../utils/expenseTypeBreakdown.js';

const props = defineProps({
    segments: {
        type: Array,
        required: true,
    },
    formatMoney: {
        type: Function,
        required: true,
    },
});

const gradient = computed(() => {
    if (!props.segments.length) {
        return 'transparent';
    }

    let cursor = 0;
    const stops = props.segments.map((segment) => {
        const start = cursor * 100;
        cursor += segment.percent;
        const end = cursor * 100;
        return `${segment.color} ${start}% ${end}%`;
    });

    return `conic-gradient(${stops.join(', ')})`;
});
</script>

<template>
    <div class="flex flex-col items-center gap-6 sm:flex-row sm:items-start sm:justify-center">
        <div
            class="h-44 w-44 shrink-0 rounded-full"
            role="img"
            :aria-label="'Распределение расходов по типам'"
            :style="{ background: gradient }"
        />

        <ul class="w-full max-w-sm space-y-2 text-sm">
            <li
                v-for="segment in segments"
                :key="segment.label"
                class="flex items-baseline justify-between gap-3"
            >
                <span class="flex min-w-0 items-center gap-2 text-stone-800">
                    <span
                        class="inline-block h-3 w-3 shrink-0 rounded-sm"
                        :style="{ backgroundColor: segment.color }"
                        aria-hidden="true"
                    />
                    <span class="truncate">{{ segment.label }}</span>
                </span>
                <span class="shrink-0 tabular-nums text-stone-600">
                    {{ formatMoney(segment.amount) }}
                    <span class="text-stone-400">·</span>
                    {{ formatPercent(segment.percent) }}
                </span>
            </li>
        </ul>
    </div>
</template>
```

- [x] **Step 2: Sanity-check import path**

Confirm file exists at `resources/js/Components/ExpenseTypePie.vue` and imports `../utils/expenseTypeBreakdown.js` (sibling of `Components/` under `resources/js/`).

- [x] **Step 3: Stop — do not commit unless the user asks**

---

### Task 3: Wire into trip detail page

**Files:**
- Modify: `resources/js/Pages/Trips/Show.vue`

**Interfaces:**
- Consumes: `expenses` prop; `buildExpenseTypeBreakdown`; `ExpenseTypePie`
- Produces: card section between settlement and expenses when `expenseTypeSegments.length >= 2`

- [x] **Step 1: Update script imports and computed**

Change the script setup top of `Show.vue` from:

```js
import ExpenseModal from '../../Components/ExpenseModal.vue';
import TripEditModal from '../../Components/TripEditModal.vue';
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
```

to:

```js
import ExpenseModal from '../../Components/ExpenseModal.vue';
import ExpenseTypePie from '../../Components/ExpenseTypePie.vue';
import TripEditModal from '../../Components/TripEditModal.vue';
import { buildExpenseTypeBreakdown } from '../../utils/expenseTypeBreakdown.js';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
```

After the `defineProps({...});` block, add:

```js
const expenseTypeSegments = computed(() => buildExpenseTypeBreakdown(props.expenses));
```

Keep existing modal refs and helpers unchanged.

- [x] **Step 2: Insert the pie section in the template**

Immediately after the closing `</section>` of the «Расчёт» block (the section that ends just before «Расходы»), and before the «Расходы» `<section>`, insert:

```vue
        <section
            v-if="expenseTypeSegments.length >= 2"
            class="mb-8 rounded-xl border border-stone-200 bg-white p-6 shadow-sm"
        >
            <h2 class="mb-4 text-xl font-medium text-stone-800">По типам расходов</h2>
            <ExpenseTypePie :segments="expenseTypeSegments" :format-money="formatMoney" />
        </section>
```

Ensure the «Расходы» section still has no extra top margin conflict: it currently has `class="rounded-xl border..."` without `mb-*`; the new section uses `mb-8` like «Расчёт».

- [x] **Step 3: Manual browser checks**

With `npm run dev` (or built assets) and a trip that has expenses:

1. Only one type (e.g. all «Бензин») → pie section absent.
2. Two+ types (e.g. «Бензин» + «Жильё», or two different custom «Другое» labels) → section between settlement and table; legend sums match table totals; percents look right.
3. Narrow viewport → pie above legend; wide → side by side.
4. No console errors; no new packages in `package.json`.

*(Agent note: aggregation verified via Node; PHPUnit smoke passed. Please confirm layout in browser.)*

- [x] **Step 4: Optional PHPUnit smoke (existing flow still green)**

Run:

```bash
php artisan test --filter=TripFlowTest
```

Expected: all tests in that file PASS (page still renders; no backend contract change).

- [x] **Step 5: Stop — do not commit unless the user asks**

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Between settlement and expenses table | Task 3 |
| Visible only when ≥ 2 `type_label`s | Task 3 (`v-if`) |
| Group by `type_label` / custom other separate | Task 1 |
| Sum + percent, sort by amount desc | Task 1 |
| Card chrome + title «По типам расходов» | Task 3 |
| Pie + legend with sum and % | Task 2 |
| Mobile stack / desktop row | Task 2 (`flex-col` / `sm:flex-row`) |
| Static, no interactivity | Task 2 |
| Teal/stone/amber palette | Task 1 colors |
| `formatMoney` reuse | Task 3 passes helper |
| Percent formatting 33% / 12.5% | Task 1 `formatPercent` |
| No new chart libs / no backend changes | All tasks |

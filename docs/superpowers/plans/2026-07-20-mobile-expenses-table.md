# Mobile Expenses Table Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** On viewports below `md`, render each expense row as a labeled block; keep the existing table with headers from `md` up.

**Architecture:** Single `<table>` markup in `Show.vue` with `data-label` on content cells. Custom CSS in `app.css` (Tailwind cannot express `attr(data-label)` `::before`) stacks rows into cards below `md` and restores table layout at `md+`.

**Tech Stack:** Vue 3 + Inertia, Tailwind CSS v4 (`@import 'tailwindcss'`), Vite.

## Global Constraints

- Scope: expenses table on `resources/js/Pages/Trips/Show.vue` only.
- Approach: CSS-only responsive table; no duplicate card markup; no new components.
- Breakpoint: Tailwind `md` (768px / `48rem`).
- Mobile fields (label left / value right): Дата, Сумма, Тип расхода, Плательщик, Комментарий; «Изменить» without label, right-aligned.
- Empty state: centered message, no field labels.
- No horizontal scroll on mobile for this section.
- Do not commit unless the user explicitly asks for a commit.

---

## File map

| File | Role |
|------|------|
| `resources/css/app.css` | Mobile/desktop table layout rules for `.expenses-table` |
| `resources/js/Pages/Trips/Show.vue` | Add `expenses-table` class, `data-label` attrs, drop mobile overflow scroll |

No new files. No automated frontend tests in this repo (`package.json` has only `dev` / `build`).

---

### Task 1: Responsive expenses table CSS + markup

**Files:**
- Modify: `resources/css/app.css`
- Modify: `resources/js/Pages/Trips/Show.vue` (expenses `<table>` section ~156–193)

**Interfaces:**
- Consumes: existing `expenses` prop rows (`spent_label`, `amount`, `type_label`, `payer`, `comment`, `id`)
- Produces: table with class `expenses-table`; content `td` elements with `data-label`; empty-state `td` with class `expenses-table__empty`

- [x] **Step 1: Add `.expenses-table` rules to `app.css`**

Append after the existing `@theme` block in `resources/css/app.css`:

```css
/* Expenses table: stacked labeled cards below md, normal table from md up */
.expenses-table {
    width: 100%;
    text-align: left;
    font-size: 0.875rem;
    line-height: 1.25rem;
}

@media (width < 48rem) {
    .expenses-table thead {
        display: none;
    }

    .expenses-table tbody {
        display: block;
    }

    .expenses-table tbody tr {
        display: block;
        margin-bottom: 0.75rem;
        border: 1px solid #e7e5e4; /* stone-200 */
        border-radius: 0.5rem;
        background-color: #fff;
        padding: 0.25rem 0;
    }

    .expenses-table tbody tr:last-child {
        margin-bottom: 0;
    }

    .expenses-table tbody td {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 0.75rem;
        border-bottom: 1px solid #f5f5f4; /* stone-100 */
        padding: 0.625rem 0.75rem;
        text-align: right;
    }

    .expenses-table tbody td:last-child {
        border-bottom: none;
    }

    .expenses-table tbody td[data-label]::before {
        content: attr(data-label);
        flex-shrink: 0;
        font-weight: 500;
        color: #78716c; /* stone-500 */
        text-align: left;
    }

    .expenses-table tbody td.expenses-table__empty {
        display: block;
        border-bottom: none;
        padding: 1.5rem 0.75rem;
        text-align: center;
        color: #78716c;
    }

    .expenses-table tbody td.expenses-table__empty::before {
        content: none;
    }
}

@media (width >= 48rem) {
    .expenses-table thead {
        border-bottom: 1px solid #e7e5e4;
        color: #78716c;
    }

    .expenses-table th,
    .expenses-table td {
        padding: 0.5rem 0.5rem;
    }

    .expenses-table tbody tr {
        border-bottom: 1px solid #f5f5f4;
    }

    .expenses-table td.expenses-table__empty {
        padding-top: 1.5rem;
        padding-bottom: 1.5rem;
        text-align: center;
        color: #78716c;
    }
}
```

- [x] **Step 2: Update the expenses table markup in `Show.vue`**

Replace the expenses table wrapper and table (from the `overflow-x-auto` div through `</table>`) with:

```vue
            <div>
                <table class="expenses-table">
                    <thead>
                        <tr>
                            <th class="font-medium">Дата</th>
                            <th class="font-medium">Сумма</th>
                            <th class="font-medium">Тип расхода</th>
                            <th class="font-medium">Плательщик</th>
                            <th class="font-medium">Комментарий</th>
                            <th class="font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="expenses.length === 0">
                            <td colspan="6" class="expenses-table__empty">Расходов пока нет</td>
                        </tr>
                        <tr v-for="expense in expenses" :key="expense.id">
                            <td data-label="Дата" class="whitespace-nowrap text-stone-600">
                                {{ expense.spent_label }}
                            </td>
                            <td data-label="Сумма" class="font-medium text-stone-900">
                                {{ formatMoney(expense.amount) }}
                            </td>
                            <td data-label="Тип расхода" class="text-stone-700">
                                {{ expense.type_label }}
                            </td>
                            <td data-label="Плательщик" class="text-stone-700">
                                {{ expense.payer }}
                            </td>
                            <td data-label="Комментарий" class="text-stone-500">
                                {{ expense.comment || '—' }}
                            </td>
                            <td class="text-right">
                                <button
                                    type="button"
                                    class="text-sm font-medium text-teal-700 hover:text-teal-900"
                                    @click="openEditExpense(expense)"
                                >
                                    Изменить
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
```

Notes:
- Action cell has **no** `data-label` so no `::before` on mobile.
- Removed `overflow-x-auto` / `min-w-full` / per-cell `px-2 py-3` that fought the card layout (padding lives in CSS).

- [x] **Step 3: Build frontend assets**

Run from repo root:

```bash
npm run build
```

Expected: Vite build succeeds with exit code 0.

- [x] **Step 4: Manual verification checklist**

Implemented; please confirm in browser:

1. **Mobile** (DevTools width &lt; 768px): no `thead`; each expense is a bordered block; each field is label left / value right; «Изменить» has no label and is right-aligned; no horizontal scroll; empty trip shows only «Расходов пока нет».
2. **Desktop** (≥ 768px): table with headers as before; columns in one row.
3. Click «Изменить» — expense modal still opens.

- [x] **Step 5: Commit (only if user asked)** — skipped (user did not ask)

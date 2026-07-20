# Mobile expenses table — design

Date: 2026-07-20  
Scope: trip expenses list on `resources/js/Pages/Trips/Show.vue`

## Goal

On small screens, show each expense as a readable block (label + value per field) instead of a horizontally scrolling table. Keep the existing table layout on desktop.

## Approach

CSS-only responsive table (single markup):

- Keep one `<table>` for expenses.
- Below `md` (~768px): hide `thead`; each `tr` becomes a card-like block; each `td` stacks as a full-width row with label from `data-label` via `::before`.
- From `md` up: current table with column headers, unchanged.

No duplicate card markup, no new components.

## Behavior

### Mobile (&lt; md)

- Hide the header row (`Дата`, `Сумма`, `Тип расхода`, `Плательщик`, `Комментарий`).
- Each expense row is one visual block (border / padding consistent with existing stone/teal UI).
- Fields, each on its own line, label left / value right:
  - Дата
  - Сумма
  - Тип расхода
  - Плательщик
  - Комментарий
  - «Изменить»: no `data-label` / no `::before`; button right-aligned in its own row
- Empty state («Расходов пока нет»): single centered message, no field labels.
- No horizontal scroll on the expenses section.

### Desktop (≥ md)

- Unchanged table with visible `thead` and columns in one row.

## Implementation notes

- File: `resources/js/Pages/Trips/Show.vue` (primary). Optional small scoped/utility CSS in `resources/css/app.css` only if Tailwind alone cannot express `td[data-label]::before`.
- Add `data-label="…"` on each content `td`.
- Prefer Tailwind `md:` breakpoints to match the rest of the page (`sm:` already used for settlement grid).
- Remove or neutralize `overflow-x-auto` on mobile so cards are not clipped into a scroll container; keep overflow only if still needed on desktop (likely not).

## Out of scope

- Settlement section / other pages
- Changing expense data model or API
- Desktop table redesign

## Acceptance

1. On a phone-width viewport, expenses appear as stacked blocks with label + value; no column header row; no horizontal scroll.
2. On tablet/desktop (`md+`), table looks as today with headers.
3. «Изменить» still opens the expense edit modal.
4. Empty list still shows the empty message cleanly.

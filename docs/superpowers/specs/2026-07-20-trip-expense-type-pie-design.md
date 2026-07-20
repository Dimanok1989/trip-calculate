# Trip expense-type pie chart — design

Date: 2026-07-20  
Scope: trip detail page `resources/js/Pages/Trips/Show.vue`

## Goal

When a trip has expenses in more than one category, show a pie chart of spend by expense type between the settlement block and the expenses table.

## Approach

Client-side SVG (or equivalent lightweight markup) pie in Vue — no new chart library. Aggregate existing `expenses` props by `type_label`.

## Behavior

### Visibility

- Show the chart section only when the number of distinct `type_label` values among expenses is **≥ 2**.
- Otherwise omit the section entirely (no empty placeholder).

### Grouping

- Group by displayed label (`type_label`), not by base `type` key.
- Custom «Другое» labels are separate sectors.
- Sum `amount` per label; percent = share of total expense amount.
- Sort sectors by amount descending.

### Layout

- New card section between «Расчёт» and «Расходы», matching existing `rounded-xl border … bg-white p-6 shadow-sm` style.
- Title: «По типам расходов».
- Desktop: pie and legend side by side.
- Mobile: pie above, legend below.
- Legend each row: color swatch · type name · formatted sum · percent.
- Static only — no hover tooltips, no click handlers.

### Visual

- Fixed palette aligned with page teal/stone/amber accents; assign colors by sector index.
- Money formatting via the same `formatMoney` helper as the rest of the page.
- Percents: whole numbers when close to integers, otherwise one decimal (e.g. `33%`, `12.5%`).

## Implementation notes

- Primary file: `resources/js/Pages/Trips/Show.vue`.
- Optional small presentational component (e.g. `ExpenseTypePie.vue`) if it keeps `Show.vue` clearer; otherwise inline computed + template is fine.
- Compute breakdown with `computed` from `expenses` prop.
- No backend / API / settlement changes.

## Out of scope

- Chart.js or other chart libraries
- Hover/tooltips/interactivity
- Grouping by base type key only
- Changes to settlement math or expenses table

## Acceptance

1. With expenses in only one `type_label`, the pie section is not rendered.
2. With two or more distinct `type_label`s, a pie section appears between settlement and the expenses table.
3. Sector sizes and legend sums match totals by `type_label`; percents sum to ~100%.
4. Custom «Другое» names appear as separate legend/sector entries.
5. Layout works on mobile and desktop; no new npm dependencies.

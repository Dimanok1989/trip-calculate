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

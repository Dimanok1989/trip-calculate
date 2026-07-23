<script setup>
import AutodorImportModal from '../../Components/AutodorImportModal.vue';
import ExpenseModal from '../../Components/ExpenseModal.vue';
import ExpenseTypePie from '../../Components/ExpenseTypePie.vue';
import MealModal from '../../Components/MealModal.vue';
import TripEditModal from '../../Components/TripEditModal.vue';
import { buildExpenseTypeBreakdown } from '../../utils/expenseTypeBreakdown.js';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    trip: {
        type: Object,
        required: true,
    },
    travelers: {
        type: Array,
        default: () => [],
    },
    expenses: {
        type: Array,
        default: () => [],
    },
    meals: {
        type: Array,
        default: () => [],
    },
    expenseTypes: {
        type: Object,
        default: () => ({}),
    },
    settlement: {
        type: Object,
        required: true,
    },
    mealSettlement: {
        type: Object,
        required: true,
    },
});

const expenseTypeSegments = computed(() => buildExpenseTypeBreakdown(props.expenses));
const page = usePage();

const activeLedgerTab = ref('expenses');
const showExpenseModal = ref(false);
const showMealModal = ref(false);
const showAutodorModal = ref(false);
const editingExpense = ref(null);
const editingMeal = ref(null);
const showTripModal = ref(false);

const avtodorImport = computed(() => page.props.flash?.avtodor_import ?? null);

function openCreateExpense() {
    editingExpense.value = null;
    showExpenseModal.value = true;
}

function openEditExpense(expense) {
    editingExpense.value = expense;
    showExpenseModal.value = true;
}

function closeExpenseModal() {
    showExpenseModal.value = false;
    editingExpense.value = null;
}

function openCreateMeal() {
    editingMeal.value = null;
    showMealModal.value = true;
}

function openEditMeal(meal) {
    editingMeal.value = meal;
    showMealModal.value = true;
}

function closeMealModal() {
    showMealModal.value = false;
    editingMeal.value = null;
}

function formatMoney(value) {
    return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        maximumFractionDigits: 2,
    }).format(Number(value) || 0);
}

function balanceText(balance) {
    if (balance > 0.009) {
        return `ему должны ${formatMoney(balance)}`;
    }

    if (balance < -0.009) {
        return `должен ${formatMoney(Math.abs(balance))}`;
    }

    return 'расчёт сведён';
}

function mealBalanceText(row) {
    if (row.balance > 0.009) {
        return `ему должны ${formatMoney(row.balance)}`;
    }

    if (row.balance < -0.009) {
        return `должен ${formatMoney(Math.abs(row.balance))}`;
    }

    return 'расчёт сведён';
}
</script>

<template>
    <div class="mx-auto max-w-4xl px-4 py-10">
        <div class="mb-6">
            <Link href="/" class="text-sm font-medium text-teal-700 hover:text-teal-900">← Все поездки</Link>
            <div class="mt-3 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-3xl font-semibold tracking-tight text-teal-900">{{ trip.name }}</h1>
                    <p class="mt-2 text-stone-600">
                        Участники:
                        <span class="text-stone-800">{{ travelers.map((t) => t.name).join(', ') }}</span>
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded-lg border border-stone-300 px-3 py-2 text-sm font-medium text-stone-700 hover:bg-stone-50"
                    @click="showTripModal = true"
                >
                    Редактировать поездку
                </button>
            </div>
        </div>

        <section class="mb-8 rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
            <div class="mb-4 flex items-center justify-between gap-2 sm:gap-3">
                <h2 class="min-w-0 text-xl font-medium text-stone-800">Расчёт</h2>
                <button
                    type="button"
                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-teal-700 p-2 text-sm font-medium text-white hover:bg-teal-800 sm:px-4 sm:py-2"
                    title="Добавить расход"
                    aria-label="Добавить расход"
                    @click="openCreateExpense"
                >
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M10 3.75a.75.75 0 0 1 .75.75v4.75H15.5a.75.75 0 0 1 0 1.5h-4.75V15.5a.75.75 0 0 1-1.5 0v-4.75H4.5a.75.75 0 0 1 0-1.5h4.75V4.5A.75.75 0 0 1 10 3.75Z" />
                    </svg>
                    <span class="hidden sm:inline">Добавить расход</span>
                </button>
            </div>

            <div class="mb-4 grid gap-3 sm:grid-cols-2">
                <div class="rounded-lg bg-teal-50 px-4 py-3">
                    <div class="text-sm text-teal-800">Общая сумма</div>
                    <div class="text-2xl font-semibold text-teal-950">{{ formatMoney(settlement.total) }}</div>
                </div>
                <div class="rounded-lg bg-stone-50 px-4 py-3">
                    <div class="text-sm text-stone-600">Доля на человека</div>
                    <div class="text-2xl font-semibold text-stone-900">{{ formatMoney(settlement.share) }}</div>
                </div>
            </div>

            <ul class="mb-4 space-y-2">
                <li
                    v-for="row in settlement.balances"
                    :key="row.traveler_id"
                    class="flex flex-wrap items-baseline justify-between gap-2 border-b border-stone-100 py-2"
                >
                    <span class="font-medium text-stone-800">{{ row.name }}</span>
                    <span class="text-sm text-stone-600">
                        внёс {{ formatMoney(row.paid) }} —
                        <span
                            :class="{
                                'text-emerald-700': row.balance > 0,
                                'text-amber-700': row.balance < 0,
                            }"
                        >
                            {{ balanceText(row.balance) }}
                        </span>
                    </span>
                </li>
            </ul>

            <div v-if="settlement.settlements.length">
                <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-stone-500">Кто кому должен</h3>
                <ul class="space-y-1">
                    <li
                        v-for="(item, index) in settlement.settlements"
                        :key="index"
                        class="rounded-lg bg-amber-50 px-3 py-2 text-stone-800"
                    >
                        <strong>{{ item.from }}</strong> → <strong>{{ item.to }}</strong>:
                        {{ formatMoney(item.amount) }}
                    </li>
                </ul>
            </div>
            <p v-else-if="settlement.total > 0" class="text-sm text-stone-500">Все расчёты сведены.</p>
            <p v-else class="text-sm text-stone-500">Добавьте расходы, чтобы увидеть расчёт.</p>
        </section>

        <section
            v-if="expenseTypeSegments.length >= 2"
            class="mb-8 rounded-xl border border-stone-200 bg-white p-6 shadow-sm"
        >
            <h2 class="mb-4 text-xl font-medium text-stone-800">По типам расходов</h2>
            <ExpenseTypePie :segments="expenseTypeSegments" :format-money="formatMoney" />
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex gap-1 rounded-lg bg-stone-100 p-1">
                    <button
                        type="button"
                        class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                        :class="
                            activeLedgerTab === 'expenses'
                                ? 'bg-white text-teal-900 shadow-sm'
                                : 'text-stone-600 hover:text-stone-900'
                        "
                        @click="activeLedgerTab = 'expenses'"
                    >
                        Расходы
                    </button>
                    <button
                        type="button"
                        class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                        :class="
                            activeLedgerTab === 'meals'
                                ? 'bg-white text-teal-900 shadow-sm'
                                : 'text-stone-600 hover:text-stone-900'
                        "
                        @click="activeLedgerTab = 'meals'"
                    >
                        Питание
                    </button>
                </div>

                <div v-if="activeLedgerTab === 'expenses'" class="flex shrink-0 flex-nowrap items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-stone-300 p-2 text-sm font-medium text-stone-700 hover:bg-stone-50 sm:px-4 sm:py-2"
                        title="Импорт Автодор"
                        aria-label="Импорт Автодор"
                        @click="showAutodorModal = true"
                    >
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M9.25 3.75a.75.75 0 0 1 1.5 0v7.19l2.22-2.22a.75.75 0 1 1 1.06 1.06l-3.5 3.5a.75.75 0 0 1-1.06 0l-3.5-3.5a.75.75 0 1 1 1.06-1.06l2.22 2.22V3.75Z" />
                            <path d="M3.5 12.75a.75.75 0 0 1 .75.75v1.5A1.5 1.5 0 0 0 5.75 16.5h8.5a1.5 1.5 0 0 0 1.5-1.5v-1.5a.75.75 0 0 1 1.5 0v1.5a3 3 0 0 1-3 3h-8.5a3 3 0 0 1-3-3v-1.5a.75.75 0 0 1 .75-.75Z" />
                        </svg>
                        <span class="hidden sm:inline">Импорт Автодор</span>
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-teal-700 p-2 text-sm font-medium text-white hover:bg-teal-800 sm:px-4 sm:py-2"
                        title="Добавить расход"
                        aria-label="Добавить расход"
                        @click="openCreateExpense"
                    >
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M10 3.75a.75.75 0 0 1 .75.75v4.75H15.5a.75.75 0 0 1 0 1.5h-4.75V15.5a.75.75 0 0 1-1.5 0v-4.75H4.5a.75.75 0 0 1 0-1.5h4.75V4.5A.75.75 0 0 1 10 3.75Z" />
                        </svg>
                        <span class="hidden sm:inline">Добавить расход</span>
                    </button>
                </div>

                <div v-else class="flex shrink-0 flex-nowrap items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-teal-700 p-2 text-sm font-medium text-white hover:bg-teal-800 sm:px-4 sm:py-2"
                        title="Добавить питание"
                        aria-label="Добавить питание"
                        @click="openCreateMeal"
                    >
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M10 3.75a.75.75 0 0 1 .75.75v4.75H15.5a.75.75 0 0 1 0 1.5h-4.75V15.5a.75.75 0 0 1-1.5 0v-4.75H4.5a.75.75 0 0 1 0-1.5h4.75V4.5A.75.75 0 0 1 10 3.75Z" />
                        </svg>
                        <span class="hidden sm:inline">Добавить питание</span>
                    </button>
                </div>
            </div>

            <template v-if="activeLedgerTab === 'expenses'">
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
            </template>

            <template v-else>
                <div class="mb-4 space-y-3">
                    <div
                        v-if="mealSettlement.incomplete_count > 0"
                        class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900"
                    >
                        Есть {{ mealSettlement.incomplete_count }}
                        {{ mealSettlement.incomplete_count === 1 ? 'поход без позиций' : 'походов без позиций' }}
                        — они не участвуют в расчёте долгов.
                    </div>

                    <div v-if="mealSettlement.settlements.length" class="space-y-1">
                        <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-stone-500">
                            Кто кому должен
                        </h3>
                        <ul class="space-y-1">
                            <li
                                v-for="(item, index) in mealSettlement.settlements"
                                :key="index"
                                class="rounded-lg bg-amber-50 px-3 py-2 text-stone-800"
                            >
                                <strong>{{ item.from }}</strong> → <strong>{{ item.to }}</strong>:
                                {{ formatMoney(item.amount) }}
                            </li>
                        </ul>
                    </div>
                    <p v-else-if="mealSettlement.total > 0" class="text-sm text-stone-500">
                        Все расчёты по питанию сведены.
                    </p>
                    <p v-else class="text-sm text-stone-500">
                        Добавьте питание с позициями, чтобы увидеть расчёт.
                    </p>

                    <ul v-if="mealSettlement.total > 0" class="space-y-2 border-t border-stone-100 pt-3">
                        <li
                            v-for="row in mealSettlement.balances"
                            :key="row.traveler_id"
                            class="flex flex-wrap items-baseline justify-between gap-2 text-sm"
                        >
                            <span class="font-medium text-stone-800">{{ row.name }}</span>
                            <span class="text-stone-600">
                                оплатил {{ formatMoney(row.paid) }}, съел {{ formatMoney(row.consumed) }} —
                                <span
                                    :class="{
                                        'text-emerald-700': row.balance > 0,
                                        'text-amber-700': row.balance < 0,
                                    }"
                                >
                                    {{ mealBalanceText(row) }}
                                </span>
                            </span>
                        </li>
                    </ul>
                </div>

                <div>
                    <table class="expenses-table">
                        <thead>
                            <tr>
                                <th class="font-medium">Дата</th>
                                <th class="font-medium">Наименование</th>
                                <th class="font-medium">Сумма</th>
                                <th class="font-medium">Плательщик</th>
                                <th class="font-medium">Статус</th>
                                <th class="font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="meals.length === 0">
                                <td colspan="6" class="expenses-table__empty">Записей о питании пока нет</td>
                            </tr>
                            <tr v-for="meal in meals" :key="meal.id">
                                <td data-label="Дата" class="whitespace-nowrap text-stone-600">
                                    {{ meal.spent_label }}
                                </td>
                                <td data-label="Наименование" class="text-stone-800">
                                    <span>{{ meal.title }}</span>
                                    <span
                                        v-if="meal.photos?.length"
                                        class="ml-1 text-xs text-stone-400"
                                    >
                                        · {{ meal.photos.length }} фото
                                    </span>
                                </td>
                                <td data-label="Сумма" class="font-medium text-stone-900">
                                    {{ formatMoney(meal.amount) }}
                                </td>
                                <td data-label="Плательщик" class="text-stone-700">
                                    {{ meal.payer }}
                                </td>
                                <td data-label="Статус" class="text-stone-500">
                                    <span v-if="meal.items_complete" class="text-emerald-700">позиции заполнены</span>
                                    <span v-else class="text-amber-700">без позиций</span>
                                </td>
                                <td class="text-right">
                                    <button
                                        type="button"
                                        class="text-sm font-medium text-teal-700 hover:text-teal-900"
                                        @click="openEditMeal(meal)"
                                    >
                                        Изменить
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </section>

        <AutodorImportModal
            :show="showAutodorModal"
            :trip-id="trip.id"
            :travelers="travelers"
            @close="showAutodorModal = false"
        />

        <ExpenseModal
            :show="showExpenseModal"
            :trip-id="trip.id"
            :travelers="travelers"
            :expense-types="expenseTypes"
            :expense="editingExpense"
            @close="closeExpenseModal"
        />

        <MealModal
            :show="showMealModal"
            :trip-id="trip.id"
            :travelers="travelers"
            :meal="editingMeal"
            @close="closeMealModal"
        />

        <TripEditModal
            :show="showTripModal"
            :trip="trip"
            :travelers="travelers"
            @close="showTripModal = false"
        />
    </div>
</template>

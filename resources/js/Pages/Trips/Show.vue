<script setup>
import ExpenseModal from '../../Components/ExpenseModal.vue';
import TripEditModal from '../../Components/TripEditModal.vue';
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
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
    expenseTypes: {
        type: Object,
        default: () => ({}),
    },
    settlement: {
        type: Object,
        required: true,
    },
});

const showExpenseModal = ref(false);
const editingExpense = ref(null);
const showTripModal = ref(false);

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
            <h2 class="mb-4 text-xl font-medium text-stone-800">Расчёт</h2>

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

        <section class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="text-xl font-medium text-stone-800">Расходы</h2>
                <button
                    type="button"
                    class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800"
                    @click="openCreateExpense"
                >
                    Добавить расход
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-stone-200 text-stone-500">
                        <tr>
                            <th class="px-2 py-2 font-medium">Дата</th>
                            <th class="px-2 py-2 font-medium">Сумма</th>
                            <th class="px-2 py-2 font-medium">Тип расхода</th>
                            <th class="px-2 py-2 font-medium">Плательщик</th>
                            <th class="px-2 py-2 font-medium">Комментарий</th>
                            <th class="px-2 py-2 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="expenses.length === 0">
                            <td colspan="6" class="px-2 py-6 text-center text-stone-500">Расходов пока нет</td>
                        </tr>
                        <tr
                            v-for="expense in expenses"
                            :key="expense.id"
                            class="border-b border-stone-100"
                        >
                            <td class="whitespace-nowrap px-2 py-3 text-stone-600">{{ expense.spent_label }}</td>
                            <td class="px-2 py-3 font-medium text-stone-900">{{ formatMoney(expense.amount) }}</td>
                            <td class="px-2 py-3 text-stone-700">{{ expense.type_label }}</td>
                            <td class="px-2 py-3 text-stone-700">{{ expense.payer }}</td>
                            <td class="px-2 py-3 text-stone-500">{{ expense.comment || '—' }}</td>
                            <td class="px-2 py-3 text-right">
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
        </section>

        <ExpenseModal
            :show="showExpenseModal"
            :trip-id="trip.id"
            :travelers="travelers"
            :expense-types="expenseTypes"
            :expense="editingExpense"
            @close="closeExpenseModal"
        />

        <TripEditModal
            :show="showTripModal"
            :trip="trip"
            :travelers="travelers"
            @close="showTripModal = false"
        />
    </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    tripId: {
        type: [Number, String],
        required: true,
    },
    travelers: {
        type: Array,
        default: () => [],
    },
    expenseTypes: {
        type: Object,
        default: () => ({}),
    },
    expense: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close']);

const form = useForm({
    amount: '',
    type: 'fuel',
    type_custom: '',
    traveler_id: '',
    comment: '',
    spent_date: '',
    spent_time: '',
});

const isOther = computed(() => form.type === 'other');
const isEditing = computed(() => Boolean(props.expense?.id));

function todayDate() {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${now.getFullYear()}-${month}-${day}`;
}

function currentTime() {
    const now = new Date();

    return `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
}

function fillForm() {
    form.clearErrors();

    if (props.expense) {
        form.amount = props.expense.amount;
        form.type = props.expense.type;
        form.type_custom = props.expense.type_custom || '';
        form.traveler_id = props.expense.traveler_id;
        form.comment = props.expense.comment || '';
        form.spent_date = props.expense.spent_date || todayDate();
        form.spent_time = props.expense.has_time ? props.expense.spent_time || '' : '';
        return;
    }

    form.reset();
    form.type = 'fuel';
    form.traveler_id = props.travelers[0]?.id ?? '';
    form.spent_date = todayDate();
    form.spent_time = currentTime();
}

watch(
    () => [props.show, props.expense],
    ([open]) => {
        if (open) {
            fillForm();
        }
    },
);

function close() {
    emit('close');
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => close(),
    };

    if (isEditing.value) {
        form.put(`/trips/${props.tripId}/expenses/${props.expense.id}`, options);
        return;
    }

    form.post(`/trips/${props.tripId}/expenses`, options);
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
                <h3 class="text-lg font-semibold text-stone-800">
                    {{ isEditing ? 'Редактировать расход' : 'Добавить расход' }}
                </h3>
                <button type="button" class="text-stone-400 hover:text-stone-700" @click="close">×</button>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium text-stone-700" for="amount">Сумма</label>
                    <input
                        id="amount"
                        v-model="form.amount"
                        type="number"
                        min="0.01"
                        step="0.01"
                        class="w-full rounded-lg border border-stone-300 px-3 py-2 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                        required
                    />
                    <p v-if="form.errors.amount" class="mt-1 text-sm text-red-600">{{ form.errors.amount }}</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-stone-700" for="spent_date">Дата</label>
                        <input
                            id="spent_date"
                            v-model="form.spent_date"
                            type="date"
                            class="w-full rounded-lg border border-stone-300 px-3 py-2 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                            required
                        />
                        <p v-if="form.errors.spent_date" class="mt-1 text-sm text-red-600">{{ form.errors.spent_date }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-stone-700" for="spent_time">
                            Время
                            <span class="font-normal text-stone-400">(необяз.)</span>
                        </label>
                        <input
                            id="spent_time"
                            v-model="form.spent_time"
                            type="time"
                            class="w-full rounded-lg border border-stone-300 px-3 py-2 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                        />
                        <p v-if="form.errors.spent_time" class="mt-1 text-sm text-red-600">{{ form.errors.spent_time }}</p>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-stone-700" for="type">Тип расхода</label>
                    <select
                        id="type"
                        v-model="form.type"
                        class="w-full rounded-lg border border-stone-300 px-3 py-2 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                    >
                        <option v-for="(label, value) in expenseTypes" :key="value" :value="value">
                            {{ label }}
                        </option>
                    </select>
                    <p v-if="form.errors.type" class="mt-1 text-sm text-red-600">{{ form.errors.type }}</p>
                </div>

                <div v-if="isOther">
                    <label class="mb-1 block text-sm font-medium text-stone-700" for="type_custom">Своё название</label>
                    <input
                        id="type_custom"
                        v-model="form.type_custom"
                        type="text"
                        class="w-full rounded-lg border border-stone-300 px-3 py-2 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                        placeholder="Например, сувениры"
                    />
                    <p v-if="form.errors.type_custom" class="mt-1 text-sm text-red-600">{{ form.errors.type_custom }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-stone-700" for="traveler_id">Плательщик</label>
                    <select
                        id="traveler_id"
                        v-model="form.traveler_id"
                        class="w-full rounded-lg border border-stone-300 px-3 py-2 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                        required
                    >
                        <option disabled value="">Выберите участника</option>
                        <option v-for="traveler in travelers" :key="traveler.id" :value="traveler.id">
                            {{ traveler.name }}
                        </option>
                    </select>
                    <p v-if="form.errors.traveler_id" class="mt-1 text-sm text-red-600">{{ form.errors.traveler_id }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-stone-700" for="comment">Комментарий</label>
                    <textarea
                        id="comment"
                        v-model="form.comment"
                        rows="2"
                        class="w-full rounded-lg border border-stone-300 px-3 py-2 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                        placeholder="Необязательно"
                    />
                    <p v-if="form.errors.comment" class="mt-1 text-sm text-red-600">{{ form.errors.comment }}</p>
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
                        Сохранить
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

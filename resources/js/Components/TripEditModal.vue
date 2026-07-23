<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    trip: {
        type: Object,
        required: true,
    },
    travelers: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close']);

const form = useForm({
    name: '',
    travelers: [],
});

const canSubmit = computed(
    () =>
        form.name.trim().length > 0 &&
        form.travelers.length >= 2 &&
        form.travelers.every((row) => row.name.trim().length > 0),
);

watch(
    () => props.show,
    (open) => {
        if (!open) {
            return;
        }

        form.clearErrors();
        form.name = props.trip.name;
        form.travelers = props.travelers.map((traveler) => ({
            id: traveler.id,
            name: traveler.name,
            expenses_count: traveler.expenses_count ?? 0,
            meals_count: traveler.meals_count ?? 0,
        }));
    },
);

function addTraveler() {
    form.travelers.push({
        id: null,
        name: '',
        expenses_count: 0,
        meals_count: 0,
    });
}

function travelerIsLocked(row) {
    return (row.expenses_count ?? 0) > 0 || (row.meals_count ?? 0) > 0;
}

function removeTraveler(index) {
    const row = form.travelers[index];

    if (form.travelers.length <= 2) {
        return;
    }

    if (travelerIsLocked(row)) {
        return;
    }

    form.travelers.splice(index, 1);
}

function close() {
    emit('close');
}

function submit() {
    form
        .transform((data) => ({
            name: data.name,
            travelers: data.travelers.map((row) => ({
                id: row.id || null,
                name: row.name,
            })),
        }))
        .put(`/trips/${props.trip.id}`, {
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
                <h3 class="text-lg font-semibold text-stone-800">Редактировать поездку</h3>
                <button type="button" class="text-stone-400 hover:text-stone-700" @click="close">×</button>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium text-stone-700" for="trip-edit-name">Название</label>
                    <input
                        id="trip-edit-name"
                        v-model="form.name"
                        type="text"
                        class="w-full rounded-lg border border-stone-300 px-3 py-2 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                        required
                    />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <label class="text-sm font-medium text-stone-700">Путешественники</label>
                        <button
                            type="button"
                            class="text-sm font-medium text-teal-700 hover:text-teal-900"
                            @click="addTraveler"
                        >
                            + Добавить участника
                        </button>
                    </div>

                    <div class="space-y-2">
                        <div
                            v-for="(traveler, index) in form.travelers"
                            :key="traveler.id ?? `new-${index}`"
                            class="flex gap-2"
                        >
                            <input
                                v-model="form.travelers[index].name"
                                type="text"
                                class="w-full rounded-lg border border-stone-300 px-3 py-2 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                                :placeholder="`Участник ${index + 1}`"
                            />
                            <button
                                v-if="form.travelers.length > 2 && !travelerIsLocked(traveler)"
                                type="button"
                                class="rounded-lg border border-stone-300 px-3 text-stone-500 hover:bg-stone-50"
                                title="Удалить участника"
                                @click="removeTraveler(index)"
                            >
                                ×
                            </button>
                            <span
                                v-else-if="travelerIsLocked(traveler)"
                                class="flex items-center whitespace-nowrap px-1 text-xs text-stone-400"
                                title="Есть расходы или питание — удалить нельзя"
                            >
                                есть траты
                            </span>
                        </div>
                    </div>
                    <p v-if="form.errors.travelers" class="mt-1 text-sm text-red-600">{{ form.errors.travelers }}</p>
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
                        :disabled="form.processing || !canSubmit"
                    >
                        Сохранить
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

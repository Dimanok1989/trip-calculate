<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    trips: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    name: '',
    travelers: ['', ''],
});

const canSubmit = computed(
    () =>
        form.name.trim().length > 0 &&
        form.travelers.length >= 2 &&
        form.travelers.every((name) => name.trim().length > 0),
);

function addTraveler() {
    form.travelers.push('');
}

function removeTraveler(index) {
    if (form.travelers.length <= 2) {
        return;
    }

    form.travelers.splice(index, 1);
}

function submit() {
    form.post('/trips', {
        preserveScroll: true,
    });
}
</script>

<template>
    <div class="mx-auto max-w-3xl px-4 py-10">
        <header class="mb-10">
            <h1 class="text-3xl font-semibold tracking-tight text-teal-900">Калькулятор поездок</h1>
            <p class="mt-2 text-stone-600">Создайте поездку или откройте уже существующую.</p>
        </header>

        <section
            v-if="trips.length > 0"
            class="mb-10 rounded-xl border border-stone-200 bg-white p-6 shadow-sm"
        >
            <h2 class="mb-4 text-xl font-medium text-stone-800">Поездки</h2>

            <ul class="divide-y divide-stone-100">
                <li v-for="trip in trips" :key="trip.id" class="py-3">
                    <Link
                        :href="`/trips/${trip.id}`"
                        class="flex items-center justify-between gap-4 text-teal-800 hover:text-teal-950"
                    >
                        <span class="font-medium">{{ trip.name }}</span>
                        <span class="text-sm text-stone-500">{{ trip.travelers_count }} уч.</span>
                    </Link>
                </li>
            </ul>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-xl font-medium text-stone-800">Новая поездка</h2>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium text-stone-700" for="trip-name">Название</label>
                    <input
                        id="trip-name"
                        v-model="form.name"
                        type="text"
                        class="w-full rounded-lg border border-stone-300 px-3 py-2 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                        placeholder="Например, Сочи 2026"
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
                            :key="index"
                            class="flex gap-2"
                        >
                            <input
                                v-model="form.travelers[index]"
                                type="text"
                                class="w-full rounded-lg border border-stone-300 px-3 py-2 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                                :placeholder="`Участник ${index + 1}`"
                            />
                            <button
                                v-if="form.travelers.length > 2"
                                type="button"
                                class="rounded-lg border border-stone-300 px-3 text-stone-500 hover:bg-stone-50"
                                @click="removeTraveler(index)"
                            >
                                ×
                            </button>
                        </div>
                    </div>
                    <p v-if="form.errors.travelers" class="mt-1 text-sm text-red-600">{{ form.errors.travelers }}</p>
                    <p v-if="form.errors['travelers.0']" class="mt-1 text-sm text-red-600">{{ form.errors['travelers.0'] }}</p>
                </div>

                <button
                    type="submit"
                    class="rounded-lg bg-teal-700 px-4 py-2 font-medium text-white hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="form.processing || !canSubmit"
                >
                    Создать поездку
                </button>
            </form>
        </section>
    </div>
</template>

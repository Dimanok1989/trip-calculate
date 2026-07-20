<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    tripId: { type: [Number, String], required: true },
    travelers: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const form = useForm({
    traveler_id: '',
    file: null,
});

const clientError = ref('');
const fileInputRef = ref(null);

function clearFileInput() {
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
}

watch(
    () => props.show,
    (open) => {
        if (open) {
            form.defaults({
                traveler_id: props.travelers[0]?.id ?? '',
                file: null,
            });
            form.reset();
            form.clearErrors();
            clientError.value = '';
            clearFileInput();
            return;
        }
        form.reset();
        clientError.value = '';
        clearFileInput();
    },
);

function onFileChange(event) {
    const file = event.target.files?.[0] ?? null;
    clientError.value = '';
    if (file && !file.name.toLowerCase().endsWith('.csv')) {
        clientError.value = 'Принимается только файл в формате CSV.';
        form.file = null;
        event.target.value = '';
        return;
    }
    form.file = file;
}

function close() {
    form.reset();
    clientError.value = '';
    clearFileInput();
    emit('close');
}

function submit() {
    if (!form.file) {
        clientError.value = 'Выберите CSV-файл экспорта Автодора.';
        return;
    }
    if (!String(form.file.name).toLowerCase().endsWith('.csv')) {
        clientError.value = 'Принимается только файл в формате CSV.';
        return;
    }

    form.post(`/trips/${props.tripId}/expenses/import-avtodor`, {
        forceFormData: true,
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
                <h3 class="text-lg font-semibold text-stone-800">Импорт Автодор</h3>
                <button type="button" class="text-stone-400 hover:text-stone-700" @click="close">×</button>
            </div>

            <p class="mb-4 text-sm text-stone-600">
                Загрузите CSV-файл, сделанный экспортом на сайте Автодора. Другие форматы не принимаются.
            </p>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium text-stone-700" for="avtodor_traveler_id">
                        Плательщик
                    </label>
                    <select
                        id="avtodor_traveler_id"
                        v-model="form.traveler_id"
                        class="w-full rounded-lg border border-stone-300 px-3 py-2 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                        required
                    >
                        <option disabled value="">Выберите участника</option>
                        <option v-for="traveler in travelers" :key="traveler.id" :value="traveler.id">
                            {{ traveler.name }}
                        </option>
                    </select>
                    <p v-if="form.errors.traveler_id" class="mt-1 text-sm text-red-600">
                        {{ form.errors.traveler_id }}
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-stone-700" for="avtodor_file">CSV-файл</label>
                    <input
                        ref="fileInputRef"
                        id="avtodor_file"
                        type="file"
                        accept=".csv,text/csv"
                        class="block w-full text-sm text-stone-700"
                        @change="onFileChange"
                    />
                    <p v-if="clientError" class="mt-1 text-sm text-red-600">{{ clientError }}</p>
                    <p v-if="form.errors.file" class="mt-1 text-sm text-red-600">{{ form.errors.file }}</p>
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
                        Импортировать
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

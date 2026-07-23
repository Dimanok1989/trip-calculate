<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

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
    meal: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close']);

const form = useForm({
    title: '',
    amount: '',
    traveler_id: '',
    spent_date: '',
    spent_time: '',
    items: [],
    photos: [],
    remove_photo_ids: [],
});

const existingPhotos = ref([]);
const photoInputRef = ref(null);
const isEditing = computed(() => Boolean(props.meal?.id));
const deleting = ref(false);

const itemsTotal = computed(() =>
    form.items.reduce((sum, item) => sum + (Number(item.amount) || 0), 0),
);

const remaining = computed(() => {
    const amount = Number(form.amount) || 0;

    return Math.round((amount - itemsTotal.value) * 100) / 100;
});

const itemsValid = computed(() => {
    if (form.items.length === 0) {
        return true;
    }

    return Math.abs(remaining.value) < 0.009;
});

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

function emptyItem() {
    return {
        name: '',
        amount: '',
        traveler_id: props.travelers[0]?.id ?? '',
    };
}

function emptyForm() {
    form.defaults({
        title: '',
        amount: '',
        traveler_id: '',
        spent_date: '',
        spent_time: '',
        items: [],
        photos: [],
        remove_photo_ids: [],
    });
    form.reset();
    form.clearErrors();
    existingPhotos.value = [];
    if (photoInputRef.value) {
        photoInputRef.value.value = '';
    }
}

function fillForm() {
    emptyForm();

    if (props.meal) {
        form.title = props.meal.title || '';
        form.amount = props.meal.amount;
        form.traveler_id = props.meal.traveler_id;
        form.spent_date = props.meal.spent_date || todayDate();
        form.spent_time = props.meal.has_time ? props.meal.spent_time || '' : '';
        form.items = (props.meal.items || []).map((item) => ({
            name: item.name,
            amount: item.amount,
            traveler_id: item.traveler_id,
        }));
        existingPhotos.value = [...(props.meal.photos || [])];
        return;
    }

    form.traveler_id = props.travelers[0]?.id ?? '';
    form.spent_date = todayDate();
    form.spent_time = currentTime();
}

watch(
    () => [props.show, props.meal],
    ([open]) => {
        if (open) {
            fillForm();
            return;
        }

        emptyForm();
    },
);

function close() {
    emptyForm();
    emit('close');
}

function addItem() {
    form.items.push(emptyItem());
}

function removeItem(index) {
    form.items.splice(index, 1);
}

function openPhotoPicker() {
    photoInputRef.value?.click();
}

function onPhotosSelected(event) {
    const files = Array.from(event.target.files || []);
    form.photos = [...form.photos, ...files];
    event.target.value = '';
}

function removeNewPhoto(index) {
    form.photos.splice(index, 1);
}

function removeExistingPhoto(photo) {
    form.remove_photo_ids.push(photo.id);
    existingPhotos.value = existingPhotos.value.filter((p) => p.id !== photo.id);
}

function submit() {
    if (!itemsValid.value) {
        return;
    }

    const options = {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            emptyForm();
            close();
        },
        onFinish: () => {
            form.transform((data) => data);
        },
    };

    // PHP не парсит multipart у PUT — шлём POST с method spoofing
    if (isEditing.value) {
        form
            .transform((data) => {
                const payload = {
                    ...data,
                    _method: 'put',
                };

                if (!payload.photos?.length) {
                    delete payload.photos;
                }

                return payload;
            })
            .post(`/trips/${props.tripId}/meals/${props.meal.id}`, options);
        return;
    }

    form
        .transform((data) => {
            if (!data.photos?.length) {
                const { photos, ...rest } = data;

                return rest;
            }

            return data;
        })
        .post(`/trips/${props.tripId}/meals`, options);
}

function destroyMeal() {
    if (!props.meal?.id || deleting.value) {
        return;
    }

    if (!window.confirm('Удалить запись питания?')) {
        return;
    }

    deleting.value = true;
    router.delete(`/trips/${props.tripId}/meals/${props.meal.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
        },
        onSuccess: () => {
            emptyForm();
            close();
        },
    });
}

function formatMoney(value) {
    return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        maximumFractionDigits: 2,
    }).format(Number(value) || 0);
}
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/50 p-4"
        @click.self="close"
    >
        <div
            class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl"
            role="dialog"
            aria-modal="true"
        >
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-stone-800">
                    {{ isEditing ? 'Редактировать питание' : 'Добавить питание' }}
                </h3>
                <button type="button" class="text-stone-400 hover:text-stone-700" @click="close">×</button>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium text-stone-700" for="meal_title">Название</label>
                    <input
                        id="meal_title"
                        v-model="form.title"
                        type="text"
                        class="w-full rounded-lg border border-stone-300 px-3 py-2 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                        placeholder="Поход в кафе"
                        required
                    />
                    <p v-if="form.errors.title" class="mt-1 text-sm text-red-600">{{ form.errors.title }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-stone-700" for="meal_amount">Общая сумма</label>
                    <input
                        id="meal_amount"
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
                        <label class="mb-1 block text-sm font-medium text-stone-700" for="meal_spent_date">Дата</label>
                        <input
                            id="meal_spent_date"
                            v-model="form.spent_date"
                            type="date"
                            class="w-full rounded-lg border border-stone-300 px-3 py-2 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                            required
                        />
                        <p v-if="form.errors.spent_date" class="mt-1 text-sm text-red-600">{{ form.errors.spent_date }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-stone-700" for="meal_spent_time">
                            Время
                            <span class="font-normal text-stone-400">(необяз.)</span>
                        </label>
                        <input
                            id="meal_spent_time"
                            v-model="form.spent_time"
                            type="time"
                            class="w-full rounded-lg border border-stone-300 px-3 py-2 outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                        />
                        <p v-if="form.errors.spent_time" class="mt-1 text-sm text-red-600">{{ form.errors.spent_time }}</p>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-stone-700" for="meal_traveler_id">Кто платил</label>
                    <select
                        id="meal_traveler_id"
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
                    <label class="mb-1 block text-sm font-medium text-stone-700">Фото</label>
                    <input
                        ref="photoInputRef"
                        id="meal_photos"
                        type="file"
                        accept="image/*"
                        multiple
                        class="sr-only"
                        @change="onPhotosSelected"
                    />
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 hover:bg-stone-50"
                        @click="openPhotoPicker"
                    >
                        <svg class="h-5 w-5 shrink-0 text-stone-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M4.5 3.5A2.5 2.5 0 0 0 2 6v8a2.5 2.5 0 0 0 2.5 2.5h11A2.5 2.5 0 0 0 18 14V6a2.5 2.5 0 0 0-2.5-2.5h-11ZM10 13a3 3 0 1 1 0-6 3 3 0 0 1 0 6Zm5.25-6.75a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                        </svg>
                        Выбрать фото
                    </button>
                    <p v-if="form.errors.photos" class="mt-1 text-sm text-red-600">{{ form.errors.photos }}</p>

                    <div v-if="existingPhotos.length || form.photos.length" class="mt-3 flex flex-wrap gap-2">
                        <div
                            v-for="photo in existingPhotos"
                            :key="photo.id"
                            class="relative h-16 w-16 overflow-hidden rounded-lg border border-stone-200"
                        >
                            <img :src="photo.url" alt="" class="h-full w-full object-cover" />
                            <button
                                type="button"
                                class="absolute right-0.5 top-0.5 rounded bg-stone-900/70 px-1 text-xs text-white"
                                @click="removeExistingPhoto(photo)"
                            >
                                ×
                            </button>
                        </div>
                        <div
                            v-for="(file, index) in form.photos"
                            :key="`new-${index}`"
                            class="relative flex h-16 w-16 items-center justify-center overflow-hidden rounded-lg border border-dashed border-stone-300 bg-stone-50 text-center text-[10px] text-stone-500"
                        >
                            <span class="px-1 break-all">{{ file.name }}</span>
                            <button
                                type="button"
                                class="absolute right-0.5 top-0.5 rounded bg-stone-900/70 px-1 text-xs text-white"
                                @click="removeNewPhoto(index)"
                            >
                                ×
                            </button>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <label class="text-sm font-medium text-stone-700">Позиции</label>
                        <button
                            type="button"
                            class="text-sm font-medium text-teal-700 hover:text-teal-900"
                            @click="addItem"
                        >
                            + Добавить
                        </button>
                    </div>
                    <p class="mb-2 text-xs text-stone-500">
                        Можно сохранить без позиций и заполнить позже. Если позиции есть — сумма должна совпасть с общей.
                    </p>

                    <div v-if="form.items.length" class="space-y-3">
                        <div
                            v-for="(item, index) in form.items"
                            :key="index"
                            class="rounded-lg border border-stone-200 p-3 space-y-2"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <input
                                    v-model="item.name"
                                    type="text"
                                    placeholder="Название"
                                    class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                                    required
                                />
                                <button
                                    type="button"
                                    class="shrink-0 text-sm text-stone-400 hover:text-red-600"
                                    @click="removeItem(index)"
                                >
                                    Удалить
                                </button>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <input
                                    v-model="item.amount"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    placeholder="Сумма"
                                    class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                                    required
                                />
                                <select
                                    v-model="item.traveler_id"
                                    class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100"
                                    required
                                >
                                    <option
                                        v-for="traveler in travelers"
                                        :key="traveler.id"
                                        :value="traveler.id"
                                    >
                                        {{ traveler.name }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="text-sm font-medium text-teal-700 hover:text-teal-900"
                            @click="addItem"
                        >
                            + Добавить
                        </button>
                    </div>

                    <p
                        v-if="form.items.length"
                        class="mt-2 text-sm"
                        :class="itemsValid ? 'text-stone-600' : 'text-amber-700'"
                    >
                        <template v-if="itemsValid">Позиции распределены полностью.</template>
                        <template v-else-if="remaining > 0">
                            Осталось распределить {{ formatMoney(remaining) }}
                        </template>
                        <template v-else>
                            Перераспределено {{ formatMoney(remaining) }}
                        </template>
                    </p>
                    <p v-if="form.errors.items" class="mt-1 text-sm text-red-600">{{ form.errors.items }}</p>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2 pt-2">
                    <button
                        v-if="isEditing"
                        type="button"
                        class="rounded-lg border border-red-200 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 disabled:opacity-50"
                        :disabled="deleting || form.processing"
                        @click="destroyMeal"
                    >
                        Удалить
                    </button>
                    <div v-else></div>
                    <div class="flex gap-2">
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
                            :disabled="form.processing || !itemsValid"
                        >
                            Сохранить
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>

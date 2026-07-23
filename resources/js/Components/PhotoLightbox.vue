<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    photos: {
        type: Array,
        default: () => [],
    },
    startIndex: {
        type: Number,
        default: 0,
    },
});

const emit = defineEmits(['close']);

const index = ref(0);
const isPortrait = ref(false);

const current = computed(() => props.photos[index.value] ?? null);
const hasMany = computed(() => props.photos.length > 1);

watch(
    () => [props.show, props.startIndex, props.photos],
    ([open]) => {
        if (open) {
            const max = Math.max(props.photos.length - 1, 0);
            index.value = Math.min(Math.max(props.startIndex, 0), max);
            isPortrait.value = false;
        }
    },
);

function close() {
    emit('close');
}

function prev() {
    if (!hasMany.value) {
        return;
    }

    index.value = (index.value - 1 + props.photos.length) % props.photos.length;
    isPortrait.value = false;
}

function next() {
    if (!hasMany.value) {
        return;
    }

    index.value = (index.value + 1) % props.photos.length;
    isPortrait.value = false;
}

function onImageLoad(event) {
    const img = event.target;
    isPortrait.value = img.naturalHeight > img.naturalWidth;
}

function onKeydown(event) {
    if (!props.show) {
        return;
    }

    if (event.key === 'Escape') {
        close();
    } else if (event.key === 'ArrowLeft') {
        prev();
    } else if (event.key === 'ArrowRight') {
        next();
    }
}

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div
        v-if="show && current"
        class="fixed inset-0 z-[60] flex items-center justify-center bg-stone-950/80 p-4 sm:p-8"
        @click.self="close"
    >
        <button
            type="button"
            class="absolute right-3 top-3 z-10 flex h-10 w-10 items-center justify-center rounded-full bg-stone-900/70 text-2xl leading-none text-white hover:bg-stone-900"
            aria-label="Закрыть"
            @click="close"
        >
            ×
        </button>

        <button
            v-if="hasMany"
            type="button"
            class="absolute left-2 top-1/2 z-10 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-stone-900/70 text-xl text-white hover:bg-stone-900 sm:left-4"
            aria-label="Предыдущее фото"
            @click="prev"
        >
            ‹
        </button>

        <button
            v-if="hasMany"
            type="button"
            class="absolute right-2 top-1/2 z-10 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-stone-900/70 text-xl text-white hover:bg-stone-900 sm:right-4"
            aria-label="Следующее фото"
            @click="next"
        >
            ›
        </button>

        <div class="flex h-full w-full max-h-full max-w-full items-center justify-center" @click.self="close">
            <img
                :key="current.url"
                :src="current.url"
                :alt="current.alt || ''"
                class="rounded-lg shadow-lg"
                :class="isPortrait ? 'h-full max-h-full w-auto max-w-full' : 'h-auto max-h-full w-full max-w-full'"
                @load="onImageLoad"
            />
        </div>

        <div
            v-if="hasMany"
            class="pointer-events-none absolute bottom-4 left-1/2 -translate-x-1/2 rounded-full bg-stone-900/70 px-3 py-1 text-sm text-white"
        >
            {{ index + 1 }} / {{ photos.length }}
        </div>
    </div>
</template>

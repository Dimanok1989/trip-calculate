<script setup>
import { computed } from 'vue';
import { formatPercent } from '../utils/expenseTypeBreakdown.js';

const props = defineProps({
    segments: {
        type: Array,
        required: true,
    },
    formatMoney: {
        type: Function,
        required: true,
    },
});

const gradient = computed(() => {
    if (!props.segments.length) {
        return 'transparent';
    }

    let cursor = 0;
    const stops = props.segments.map((segment) => {
        const start = cursor * 100;
        cursor += segment.percent;
        const end = cursor * 100;
        return `${segment.color} ${start}% ${end}%`;
    });

    return `conic-gradient(${stops.join(', ')})`;
});
</script>

<template>
    <div class="flex flex-col items-center gap-6 sm:flex-row sm:items-start sm:justify-center">
        <div
            class="h-44 w-44 shrink-0 rounded-full"
            role="img"
            :aria-label="'Распределение расходов по типам'"
            :style="{ background: gradient }"
        />

        <ul class="w-full max-w-sm space-y-2 text-sm">
            <li
                v-for="segment in segments"
                :key="segment.label"
                class="flex items-baseline justify-between gap-3"
            >
                <span class="flex min-w-0 items-center gap-2 text-stone-800">
                    <span
                        class="inline-block h-3 w-3 shrink-0 rounded-sm"
                        :style="{ backgroundColor: segment.color }"
                        aria-hidden="true"
                    />
                    <span class="truncate">{{ segment.label }}</span>
                </span>
                <span class="shrink-0 tabular-nums text-stone-600">
                    {{ formatMoney(segment.amount) }}
                    <span class="text-stone-400">·</span>
                    {{ formatPercent(segment.percent) }}
                </span>
            </li>
        </ul>
    </div>
</template>

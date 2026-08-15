<script setup>
import { computed } from 'vue'

const props = defineProps({
    data: { type: Array, required: true },
})

const max = computed(() => Math.max(...props.data.map((d) => d.total), 1))
const hasData = computed(() => props.data.some((d) => d.total > 0))

const money = (n) => '$' + Number(n).toLocaleString(undefined, { maximumFractionDigits: 0 })
</script>

<template>
    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
        <div class="mb-6 flex items-baseline justify-between">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Revenue</h2>
            <span class="text-xs text-gray-400">Last 14 days</span>
        </div>

        <div v-if="hasData" class="flex h-48 items-end gap-1.5">
            <div
                v-for="point in data"
                :key="point.date"
                class="group relative flex flex-1 flex-col items-center justify-end"
            >
                <div
                    class="w-full rounded-t bg-brand-600 transition-all hover:bg-brand-700"
                    :style="{ height: `${Math.max((point.total / max) * 100, point.total > 0 ? 2 : 0)}%` }"
                />
                <span
                    class="pointer-events-none absolute -top-7 whitespace-nowrap rounded bg-gray-900 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100"
                >
                    {{ money(point.total) }}
                </span>
            </div>
        </div>

        <!-- Empty state matters here: no orders exist until checkout ships -->
        <div
            v-else
            class="flex h-48 flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-200 text-center dark:border-gray-700"
        >
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">No revenue yet</p>
            <p class="mt-1 text-xs text-gray-400">Sales will appear once checkout is live.</p>
        </div>

        <div v-if="hasData" class="mt-3 flex justify-between text-[10px] text-gray-400">
            <span>{{ data[0]?.label }}</span>
            <span>{{ data[data.length - 1]?.label }}</span>
        </div>
    </div>
</template>

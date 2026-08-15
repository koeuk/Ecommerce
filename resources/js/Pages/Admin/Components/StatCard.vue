<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    hint: { type: String, default: null },
    tone: { type: String, default: 'neutral' }, // neutral | positive | warning
    href: { type: String, default: null },
})

const tones = {
    neutral: 'text-gray-900 dark:text-white',
    positive: 'text-green-600 dark:text-green-400',
    warning: 'text-amber-600 dark:text-amber-400',
}
</script>

<template>
    <component
        :is="href ? Link : 'div'"
        :href="href"
        class="block rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 transition dark:bg-gray-800 dark:ring-gray-700"
        :class="href ? 'hover:ring-blue-400' : ''"
    >
        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ label }}</p>
        <p class="mt-2 text-2xl font-semibold tabular-nums" :class="tones[tone]">{{ value }}</p>
        <p v-if="hint" class="mt-1 text-xs text-gray-400">{{ hint }}</p>
    </component>
</template>

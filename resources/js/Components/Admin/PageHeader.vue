<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
    actionLabel: { type: String, default: null },
    actionHref: { type: String, default: null },
})
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">{{ title }}</h1>
            <p v-if="subtitle" class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ subtitle }}</p>
        </div>

        <Link
            v-if="actionLabel && actionHref"
            :href="actionHref"
            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-brand-700 rounded-lg hover:bg-brand-800 focus:ring-4 focus:ring-brand-200"
        >
            {{ actionLabel }}
        </Link>

        <slot name="actions" />
    </div>
</template>

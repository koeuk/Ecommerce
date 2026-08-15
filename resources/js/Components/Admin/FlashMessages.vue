<script setup>
import { computed, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'

const page = usePage()
const visible = ref(true)

const flash = computed(() => page.props.flash ?? {})

watch(flash, () => { visible.value = true }, { deep: true })
</script>

<template>
    <div v-if="visible && (flash.success || flash.error)" class="mb-4">
        <div
            v-if="flash.success"
            class="flex items-center justify-between p-4 text-sm text-green-800 rounded-lg bg-green-50 dark:bg-gray-800 dark:text-green-400"
            role="alert"
        >
            <span>{{ flash.success }}</span>
            <button type="button" class="ml-4 opacity-60 hover:opacity-100" @click="visible = false">✕</button>
        </div>

        <div
            v-if="flash.error"
            class="flex items-center justify-between p-4 text-sm text-red-800 rounded-lg bg-red-50 dark:bg-gray-800 dark:text-red-400"
            role="alert"
        >
            <span>{{ flash.error }}</span>
            <button type="button" class="ml-4 opacity-60 hover:opacity-100" @click="visible = false">✕</button>
        </div>
    </div>
</template>

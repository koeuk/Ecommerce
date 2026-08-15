<script setup>
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
    modelValue: { type: Array, required: true },
})

const emit = defineEmits(['update:modelValue'])

const page = usePage()
const locale = computed(() => (page.props.locales ?? ['en'])[0])

const addRow = () => {
    emit('update:modelValue', [
        ...props.modelValue,
        { group: { en: '' }, key: { en: '' }, value: { en: '' } },
    ])
}

const removeRow = (index) => {
    emit('update:modelValue', props.modelValue.filter((_, i) => i !== index))
}

/** Copies the group from the row above — spec sheets are mostly grouped runs. */
const inheritGroup = (index) => {
    if (index === 0) return
    const rows = [...props.modelValue]
    rows[index] = { ...rows[index], group: { ...props.modelValue[index - 1].group } }
    emit('update:modelValue', rows)
}

const cell = 'w-full rounded border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600 dark:bg-gray-700 dark:border-gray-600 dark:text-white'
</script>

<template>
    <div class="space-y-3">
        <p class="text-xs text-gray-500 dark:text-gray-400">
            Descriptive detail only — specs never create a SKU. Group related rows together
            (Processor, Display, Battery) and they render as sections on the product page.
        </p>

        <div v-if="modelValue.length" class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                    <tr>
                        <th class="px-3 py-2 w-1/4">Group</th>
                        <th class="px-3 py-2 w-1/4">Name</th>
                        <th class="px-3 py-2">Value</th>
                        <th class="px-3 py-2 w-10"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(spec, index) in modelValue" :key="index" class="border-b dark:border-gray-700">
                        <td class="px-3 py-2">
                            <div class="flex gap-1">
                                <input
                                    v-model="spec.group[locale]"
                                    type="text"
                                    placeholder="Display"
                                    :class="cell"
                                />
                                <button
                                    v-if="index > 0"
                                    type="button"
                                    title="Same group as the row above"
                                    class="shrink-0 rounded border border-gray-300 px-2 text-xs text-gray-500 hover:bg-gray-50 dark:border-gray-600"
                                    @click="inheritGroup(index)"
                                >
                                    ↑
                                </button>
                            </div>
                        </td>
                        <td class="px-3 py-2">
                            <input v-model="spec.key[locale]" type="text" placeholder="Screen Size" :class="cell" />
                        </td>
                        <td class="px-3 py-2">
                            <input v-model="spec.value[locale]" type="text" placeholder="15.6 inch" :class="cell" />
                        </td>
                        <td class="px-3 py-2 text-right">
                            <button type="button" class="text-red-600 hover:underline" @click="removeRow(index)">
                                ✕
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p v-else class="rounded-lg bg-gray-50 py-6 text-center text-sm text-gray-500 dark:bg-gray-700/40">
            No specifications yet.
        </p>

        <button
            type="button"
            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
            @click="addRow"
        >
            Add specification
        </button>
    </div>
</template>

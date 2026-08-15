<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
    modelValue: { type: Array, required: true },
    attributes: { type: Array, required: true },
    baseSku: { type: String, default: '' },
    basePrice: { type: [Number, String], default: 0 },
    errors: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:modelValue'])

// Which attributes participate in the matrix, and which of their values
const selected = ref({})

const chosenAttributes = computed(() =>
    props.attributes.filter((a) => (selected.value[a.id] ?? []).length > 0),
)

const combinationCount = computed(() =>
    chosenAttributes.value.reduce((total, a) => total * selected.value[a.id].length, 1),
)

const toggleValue = (attributeId, valueId) => {
    const current = selected.value[attributeId] ?? []
    selected.value = {
        ...selected.value,
        [attributeId]: current.includes(valueId)
            ? current.filter((id) => id !== valueId)
            : [...current, valueId],
    }
}

const isSelected = (attributeId, valueId) => (selected.value[attributeId] ?? []).includes(valueId)

const labelFor = (attributeId, valueId) =>
    props.attributes.find((a) => a.id === attributeId)?.values.find((v) => v.id === valueId)?.label ?? ''

/** Cartesian product of the chosen attribute values. */
const generate = () => {
    if (chosenAttributes.value.length === 0) return

    let combos = [[]]

    for (const attribute of chosenAttributes.value) {
        const next = []
        for (const partial of combos) {
            for (const valueId of selected.value[attribute.id]) {
                next.push([...partial, { attributeId: attribute.id, valueId }])
            }
        }
        combos = next
    }

    // Keep rows that already exist so their price and stock survive regeneration
    const existing = new Map(
        props.modelValue.map((v) => [
            Object.entries(v.attribute_value_ids ?? {})
                .map(([a, val]) => `${a}:${val}`)
                .sort()
                .join('|'),
            v,
        ]),
    )

    const rows = combos.map((combo, index) => {
        const map = {}
        combo.forEach(({ attributeId, valueId }) => {
            map[attributeId] = valueId
        })

        const key = Object.entries(map)
            .map(([a, val]) => `${a}:${val}`)
            .sort()
            .join('|')

        if (existing.has(key)) return existing.get(key)

        return {
            id: null,
            sku: `${props.baseSku || 'SKU'}-V${String(index + 1).padStart(2, '0')}`,
            label: combo.map(({ attributeId, valueId }) => labelFor(attributeId, valueId)).join(' / '),
            price: Number(props.basePrice) || 0,
            compare_at_price: null,
            cost_price: null,
            stock_quantity: 0,
            low_stock_threshold: 5,
            allow_backorder: false,
            is_active: true,
            attribute_value_ids: map,
        }
    })

    emit('update:modelValue', rows)
}

const addBlankRow = () => {
    emit('update:modelValue', [
        ...props.modelValue,
        {
            id: null,
            sku: `${props.baseSku || 'SKU'}-V${String(props.modelValue.length + 1).padStart(2, '0')}`,
            label: '',
            price: Number(props.basePrice) || 0,
            compare_at_price: null,
            cost_price: null,
            stock_quantity: 0,
            low_stock_threshold: 5,
            allow_backorder: false,
            is_active: true,
            attribute_value_ids: {},
        },
    ])
}

const removeRow = (index) => {
    emit('update:modelValue', props.modelValue.filter((_, i) => i !== index))
}

const cell = 'w-full rounded border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600 dark:bg-gray-700 dark:border-gray-600 dark:text-white'
</script>

<template>
    <div class="space-y-6">
        <!-- Matrix builder -->
        <div class="rounded-lg border border-dashed border-gray-300 p-4 dark:border-gray-600">
            <h3 class="mb-1 text-sm font-semibold text-gray-900 dark:text-white">Generate variants</h3>
            <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">
                Pick the options this product comes in. Every combination becomes its own SKU with
                its own price and stock. Existing rows keep their values.
            </p>

            <div class="space-y-3">
                <div v-for="attribute in attributes" :key="attribute.id">
                    <p class="mb-1.5 text-xs font-medium uppercase tracking-wide text-gray-500">
                        {{ attribute.name }}
                    </p>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="value in attribute.values"
                            :key="value.id"
                            type="button"
                            class="rounded-full border px-3 py-1 text-xs transition"
                            :class="isSelected(attribute.id, value.id)
                                ? 'border-brand-700 bg-brand-700 text-white'
                                : 'border-gray-300 bg-white text-gray-600 hover:border-brand-600 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300'"
                            @click="toggleValue(attribute.id, value.id)"
                        >
                            <span
                                v-if="value.colour_hex"
                                class="mr-1 inline-block h-2.5 w-2.5 rounded-full ring-1 ring-black/10"
                                :style="{ backgroundColor: value.colour_hex }"
                            />
                            {{ value.label }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <button
                    type="button"
                    :disabled="chosenAttributes.length === 0"
                    class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-800 disabled:cursor-not-allowed disabled:opacity-40"
                    @click="generate"
                >
                    Generate {{ chosenAttributes.length ? combinationCount : '' }} variant{{ combinationCount === 1 ? '' : 's' }}
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                    @click="addBlankRow"
                >
                    Add single row
                </button>
                <p v-if="combinationCount > 50 && chosenAttributes.length" class="text-xs text-amber-600">
                    That is {{ combinationCount }} SKUs — consider fewer options.
                </p>
            </div>
        </div>

        <!-- Variant rows -->
        <div v-if="modelValue.length" class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                    <tr>
                        <th class="px-3 py-2">SKU</th>
                        <th class="px-3 py-2">Options</th>
                        <th class="px-3 py-2 w-28">Price</th>
                        <th class="px-3 py-2 w-24">Stock</th>
                        <th class="px-3 py-2 w-20">Active</th>
                        <th class="px-3 py-2 w-10"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(variant, index) in modelValue"
                        :key="index"
                        class="border-b dark:border-gray-700"
                        :class="index === 0 ? 'bg-brand-50/50 dark:bg-brand-950/20' : ''"
                    >
                        <td class="px-3 py-2">
                            <input v-model="variant.sku" type="text" :class="cell" />
                            <p v-if="errors[`variants.${index}.sku`]" class="mt-1 text-xs text-red-600">
                                {{ errors[`variants.${index}.sku`] }}
                            </p>
                            <span v-if="index === 0" class="text-[10px] uppercase text-brand-700">Default</span>
                        </td>
                        <td class="px-3 py-2">
                            <div v-if="attributes.length" class="flex flex-wrap gap-1">
                                <select
                                    v-for="attribute in attributes"
                                    :key="attribute.id"
                                    v-model="variant.attribute_value_ids[attribute.id]"
                                    class="rounded border-gray-300 text-xs focus:border-brand-600 focus:ring-brand-600 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                >
                                    <option :value="undefined">{{ attribute.name }}…</option>
                                    <option v-for="value in attribute.values" :key="value.id" :value="value.id">
                                        {{ value.label }}
                                    </option>
                                </select>
                            </div>
                            <p v-if="errors[`variants.${index}.attribute_value_ids`]" class="mt-1 text-xs text-red-600">
                                {{ errors[`variants.${index}.attribute_value_ids`] }}
                            </p>
                        </td>
                        <td class="px-3 py-2">
                            <input v-model.number="variant.price" type="number" step="0.01" min="0" :class="cell" />
                        </td>
                        <td class="px-3 py-2">
                            <input v-model.number="variant.stock_quantity" type="number" min="0" :class="cell" />
                        </td>
                        <td class="px-3 py-2 text-center">
                            <input
                                v-model="variant.is_active"
                                type="checkbox"
                                class="h-4 w-4 rounded border-gray-300 text-brand-700 focus:ring-brand-600"
                            />
                        </td>
                        <td class="px-3 py-2 text-right">
                            <button
                                type="button"
                                class="text-red-600 hover:underline"
                                :disabled="modelValue.length === 1"
                                :class="modelValue.length === 1 ? 'cursor-not-allowed opacity-30' : ''"
                                @click="removeRow(index)"
                            >
                                ✕
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="mt-2 text-xs text-gray-500">
                The first row is the default variant — it is what the storefront shows before a
                customer picks options.
            </p>
        </div>

        <p v-else class="rounded-lg bg-gray-50 py-8 text-center text-sm text-gray-500 dark:bg-gray-700/40">
            No variants yet. Generate them above, or add a single row for a product with no options.
        </p>
    </div>
</template>

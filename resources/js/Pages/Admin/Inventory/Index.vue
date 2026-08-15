<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { debounce } from '@/utils/debounce'
import AdminLayout from '../Components/AdminLayout.vue'
import PageHeader from '@/Components/Admin/PageHeader.vue'
import FlashMessages from '@/Components/Admin/FlashMessages.vue'
import StatCard from '../Components/StatCard.vue'

const props = defineProps({
    variants: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, required: true },
})

const search = ref(props.filters.search ?? '')
const stock = ref(props.filters.stock ?? '')

const refresh = debounce(() => {
    router.get(
        route('admin.inventory.index'),
        { filter: { search: search.value || undefined, stock: stock.value || undefined } },
        { preserveState: true, replace: true },
    )
}, 300)

watch([search, stock], refresh)

// Inline adjustment
const editing = ref(null)
const adjustForm = useForm({ mode: 'set', quantity: 0, reason: '' })

const openAdjust = (variant) => {
    editing.value = variant
    adjustForm.reset()
    adjustForm.mode = 'set'
    adjustForm.quantity = variant.stock_quantity
}

const submitAdjust = () => {
    adjustForm.put(route('admin.inventory.adjust', editing.value.id), {
        preserveScroll: true,
        onSuccess: () => { editing.value = null },
    })
}

const money = (n) => '$' + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2 })
const field = 'rounded-lg border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600 dark:bg-gray-700 dark:border-gray-600 dark:text-white'
</script>

<template>
    <Head title="Inventory" />

    <AdminLayout>
        <main class="p-4 pt-20 md:ml-64">
            <FlashMessages />

            <PageHeader title="Inventory" subtitle="Stock levels per variant" />

            <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard label="Stock units" :value="summary.stock_units" :hint="`${summary.total_variants} variants`" />
                <StatCard label="Stock value" :value="money(summary.stock_value)" hint="At selling price" />
                <StatCard
                    label="Low stock"
                    :value="summary.low_stock"
                    hint="At or below threshold"
                    :tone="summary.low_stock > 0 ? 'warning' : 'neutral'"
                />
                <StatCard
                    label="Out of stock"
                    :value="summary.out_of_stock"
                    hint="Zero on hand"
                    :tone="summary.out_of_stock > 0 ? 'warning' : 'neutral'"
                />
            </div>

            <div class="mb-4 flex flex-wrap gap-3">
                <input v-model="search" type="search" placeholder="Search SKU or product…" :class="['w-full sm:w-72', field]" />
                <select v-model="stock" :class="field">
                    <option value="">All stock levels</option>
                    <option value="out">Out of stock</option>
                    <option value="low">Low stock</option>
                    <option value="in">In stock</option>
                </select>
            </div>

            <div class="overflow-x-auto rounded-lg bg-white shadow dark:bg-gray-800">
                <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-700 dark:bg-gray-700 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3">Product / Variant</th>
                            <th class="px-6 py-3">SKU</th>
                            <th class="px-6 py-3">Price</th>
                            <th class="px-6 py-3">On hand</th>
                            <th class="px-6 py-3">Threshold</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="variant in variants.data" :key="variant.id">
                            <tr class="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                <td class="px-6 py-3">
                                    <p class="font-medium text-gray-900 dark:text-white">{{ variant.product_title }}</p>
                                    <p class="text-xs text-gray-400">{{ variant.label ?? '—' }}</p>
                                </td>
                                <td class="px-6 py-3 font-mono text-xs">{{ variant.sku }}</td>
                                <td class="px-6 py-3 tabular-nums">{{ money(variant.price) }}</td>
                                <td class="px-6 py-3">
                                    <span
                                        class="rounded px-2 py-0.5 text-sm font-semibold tabular-nums"
                                        :class="variant.stock_quantity === 0
                                            ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'
                                            : variant.is_low
                                                ? 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-300'
                                                : 'bg-brand-100 text-brand-800 dark:bg-brand-900 dark:text-brand-200'"
                                    >
                                        {{ variant.stock_quantity }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 tabular-nums">{{ variant.low_stock_threshold }}</td>
                                <td class="whitespace-nowrap px-6 py-3 text-right">
                                    <button
                                        type="button"
                                        class="font-medium text-brand-700 hover:underline dark:text-brand-400"
                                        @click="openAdjust(variant)"
                                    >
                                        Adjust
                                    </button>
                                    <Link
                                        :href="route('admin.inventory.history', variant.id)"
                                        class="ml-3 font-medium text-gray-500 hover:underline"
                                    >
                                        History
                                    </Link>
                                </td>
                            </tr>

                            <!-- Inline adjust row -->
                            <tr v-if="editing?.id === variant.id" class="border-b bg-brand-50/60 dark:border-gray-700 dark:bg-brand-950/20">
                                <td colspan="6" class="px-6 py-4">
                                    <form class="flex flex-wrap items-end gap-3" @submit.prevent="submitAdjust">
                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Mode</label>
                                            <select v-model="adjustForm.mode" :class="field">
                                                <option value="set">Set to</option>
                                                <option value="add">Add</option>
                                                <option value="subtract">Subtract</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Quantity</label>
                                            <input v-model.number="adjustForm.quantity" type="number" min="0" :class="['w-28', field]" />
                                        </div>
                                        <div class="flex-1 min-w-[12rem]">
                                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Reason</label>
                                            <input
                                                v-model="adjustForm.reason"
                                                type="text"
                                                placeholder="Stock count, damaged, received shipment…"
                                                :class="['w-full', field]"
                                            />
                                        </div>
                                        <button
                                            type="submit"
                                            :disabled="adjustForm.processing"
                                            class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800 disabled:opacity-50"
                                        >
                                            Save
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 dark:border-gray-600 dark:text-gray-300"
                                            @click="editing = null"
                                        >
                                            Cancel
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        </template>

                        <tr v-if="variants.data.length === 0">
                            <td colspan="6" class="px-6 py-10 text-center text-gray-500">No variants found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="variants.links.length > 3" class="mt-4 flex flex-wrap gap-1">
                <component
                    :is="link.url ? 'Link' : 'span'"
                    v-for="link in variants.links"
                    :key="link.label"
                    :href="link.url"
                    class="rounded border px-3 py-1.5 text-sm"
                    :class="link.active
                        ? 'border-brand-700 bg-brand-700 text-white'
                        : 'border-gray-300 bg-white text-gray-600 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300'"
                    v-html="link.label"
                />
            </div>
        </main>
    </AdminLayout>
</template>

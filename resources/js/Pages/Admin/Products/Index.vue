<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { debounce } from '@/utils/debounce'
import AdminLayout from '../Components/AdminLayout.vue'
import PageHeader from '@/Components/Admin/PageHeader.vue'
import FlashMessages from '@/Components/Admin/FlashMessages.vue'

const props = defineProps({
    products: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, required: true },
})

const search = ref(props.filters.search ?? '')
const status = ref(props.filters.status ?? '')
const brandId = ref(props.filters.brand_id ?? '')
const categoryId = ref(props.filters.category_id ?? '')
const lowStock = ref(Boolean(props.filters.low_stock))

const refresh = debounce(() => {
    router.get(
        route('admin.products.index'),
        {
            filter: {
                search: search.value || undefined,
                status: status.value || undefined,
                brand_id: brandId.value || undefined,
                category_id: categoryId.value || undefined,
                low_stock: lowStock.value ? 1 : undefined,
            },
        },
        { preserveState: true, replace: true },
    )
}, 300)

watch([search, status, brandId, categoryId, lowStock], refresh)

const destroy = (product) => {
    if (confirm(`Delete "${product.title}"? It will be hidden from the store but kept for order history.`)) {
        router.delete(route('admin.products.destroy', product.id), { preserveScroll: true })
    }
}

const duplicate = (product) => {
    router.post(route('admin.products.duplicate', product.id))
}

const money = (n) => '$' + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2 })

const statusTone = {
    published: 'bg-brand-100 text-brand-800 dark:bg-brand-900 dark:text-brand-200',
    draft: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    archived: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-300',
}

const selectClass =
    'rounded-lg border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600 dark:bg-gray-700 dark:border-gray-600 dark:text-white'
</script>

<template>
    <Head title="Products" />

    <AdminLayout>
        <main class="p-4 pt-20 md:ml-64">
            <FlashMessages />

            <PageHeader
                title="Products"
                :subtitle="`${products.total} product${products.total === 1 ? '' : 's'}`"
                action-label="Add product"
                :action-href="route('admin.products.create')"
            />

            <div class="mb-4 flex flex-wrap gap-3">
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search title or SKU…"
                    :class="['w-full sm:w-64', selectClass]"
                />
                <select v-model="status" :class="selectClass">
                    <option value="">All statuses</option>
                    <option v-for="s in options.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
                <select v-model="brandId" :class="selectClass">
                    <option value="">All brands</option>
                    <option v-for="b in options.brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                </select>
                <select v-model="categoryId" :class="selectClass">
                    <option value="">All categories</option>
                    <option v-for="c in options.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                    <input
                        v-model="lowStock"
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-300 text-brand-700 focus:ring-brand-600"
                    />
                    Low stock only
                </label>
            </div>

            <div class="overflow-x-auto rounded-lg bg-white shadow dark:bg-gray-800">
                <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-700 dark:bg-gray-700 dark:text-gray-400">
                        <tr>
                            <th scope="col" class="px-6 py-3">Product</th>
                            <th scope="col" class="px-6 py-3">Brand / Category</th>
                            <th scope="col" class="px-6 py-3">Price</th>
                            <th scope="col" class="px-6 py-3">Stock</th>
                            <th scope="col" class="px-6 py-3">Variants</th>
                            <th scope="col" class="px-6 py-3">Status</th>
                            <th scope="col" class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="product in products.data"
                            :key="product.id"
                            class="border-b hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700"
                        >
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <img
                                        v-if="product.image_url"
                                        :src="product.image_url"
                                        :alt="product.title"
                                        class="h-10 w-10 rounded object-cover"
                                    />
                                    <div v-else class="h-10 w-10 rounded bg-gray-100 dark:bg-gray-700" />
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-gray-900 dark:text-white">
                                            {{ product.title }}
                                            <span v-if="product.is_featured" class="ml-1 text-brand-600">★</span>
                                        </p>
                                        <p class="truncate font-mono text-xs text-gray-400">{{ product.sku }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-gray-900 dark:text-white">{{ product.brand ?? '—' }}</p>
                                <p class="text-xs text-gray-400">{{ product.category ?? '—' }}</p>
                            </td>
                            <td class="px-6 py-4 tabular-nums">{{ money(product.price) }}</td>
                            <td class="px-6 py-4">
                                <span
                                    class="tabular-nums"
                                    :class="product.stock_quantity === 0 ? 'font-semibold text-red-600' : ''"
                                >
                                    {{ product.stock_quantity }}
                                </span>
                            </td>
                            <td class="px-6 py-4">{{ product.variants_count }}</td>
                            <td class="px-6 py-4">
                                <span class="rounded px-2 py-0.5 text-xs font-medium" :class="statusTone[product.status]">
                                    {{ product.status }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right">
                                <Link
                                    :href="route('admin.products.edit', product.id)"
                                    class="font-medium text-brand-700 hover:underline dark:text-brand-400"
                                >
                                    Edit
                                </Link>
                                <button
                                    type="button"
                                    class="ml-3 font-medium text-gray-500 hover:underline"
                                    @click="duplicate(product)"
                                >
                                    Duplicate
                                </button>
                                <button
                                    type="button"
                                    class="ml-3 font-medium text-red-600 hover:underline dark:text-red-500"
                                    @click="destroy(product)"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>

                        <tr v-if="products.data.length === 0">
                            <td colspan="7" class="px-6 py-10 text-center text-gray-500">No products found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="products.links.length > 3" class="mt-4 flex flex-wrap gap-1">
                <component
                    :is="link.url ? 'Link' : 'span'"
                    v-for="link in products.links"
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

<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { debounce } from '@/utils/debounce'
import AdminLayout from '../Components/AdminLayout.vue'
import PageHeader from '@/Components/Admin/PageHeader.vue'
import FlashMessages from '@/Components/Admin/FlashMessages.vue'

const props = defineProps({
    brands: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
})

const search = ref(props.filters.search ?? '')
const status = ref(props.filters.status ?? '')

// spatie/laravel-query-builder reads filters from filter[...]
const refresh = debounce(() => {
    router.get(
        route('admin.brands.index'),
        {
            filter: {
                search: search.value || undefined,
                status: status.value || undefined,
            },
        },
        { preserveState: true, replace: true },
    )
}, 300)

watch([search, status], refresh)

const destroy = (brand) => {
    if (confirm(`Delete "${brand.name.en}"? This cannot be undone.`)) {
        router.delete(route('admin.brands.destroy', brand.id), { preserveScroll: true })
    }
}
</script>

<template>
    <Head title="Brands" />

    <AdminLayout>
        <main class="p-4 md:ml-64 pt-20">
            <FlashMessages />

            <PageHeader
                title="Brands"
                :subtitle="`${brands.total} brand${brands.total === 1 ? '' : 's'}`"
                action-label="Add brand"
                :action-href="route('admin.brands.create')"
            />

            <div class="flex flex-wrap gap-3 mb-4">
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search brands…"
                    class="w-full sm:w-64 rounded-lg border-gray-300 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                />
                <select
                    v-model="status"
                    class="rounded-lg border-gray-300 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                >
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <div class="overflow-x-auto bg-white rounded-lg shadow dark:bg-gray-800">
                <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                        <tr>
                            <th scope="col" class="px-6 py-3">Brand</th>
                            <th scope="col" class="px-6 py-3">Slug</th>
                            <th scope="col" class="px-6 py-3">Products</th>
                            <th scope="col" class="px-6 py-3">Status</th>
                            <th scope="col" class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="brand in brands.data"
                            :key="brand.id"
                            class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700"
                        >
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <img
                                        v-if="brand.logo_url"
                                        :src="brand.logo_url"
                                        :alt="brand.name.en"
                                        class="object-contain w-8 h-8"
                                    />
                                    <div
                                        v-else
                                        class="flex items-center justify-center w-8 h-8 text-xs font-semibold text-gray-500 bg-gray-100 rounded dark:bg-gray-700"
                                    >
                                        {{ brand.name.en?.charAt(0) }}
                                    </div>
                                    <span class="font-medium text-gray-900 dark:text-white">
                                        {{ brand.name.en }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 font-mono text-xs">{{ brand.slug }}</td>
                            <td class="px-6 py-4">{{ brand.products_count }}</td>
                            <td class="px-6 py-4">
                                <span
                                    class="px-2 py-0.5 text-xs font-medium rounded"
                                    :class="brand.is_active
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
                                        : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'"
                                >
                                    {{ brand.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                <Link
                                    :href="route('admin.brands.edit', brand.id)"
                                    class="font-medium text-blue-600 hover:underline dark:text-blue-500"
                                >
                                    Edit
                                </Link>
                                <button
                                    type="button"
                                    class="ml-4 font-medium text-red-600 hover:underline dark:text-red-500"
                                    @click="destroy(brand)"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>

                        <tr v-if="brands.data.length === 0">
                            <td colspan="5" class="px-6 py-10 text-center text-gray-500">
                                No brands found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="brands.links.length > 3" class="flex flex-wrap gap-1 mt-4">
                <component
                    :is="link.url ? 'Link' : 'span'"
                    v-for="link in brands.links"
                    :key="link.label"
                    :href="link.url"
                    class="px-3 py-1.5 text-sm rounded border"
                    :class="link.active
                        ? 'bg-blue-600 text-white border-blue-600'
                        : 'bg-white text-gray-600 border-gray-300 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600'"
                    v-html="link.label"
                />
            </div>
        </main>
    </AdminLayout>
</template>

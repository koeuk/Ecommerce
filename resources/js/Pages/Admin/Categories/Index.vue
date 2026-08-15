<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '../Components/AdminLayout.vue'
import PageHeader from '@/Components/Admin/PageHeader.vue'
import FlashMessages from '@/Components/Admin/FlashMessages.vue'
import CategoryRow from './CategoryRow.vue'

defineProps({
    tree: { type: Array, required: true },
    total: { type: Number, default: 0 },
})

const destroy = (category) => {
    if (confirm(`Delete "${category.name.en}"? This cannot be undone.`)) {
        router.delete(route('admin.categories.destroy', category.id), { preserveScroll: true })
    }
}
</script>

<template>
    <Head title="Categories" />

    <AdminLayout>
        <main class="p-4 md:ml-64 pt-20">
            <FlashMessages />

            <PageHeader
                title="Categories"
                :subtitle="`${total} categories`"
                action-label="Add category"
                :action-href="route('admin.categories.create')"
            />

            <div class="overflow-x-auto bg-white rounded-lg shadow dark:bg-gray-800">
                <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                        <tr>
                            <th scope="col" class="px-6 py-3">Category</th>
                            <th scope="col" class="px-6 py-3">Slug</th>
                            <th scope="col" class="px-6 py-3">Products</th>
                            <th scope="col" class="px-6 py-3">Status</th>
                            <th scope="col" class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <CategoryRow
                            v-for="node in tree"
                            :key="node.id"
                            :node="node"
                            :depth="0"
                            @delete="destroy"
                        />

                        <tr v-if="tree.length === 0">
                            <td colspan="5" class="px-6 py-10 text-center text-gray-500">
                                No categories yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </AdminLayout>
</template>

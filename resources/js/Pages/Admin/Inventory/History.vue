<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '../Components/AdminLayout.vue'
import PageHeader from '@/Components/Admin/PageHeader.vue'

defineProps({
    variant: { type: Object, required: true },
    movements: { type: Object, required: true },
})
</script>

<template>
    <Head :title="`Stock history · ${variant.sku}`" />

    <AdminLayout>
        <main class="p-4 pt-20 md:ml-64">
            <PageHeader
                title="Stock history"
                :subtitle="`${variant.product_title} · ${variant.sku} · ${variant.stock_quantity} on hand`"
            >
                <template #actions>
                    <Link
                        :href="route('admin.inventory.index')"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300"
                    >
                        Back to inventory
                    </Link>
                </template>
            </PageHeader>

            <div class="overflow-x-auto rounded-lg bg-white shadow dark:bg-gray-800">
                <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-700 dark:bg-gray-700 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3">When</th>
                            <th class="px-6 py-3">Change</th>
                            <th class="px-6 py-3">Resulting stock</th>
                            <th class="px-6 py-3">Reason</th>
                            <th class="px-6 py-3">By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="movement in movements.data"
                            :key="movement.id"
                            class="border-b dark:border-gray-700"
                        >
                            <td class="px-6 py-3 whitespace-nowrap">{{ movement.created_at }}</td>
                            <td class="px-6 py-3">
                                <span
                                    class="font-semibold tabular-nums"
                                    :class="movement.quantity >= 0 ? 'text-brand-700 dark:text-brand-400' : 'text-red-600'"
                                >
                                    {{ movement.quantity > 0 ? '+' : '' }}{{ movement.quantity }}
                                </span>
                            </td>
                            <td class="px-6 py-3 tabular-nums">{{ movement.stock_after }}</td>
                            <td class="px-6 py-3">{{ movement.reason ?? '—' }}</td>
                            <td class="px-6 py-3">{{ movement.author ?? 'System' }}</td>
                        </tr>

                        <tr v-if="movements.data.length === 0">
                            <td colspan="5" class="px-6 py-10 text-center text-gray-500">
                                No movements recorded yet. Adjustments and orders will appear here.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </AdminLayout>
</template>

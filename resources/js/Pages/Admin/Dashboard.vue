<script setup>
import { onMounted } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { initFlowbite } from 'flowbite'
import AdminLayout from './Components/AdminLayout.vue'
import StatCard from './Components/StatCard.vue'
import SalesChart from './Components/SalesChart.vue'
import FlashMessages from '@/Components/Admin/FlashMessages.vue'

defineProps({
    stats: { type: Object, required: true },
    salesChart: { type: Array, default: () => [] },
    ordersByStatus: { type: Array, default: () => [] },
    lowStock: { type: Array, default: () => [] },
    recentOrders: { type: Array, default: () => [] },
    topProducts: { type: Array, default: () => [] },
})

onMounted(() => initFlowbite())

const money = (n) => '$' + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2 })

const statusTone = {
    pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-300',
    confirmed: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    processing: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-300',
    shipped: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
    delivered: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    cancelled: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
    refunded: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
}
</script>

<template>
    <Head title="Dashboard" />

    <AdminLayout>
        <main class="p-4 pt-20 md:ml-64">
            <FlashMessages />

            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Dashboard</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Overview of your store.
                </p>
            </div>

            <!-- Headline stats -->
            <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label="Revenue today"
                    :value="money(stats.revenue_today)"
                    :hint="`${money(stats.revenue_month)} this month`"
                    tone="positive"
                />
                <StatCard
                    label="Orders"
                    :value="stats.orders_total"
                    :hint="`${stats.orders_pending} awaiting action`"
                />
                <StatCard
                    label="Products"
                    :value="stats.products_total"
                    :hint="`${stats.products_published} published`"
                    :href="route('admin.brands.index')"
                />
                <StatCard
                    label="Low stock"
                    :value="stats.low_stock_count"
                    hint="Variants at or below threshold"
                    :tone="stats.low_stock_count > 0 ? 'warning' : 'neutral'"
                />
            </div>

            <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
                <!-- Revenue chart -->
                <div class="lg:col-span-2">
                    <SalesChart :data="salesChart" />
                </div>

                <!-- Orders by status -->
                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                    <h2 class="mb-4 text-base font-semibold text-gray-900 dark:text-white">
                        Orders by status
                    </h2>
                    <ul class="space-y-2.5">
                        <li
                            v-for="row in ordersByStatus"
                            :key="row.status"
                            class="flex items-center justify-between text-sm"
                        >
                            <span
                                class="rounded px-2 py-0.5 text-xs font-medium"
                                :class="statusTone[row.status]"
                            >
                                {{ row.label }}
                            </span>
                            <span class="font-semibold tabular-nums text-gray-900 dark:text-white">
                                {{ row.count }}
                            </span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <!-- Low stock -->
                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                    <h2 class="mb-4 text-base font-semibold text-gray-900 dark:text-white">
                        Low stock alerts
                    </h2>

                    <ul v-if="lowStock.length" class="divide-y divide-gray-100 dark:divide-gray-700">
                        <li v-for="item in lowStock" :key="item.id" class="flex items-center justify-between py-2.5">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900 dark:text-white">
                                    {{ item.product_title }}
                                </p>
                                <p class="truncate text-xs text-gray-400">
                                    {{ item.sku }}<span v-if="item.label"> · {{ item.label }}</span>
                                </p>
                            </div>
                            <span
                                class="ml-3 shrink-0 rounded px-2 py-0.5 text-xs font-semibold"
                                :class="item.stock_quantity === 0
                                    ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'
                                    : 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-300'"
                            >
                                {{ item.stock_quantity }} left
                            </span>
                        </li>
                    </ul>

                    <p v-else class="py-8 text-center text-sm text-gray-400">
                        Everything is above its stock threshold.
                    </p>
                </div>

                <!-- Recent orders -->
                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                    <h2 class="mb-4 text-base font-semibold text-gray-900 dark:text-white">
                        Recent orders
                    </h2>

                    <ul v-if="recentOrders.length" class="divide-y divide-gray-100 dark:divide-gray-700">
                        <li v-for="order in recentOrders" :key="order.id" class="flex items-center justify-between py-2.5">
                            <div class="min-w-0">
                                <p class="truncate font-mono text-sm text-gray-900 dark:text-white">
                                    {{ order.order_number }}
                                </p>
                                <p class="truncate text-xs text-gray-400">
                                    {{ order.customer_name }} · {{ order.placed_at }}
                                </p>
                            </div>
                            <div class="ml-3 shrink-0 text-right">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ money(order.grand_total) }}
                                </p>
                                <span class="text-xs" :class="statusTone[order.status]">
                                    {{ order.status_label }}
                                </span>
                            </div>
                        </li>
                    </ul>

                    <p v-else class="py-8 text-center text-sm text-gray-400">
                        No orders yet — checkout ships in Phase 6.
                    </p>
                </div>
            </div>

            <!-- Most viewed -->
            <div class="mt-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                <h2 class="mb-4 text-base font-semibold text-gray-900 dark:text-white">Most viewed products</h2>

                <ul v-if="topProducts.length" class="divide-y divide-gray-100 dark:divide-gray-700">
                    <li v-for="product in topProducts" :key="product.id" class="flex items-center gap-3 py-3">
                        <img
                            v-if="product.image_url"
                            :src="product.image_url"
                            :alt="product.title"
                            class="h-10 w-10 rounded object-cover"
                        />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">
                                {{ product.title }}
                            </p>
                            <p class="text-xs text-gray-400">
                                {{ money(product.price) }} · {{ product.stock_quantity }} in stock
                            </p>
                        </div>
                        <span class="shrink-0 text-sm tabular-nums text-gray-500">
                            {{ product.views_count }} views
                        </span>
                    </li>
                </ul>

                <p v-else class="py-8 text-center text-sm text-gray-400">No published products yet.</p>
            </div>
        </main>
    </AdminLayout>
</template>

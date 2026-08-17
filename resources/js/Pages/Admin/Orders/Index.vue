<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { debounce } from '@/utils/debounce'
import AdminLayout from '../Components/AdminLayout.vue'
import PageHeader from '@/Components/Admin/PageHeader.vue'
import FlashMessages from '@/Components/Admin/FlashMessages.vue'
import StatCard from '../Components/StatCard.vue'

const props = defineProps({
    orders: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, required: true },
    summary: { type: Object, required: true },
})

const search = ref(props.filters.search ?? '')
const status = ref(props.filters.status ?? '')
const paymentStatus = ref(props.filters.payment_status ?? '')
const from = ref(props.filters.from ?? '')
const to = ref(props.filters.to ?? '')

const refresh = debounce(() => {
    router.get(
        route('admin.orders.index'),
        {
            filter: {
                search: search.value || undefined,
                status: status.value || undefined,
                payment_status: paymentStatus.value || undefined,
                from: from.value || undefined,
                to: to.value || undefined,
            },
        },
        { preserveState: true, replace: true },
    )
}, 300)

watch([search, status, paymentStatus, from, to], refresh)

const money = (n) => '$' + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2 })

// Status colours double as the visual grammar on the detail page.
const statusClass = (value) => ({
    pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-300',
    confirmed: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    processing: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-300',
    shipped: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
    delivered: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    cancelled: 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    refunded: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
}[value] ?? 'bg-gray-100 text-gray-800')

const paymentClass = (value) =>
    value === 'paid'
        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
        : value === 'refunded'
            ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'
            : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'

const field = 'rounded-lg border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600 dark:bg-gray-700 dark:border-gray-600 dark:text-white'
</script>

<template>
    <Head title="Orders" />

    <AdminLayout>
        <main class="p-4 pt-20 md:ml-64">
            <FlashMessages />

            <PageHeader title="Orders" subtitle="Fulfil and track customer orders" />

            <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard label="Awaiting action" :value="summary.pending" />
                <StatCard label="Unpaid" :value="summary.unpaid" />
                <StatCard label="Orders today" :value="summary.today_count" />
                <StatCard label="Revenue today" :value="money(summary.today_revenue)" />
            </div>

            <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <input v-model="search" type="search" placeholder="Order no., name, phone…" :class="field" />

                <select v-model="status" :class="field">
                    <option value="">All statuses</option>
                    <option v-for="s in options.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>

                <select v-model="paymentStatus" :class="field">
                    <option value="">All payments</option>
                    <option v-for="p in options.payment_statuses" :key="p.value" :value="p.value">{{ p.label }}</option>
                </select>

                <input v-model="from" type="date" :class="field" title="Placed from" />
                <input v-model="to" type="date" :class="field" title="Placed to" />
            </div>

            <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3">Order</th>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3 text-center">Items</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Payment</th>
                            <th class="px-4 py-3">Placed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="order in orders.data"
                            :key="order.id"
                            class="border-t border-gray-200 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700"
                        >
                            <td class="px-4 py-3">
                                <Link
                                    :href="route('admin.orders.show', order.id)"
                                    class="font-medium text-brand-600 hover:underline dark:text-brand-400"
                                >
                                    {{ order.order_number }}
                                </Link>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900 dark:text-white">{{ order.customer_name }}</div>
                                <div class="text-xs text-gray-500">
                                    {{ order.customer_phone }}
                                    <span v-if="order.is_guest" class="ml-1 text-gray-400">· guest</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">{{ order.items_count }}</td>
                            <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-white">
                                {{ money(order.grand_total) }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(order.status)">
                                    {{ order.status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-medium" :class="paymentClass(order.payment_status)">
                                    {{ order.payment_status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ order.placed_at }}</td>
                        </tr>

                        <tr v-if="orders.data.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-gray-500">No orders match these filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="orders.links.length > 3" class="mt-4 flex flex-wrap gap-1">
                <component
                    :is="link.url ? Link : 'span'"
                    v-for="link in orders.links"
                    :key="link.label"
                    :href="link.url"
                    class="rounded-lg border px-3 py-1.5 text-sm"
                    :class="link.active
                        ? 'border-brand-600 bg-brand-600 text-white'
                        : 'border-gray-300 text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700'"
                    v-html="link.label"
                />
            </div>
        </main>
    </AdminLayout>
</template>

<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { debounce } from '@/utils/debounce'
import AdminLayout from '../Components/AdminLayout.vue'
import PageHeader from '@/Components/Admin/PageHeader.vue'
import FlashMessages from '@/Components/Admin/FlashMessages.vue'
import StatCard from '../Components/StatCard.vue'

const props = defineProps({
    customers: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, required: true },
})

const search = ref(props.filters.search ?? '')
const status = ref(props.filters.status ?? '')
const from = ref(props.filters.from ?? '')
const to = ref(props.filters.to ?? '')

const refresh = debounce(() => {
    router.get(
        route('admin.customers.index'),
        {
            filter: {
                search: search.value || undefined,
                status: status.value || undefined,
                from: from.value || undefined,
                to: to.value || undefined,
            },
        },
        { preserveState: true, replace: true },
    )
}, 300)

watch([search, status, from, to], refresh)

const money = (n) => '$' + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2 })

const field = 'rounded-lg border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600 dark:bg-gray-700 dark:border-gray-600 dark:text-white'
</script>

<template>
    <Head title="Customers" />

    <AdminLayout>
        <main class="p-4 pt-20 md:ml-64">
            <FlashMessages />

            <PageHeader title="Customers" subtitle="Accounts, order history and lifetime value" />

            <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard label="Total customers" :value="summary.total" />
                <StatCard label="New this month" :value="summary.new_this_month" />
                <StatCard label="Active" :value="summary.active" tone="positive" />
                <StatCard label="Have ordered" :value="summary.with_orders" />
            </div>

            <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <input v-model="search" type="search" placeholder="Name, email, phone…" :class="field" />

                <select v-model="status" :class="field">
                    <option value="">All accounts</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>

                <input v-model="from" type="date" :class="field" title="Joined from" />
                <input v-model="to" type="date" :class="field" title="Joined to" />
            </div>

            <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Phone</th>
                            <th class="px-4 py-3 text-center">Orders</th>
                            <th class="px-4 py-3 text-right">Lifetime value</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Last order</th>
                            <th class="px-4 py-3">Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="customer in customers.data"
                            :key="customer.id"
                            class="border-t border-gray-200 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700"
                        >
                            <td class="px-4 py-3">
                                <Link
                                    :href="route('admin.customers.show', customer.id)"
                                    class="font-medium text-brand-600 hover:underline dark:text-brand-400"
                                >
                                    {{ customer.name }}
                                </Link>
                                <div class="text-xs text-gray-500">{{ customer.email }}</div>
                            </td>
                            <td class="px-4 py-3">{{ customer.phone ?? '—' }}</td>
                            <td class="px-4 py-3 text-center">{{ customer.orders_count }}</td>
                            <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-white">
                                {{ money(customer.lifetime_value) }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full px-2 py-1 text-xs font-medium"
                                    :class="customer.is_active
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
                                        : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                                >
                                    {{ customer.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ customer.last_order_at ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ customer.joined_at }}</td>
                        </tr>

                        <tr v-if="customers.data.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-gray-500">No customers match these filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="customers.links.length > 3" class="mt-4 flex flex-wrap gap-1">
                <component
                    :is="link.url ? Link : 'span'"
                    v-for="link in customers.links"
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

<script setup>
import { computed } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '../Components/AdminLayout.vue'
import PageHeader from '@/Components/Admin/PageHeader.vue'
import FlashMessages from '@/Components/Admin/FlashMessages.vue'
import StatCard from '../Components/StatCard.vue'

const props = defineProps({
    customer: { type: Object, required: true },
    stats: { type: Object, required: true },
    orders: { type: Array, required: true },
})

const page = usePage()
const canUpdate = computed(() => (page.props.auth?.user?.permissions ?? []).includes('update customer'))

const toggleActive = () => {
    const action = props.customer.is_active ? 'Deactivate' : 'Activate'
    if (!confirm(`${action} ${props.customer.name}'s account?`)) return

    router.put(route('admin.customers.active', props.customer.id), {}, { preserveScroll: true })
}

const money = (n) => '$' + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2 })

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

const card = 'rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800'
</script>

<template>
    <Head :title="customer.name" />

    <AdminLayout>
        <main class="p-4 pt-20 md:ml-64">
            <FlashMessages />

            <PageHeader :title="customer.name" :subtitle="`Customer since ${customer.joined_at}`">
                <template #actions>
                    <Link
                        :href="route('admin.customers.index')"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        Back
                    </Link>
                    <button
                        v-if="canUpdate"
                        type="button"
                        class="rounded-lg border px-3 py-2 text-sm"
                        :class="customer.is_active
                            ? 'border-red-300 text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/30'
                            : 'border-green-300 text-green-700 hover:bg-green-50 dark:border-green-800 dark:text-green-400 dark:hover:bg-green-900/30'"
                        @click="toggleActive"
                    >
                        {{ customer.is_active ? 'Deactivate account' : 'Activate account' }}
                    </button>
                </template>
            </PageHeader>

            <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard label="Orders" :value="stats.orders_count" />
                <StatCard label="Lifetime value" :value="money(stats.lifetime_value)" hint="Paid orders only" tone="positive" />
                <StatCard label="Average order" :value="money(stats.average_order)" />
                <StatCard label="Last order" :value="stats.last_order_at ?? '—'" />
            </div>

            <div class="grid gap-5 lg:grid-cols-3">
                <!-- Left: order history -->
                <div class="lg:col-span-2">
                    <div :class="card">
                        <h3 class="mb-4 font-semibold text-gray-900 dark:text-white">Recent orders</h3>

                        <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                            <thead class="text-xs uppercase text-gray-500">
                                <tr>
                                    <th class="pb-2">Order</th>
                                    <th class="pb-2 text-center">Items</th>
                                    <th class="pb-2 text-right">Total</th>
                                    <th class="pb-2">Status</th>
                                    <th class="pb-2">Payment</th>
                                    <th class="pb-2">Placed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="order in orders" :key="order.id" class="border-t border-gray-100 dark:border-gray-700">
                                    <td class="py-3">
                                        <Link
                                            :href="route('admin.orders.show', order.id)"
                                            class="font-medium text-brand-600 hover:underline dark:text-brand-400"
                                        >
                                            {{ order.order_number }}
                                        </Link>
                                    </td>
                                    <td class="py-3 text-center">{{ order.items_count }}</td>
                                    <td class="py-3 text-right font-medium text-gray-900 dark:text-white">
                                        {{ money(order.grand_total) }}
                                    </td>
                                    <td class="py-3">
                                        <span class="rounded-full px-2 py-1 text-xs font-medium" :class="statusClass(order.status)">
                                            {{ order.status_label }}
                                        </span>
                                    </td>
                                    <td class="py-3">
                                        <span class="rounded-full px-2 py-1 text-xs font-medium" :class="paymentClass(order.payment_status)">
                                            {{ order.payment_status }}
                                        </span>
                                    </td>
                                    <td class="py-3 text-xs text-gray-500">{{ order.placed_at }}</td>
                                </tr>

                                <tr v-if="orders.length === 0">
                                    <td colspan="6" class="py-10 text-center text-gray-500">No orders yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Right: profile and addresses -->
                <div class="space-y-5">
                    <div :class="card">
                        <h3 class="mb-4 font-semibold text-gray-900 dark:text-white">Profile</h3>

                        <dl class="space-y-3 text-sm">
                            <div>
                                <dt class="text-xs uppercase text-gray-500">Email</dt>
                                <dd class="mt-0.5 text-gray-900 dark:text-white">
                                    {{ customer.email }}
                                    <span
                                        class="ml-1 rounded-full px-2 py-0.5 text-[11px] font-medium"
                                        :class="customer.email_verified
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
                                            : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'"
                                    >
                                        {{ customer.email_verified ? 'verified' : 'unverified' }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase text-gray-500">Phone</dt>
                                <dd class="mt-0.5 text-gray-900 dark:text-white">{{ customer.phone ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase text-gray-500">Account</dt>
                                <dd class="mt-0.5">
                                    <span
                                        class="rounded-full px-2 py-1 text-xs font-medium"
                                        :class="customer.is_active
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
                                            : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                                    >
                                        {{ customer.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase text-gray-500">Joined</dt>
                                <dd class="mt-0.5 text-gray-900 dark:text-white">{{ customer.joined_at }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase text-gray-500">Last login</dt>
                                <dd class="mt-0.5 text-gray-900 dark:text-white">{{ customer.last_login_at ?? '—' }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div :class="card">
                        <h3 class="mb-4 font-semibold text-gray-900 dark:text-white">Addresses</h3>

                        <p v-if="customer.addresses.length === 0" class="text-sm text-gray-500">No saved addresses.</p>

                        <ul v-else class="space-y-4">
                            <li
                                v-for="address in customer.addresses"
                                :key="address.id"
                                class="rounded-lg border border-gray-100 p-3 text-sm dark:border-gray-700"
                            >
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-gray-900 dark:text-white">{{ address.label ?? 'Address' }}</span>
                                    <span
                                        v-if="address.is_default_shipping"
                                        class="rounded bg-brand-50 px-1.5 py-0.5 text-[10px] font-medium text-brand-800 dark:bg-brand-900/40 dark:text-brand-200"
                                    >
                                        shipping
                                    </span>
                                    <span
                                        v-if="address.is_default_billing"
                                        class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300"
                                    >
                                        billing
                                    </span>
                                </div>
                                <div class="mt-1 text-gray-600 dark:text-gray-300">{{ address.full_address }}</div>
                                <div class="mt-1 text-xs text-gray-500">
                                    {{ address.receiver_name }}<span v-if="address.phone"> · {{ address.phone }}</span>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </AdminLayout>
</template>

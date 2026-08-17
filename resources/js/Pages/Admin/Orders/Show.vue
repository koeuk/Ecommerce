<script setup>
import { ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '../Components/AdminLayout.vue'
import PageHeader from '@/Components/Admin/PageHeader.vue'
import FlashMessages from '@/Components/Admin/FlashMessages.vue'

const props = defineProps({
    order: { type: Object, required: true },
})

// The server sends only the transitions OrderStatus permits, so the UI never
// has to know the state machine.
const statusForm = useForm({ status: '', note: '' })
const noteForm = useForm({ admin_note: props.order.admin_note ?? '' })
const confirming = ref(null)

const askTransition = (target) => {
    confirming.value = target
    statusForm.status = target.value
    statusForm.note = ''
}

const submitTransition = () => {
    statusForm.put(route('admin.orders.status', props.order.id), {
        preserveScroll: true,
        onSuccess: () => { confirming.value = null },
    })
}

const markPaid = () => {
    useForm({}).put(route('admin.orders.paid', props.order.id), { preserveScroll: true })
}

const saveNote = () => {
    noteForm.put(route('admin.orders.note', props.order.id), { preserveScroll: true })
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

const card = 'rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800'
const field = 'w-full rounded-lg border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600 dark:bg-gray-700 dark:border-gray-600 dark:text-white'
const addr = (a) => [a?.address_line1, a?.address_line2, a?.city, a?.state, a?.postal_code].filter(Boolean).join(', ')
</script>

<template>
    <Head :title="`Order ${order.order_number}`" />

    <AdminLayout>
        <main class="p-4 pt-20 md:ml-64">
            <FlashMessages />

            <PageHeader :title="order.order_number" :subtitle="`Placed ${order.placed_at}`">
                <template #actions>
                    <Link
                        :href="route('admin.orders.index')"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        Back
                    </Link>
                    <a
                        :href="route('admin.orders.invoice', order.id)"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        Invoice PDF
                    </a>
                    <a
                        :href="route('admin.orders.delivery-note', order.id)"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        Delivery note
                    </a>
                </template>
            </PageHeader>

            <div class="grid gap-5 lg:grid-cols-3">
                <!-- Left: items and totals -->
                <div class="space-y-5 lg:col-span-2">
                    <div :class="card">
                        <h3 class="mb-4 font-semibold text-gray-900 dark:text-white">Items</h3>

                        <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                            <thead class="text-xs uppercase text-gray-500">
                                <tr>
                                    <th class="pb-2">Product</th>
                                    <th class="pb-2 text-center">Qty</th>
                                    <th class="pb-2 text-right">Unit</th>
                                    <th class="pb-2 text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in order.items" :key="item.id" class="border-t border-gray-100 dark:border-gray-700">
                                    <td class="py-3">
                                        <div class="font-medium text-gray-900 dark:text-white">{{ item.product_name }}</div>
                                        <div class="text-xs text-gray-500">
                                            {{ item.sku }}<span v-if="item.variant_label"> · {{ item.variant_label }}</span>
                                        </div>
                                    </td>
                                    <td class="py-3 text-center">{{ item.quantity }}</td>
                                    <td class="py-3 text-right">{{ money(item.unit_price) }}</td>
                                    <td class="py-3 text-right font-medium text-gray-900 dark:text-white">{{ money(item.subtotal) }}</td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="mt-4 space-y-1 border-t border-gray-200 pt-4 text-sm dark:border-gray-700">
                            <div class="flex justify-between"><span>Subtotal</span><span>{{ money(order.subtotal) }}</span></div>
                            <div v-if="order.discount_total > 0" class="flex justify-between text-green-600">
                                <span>Discount <span v-if="order.coupon" class="text-xs">({{ order.coupon }})</span></span>
                                <span>−{{ money(order.discount_total) }}</span>
                            </div>
                            <div v-if="order.tax_total > 0" class="flex justify-between"><span>Tax</span><span>{{ money(order.tax_total) }}</span></div>
                            <div class="flex justify-between">
                                <span>Shipping <span v-if="order.shipping_method" class="text-xs text-gray-500">({{ order.shipping_method }})</span></span>
                                <span>{{ money(order.shipping_fee) }}</span>
                            </div>
                            <div class="flex justify-between border-t border-gray-200 pt-2 text-base font-bold text-gray-900 dark:border-gray-700 dark:text-white">
                                <span>Total</span><span>{{ money(order.grand_total) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Timeline -->
                    <div :class="card">
                        <h3 class="mb-4 font-semibold text-gray-900 dark:text-white">History</h3>
                        <ol class="relative space-y-4 border-l border-gray-200 pl-5 dark:border-gray-700">
                            <li v-for="entry in order.timeline" :key="entry.id">
                                <span class="absolute -left-1.5 mt-1.5 h-3 w-3 rounded-full bg-brand-600"></span>
                                <div class="text-sm text-gray-900 dark:text-white">
                                    <span v-if="entry.from">{{ entry.from }} → </span>{{ entry.to }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ entry.at }}<span v-if="entry.author"> · {{ entry.author }}</span>
                                </div>
                                <p v-if="entry.note" class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ entry.note }}</p>
                            </li>
                        </ol>
                    </div>
                </div>

                <!-- Right: status, customer, notes -->
                <div class="space-y-5">
                    <div :class="card">
                        <h3 class="mb-3 font-semibold text-gray-900 dark:text-white">Status</h3>

                        <div class="mb-4 flex flex-wrap gap-2">
                            <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusClass(order.status)">
                                {{ order.status_label }}
                            </span>
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                {{ order.payment_status }}
                            </span>
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                {{ order.fulfillment_status }}
                            </span>
                        </div>

                        <div v-if="order.allowed_transitions.length" class="space-y-2">
                            <button
                                v-for="t in order.allowed_transitions"
                                :key="t.value"
                                type="button"
                                class="w-full rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700"
                                @click="askTransition(t)"
                            >
                                Mark as {{ t.label }}
                            </button>
                        </div>
                        <p v-else class="text-sm text-gray-500">This order has reached a final state.</p>

                        <button
                            v-if="order.payment_status !== 'paid'"
                            type="button"
                            class="mt-2 w-full rounded-lg border border-green-600 px-3 py-2 text-sm font-medium text-green-700 hover:bg-green-50 dark:text-green-400 dark:hover:bg-gray-700"
                            @click="markPaid"
                        >
                            Mark payment received
                        </button>
                    </div>

                    <div :class="card">
                        <h3 class="mb-3 font-semibold text-gray-900 dark:text-white">Customer</h3>
                        <dl class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                            <div><dt class="text-xs uppercase text-gray-400">Name</dt><dd>{{ order.customer_name }}</dd></div>
                            <div><dt class="text-xs uppercase text-gray-400">Phone</dt><dd>{{ order.customer_phone }}</dd></div>
                            <div v-if="order.customer_email"><dt class="text-xs uppercase text-gray-400">Email</dt><dd>{{ order.customer_email }}</dd></div>
                            <div>
                                <dt class="text-xs uppercase text-gray-400">Account</dt>
                                <dd>{{ order.account ? order.account.name : 'Guest checkout' }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div :class="card">
                        <h3 class="mb-3 font-semibold text-gray-900 dark:text-white">Delivery address</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            {{ order.shipping_address?.receiver_name }}<br>
                            {{ addr(order.shipping_address) }}<br>
                            {{ order.shipping_address?.phone }}
                        </p>
                        <p v-if="order.customer_note" class="mt-3 rounded bg-amber-50 p-2 text-sm text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                            <strong>Customer note:</strong> {{ order.customer_note }}
                        </p>
                    </div>

                    <div :class="card">
                        <h3 class="mb-3 font-semibold text-gray-900 dark:text-white">Internal note</h3>
                        <textarea v-model="noteForm.admin_note" rows="4" :class="field" placeholder="Not shown to the customer"></textarea>
                        <button
                            type="button"
                            class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                            :disabled="noteForm.processing"
                            @click="saveNote"
                        >
                            Save note
                        </button>
                    </div>
                </div>
            </div>

            <!-- Transition confirmation -->
            <div v-if="confirming" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl dark:bg-gray-800">
                    <h3 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">
                        Mark as {{ confirming.label }}?
                    </h3>
                    <p v-if="confirming.value === 'cancelled'" class="mb-3 text-sm text-amber-700 dark:text-amber-400">
                        Cancelling returns every item on this order to stock.
                    </p>

                    <textarea
                        v-model="statusForm.note"
                        rows="3"
                        :class="field"
                        placeholder="Optional note for the history"
                    ></textarea>

                    <div class="mt-4 flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm dark:border-gray-600 dark:text-gray-200"
                            @click="confirming = null"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
                            :disabled="statusForm.processing"
                            @click="submitTransition"
                        >
                            Confirm
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </AdminLayout>
</template>

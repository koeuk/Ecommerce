<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const page = usePage()

const permissions = computed(() => page.props.auth?.user?.permissions ?? [])
const can = (permission) => permissions.value.includes(permission)

/** Marks the active item, including nested pages like products/create. */
const isActive = (pattern) => {
    const path = page.url.split('?')[0]
    return path === pattern || path.startsWith(`${pattern}/`)
}

const sections = computed(() => [
    {
        heading: null,
        items: [
            { label: 'Dashboard', href: route('admin.dashboard'), path: '/admin/dashboard', icon: 'grid', show: true },
        ],
    },
    {
        heading: 'Catalog',
        items: [
            { label: 'Products', href: route('admin.products.index'), path: '/admin/products', icon: 'box', show: can('view product') },
            { label: 'Categories', href: route('admin.categories.index'), path: '/admin/categories', icon: 'folder', show: can('view category') },
            { label: 'Brands', href: route('admin.brands.index'), path: '/admin/brands', icon: 'tag', show: can('view brand') },
            { label: 'Inventory', href: route('admin.inventory.index'), path: '/admin/inventory', icon: 'layers', show: can('view inventory') },
        ],
    },
    {
        heading: 'Sales',
        items: [
            { label: 'Orders', href: route('admin.orders.index'), path: '/admin/orders', icon: 'cart', show: can('view order') },
            { label: 'Customers', href: null, path: '/admin/customers', icon: 'users', show: can('view customer'), soon: true },
        ],
    },
])

const icons = {
    grid: 'M4 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm0 8a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zm8-8a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zm0 8a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z',
    box: 'M3 6l7-3 7 3v8l-7 3-7-3V6zm7 0L4.5 4 10 1.8 15.5 4 10 6zm0 1.7l6-2.2v6.9l-6 2.6V7.7z',
    folder: 'M2 5a2 2 0 012-2h3.5l1.5 2H16a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V5z',
    tag: 'M2 5a3 3 0 013-3h4.6a2 2 0 011.4.6l6.4 6.4a2 2 0 010 2.8l-4.6 4.6a2 2 0 01-2.8 0L2.6 10A2 2 0 012 8.6V5zm4 2a1 1 0 100-2 1 1 0 000 2z',
    layers: 'M10 2l8 4-8 4-8-4 8-4zm8 8l-8 4-8-4m16 4l-8 4-8-4',
    cart: 'M3 3h2l.6 3M7 13h9l2-7H5.6M7 13l-1.4 3H17M8 18a1 1 0 100 2 1 1 0 000-2zm8 0a1 1 0 100 2 1 1 0 000-2z',
    users: 'M13 6a3 3 0 11-6 0 3 3 0 016 0zM3 18a7 7 0 0114 0H3z',
}
</script>

<template>
    <aside
        id="drawer-navigation"
        aria-label="Sidebar"
        class="fixed left-0 top-0 z-40 h-screen w-64 -translate-x-full border-r border-gray-200 bg-white pt-14 transition-transform dark:border-gray-700 dark:bg-gray-800 md:translate-x-0"
    >
        <div class="h-full overflow-y-auto px-3 py-5">
            <template v-for="(section, si) in sections" :key="si">
                <p
                    v-if="section.heading"
                    class="mb-1 mt-5 px-2 text-[11px] font-semibold uppercase tracking-wider text-gray-400"
                >
                    {{ section.heading }}
                </p>

                <ul class="space-y-1">
                    <li v-for="item in section.items.filter((i) => i.show)" :key="item.label">
                        <!-- Built pages link; the rest render as disabled placeholders -->
                        <Link
                            v-if="item.href"
                            :href="item.href"
                            class="group flex items-center rounded-lg p-2 text-sm font-medium transition"
                            :class="isActive(item.path)
                                ? 'bg-brand-50 text-brand-800 dark:bg-brand-900/40 dark:text-brand-200'
                                : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'"
                        >
                            <svg
                                class="size-5 shrink-0 transition"
                                :class="isActive(item.path) ? 'text-brand-700 dark:text-brand-300' : 'text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-200'"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path :d="icons[item.icon]" />
                            </svg>
                            <span class="ml-3">{{ item.label }}</span>
                        </Link>

                        <span
                            v-else
                            class="flex cursor-not-allowed items-center rounded-lg p-2 text-sm font-medium text-gray-400 dark:text-gray-600"
                            :title="`${item.label} ships in a later phase`"
                        >
                            <svg class="size-5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path :d="icons[item.icon]" />
                            </svg>
                            <span class="ml-3">{{ item.label }}</span>
                            <span class="ml-auto rounded bg-gray-100 px-1.5 py-0.5 text-[10px] dark:bg-gray-700">
                                soon
                            </span>
                        </span>
                    </li>
                </ul>
            </template>
        </div>
    </aside>
</template>

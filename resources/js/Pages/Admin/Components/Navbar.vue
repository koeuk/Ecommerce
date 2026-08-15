<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'

const page = usePage()
const user = computed(() => page.props.auth?.user)

const menuOpen = ref(false)
const sidebarOpen = ref(false)
const menuRef = ref(null)

const initials = computed(() =>
    (user.value?.name ?? '?')
        .split(' ')
        .map((part) => part.charAt(0))
        .slice(0, 2)
        .join('')
        .toUpperCase(),
)

const roleLabel = computed(() => (user.value?.roles ?? []).join(', ') || 'staff')

const logout = () => router.post(route('admin.logout'))

const toggleSidebar = () => {
    sidebarOpen.value = !sidebarOpen.value
    document.getElementById('drawer-navigation')?.classList.toggle('-translate-x-full')
}

const onClickOutside = (event) => {
    if (menuRef.value && !menuRef.value.contains(event.target)) {
        menuOpen.value = false
    }
}

onMounted(() => document.addEventListener('click', onClickOutside))
onUnmounted(() => document.removeEventListener('click', onClickOutside))
</script>

<template>
    <nav
        class="fixed left-0 right-0 top-0 z-50 border-b border-gray-200 bg-white px-4 py-2.5 dark:border-gray-700 dark:bg-gray-800"
    >
        <div class="flex flex-wrap items-center justify-between">
            <div class="flex items-center justify-start">
                <button
                    type="button"
                    aria-label="Toggle sidebar"
                    class="mr-2 cursor-pointer rounded-lg p-2 text-gray-600 hover:bg-gray-100 focus:ring-2 focus:ring-gray-100 dark:text-gray-400 dark:hover:bg-gray-700 md:hidden"
                    @click="toggleSidebar"
                >
                    <svg class="size-6" fill="currentColor" viewBox="0 0 20 20">
                        <path
                            fill-rule="evenodd"
                            d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </button>

                <Link :href="route('admin.dashboard')" class="flex items-center">
                    <span
                        class="mr-2.5 flex size-8 items-center justify-center rounded-lg bg-brand-700 text-sm font-bold text-white"
                    >
                        V
                    </span>
                    <span class="self-center whitespace-nowrap text-lg font-semibold dark:text-white">
                        Veha Admin
                    </span>
                </Link>
            </div>

            <div ref="menuRef" class="relative flex items-center gap-2">
                <span class="hidden text-xs text-gray-400 sm:block">{{ roleLabel }}</span>

                <button
                    type="button"
                    class="flex rounded-full text-sm focus:ring-4 focus:ring-gray-200 dark:focus:ring-gray-600"
                    aria-label="Open user menu"
                    @click.stop="menuOpen = !menuOpen"
                >
                    <img
                        v-if="user?.avatar"
                        :src="user.avatar"
                        alt=""
                        class="size-9 rounded-full object-cover"
                    />
                    <span
                        v-else
                        class="flex size-9 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-800 dark:bg-brand-900 dark:text-brand-200"
                    >
                        {{ initials }}
                    </span>
                </button>

                <div
                    v-show="menuOpen"
                    class="absolute right-0 top-12 z-50 w-56 divide-y divide-gray-100 rounded-lg bg-white shadow-lg ring-1 ring-black/5 dark:divide-gray-600 dark:bg-gray-700"
                >
                    <div class="px-4 py-3">
                        <p class="truncate text-sm font-medium text-gray-900 dark:text-white">
                            {{ user?.name }}
                        </p>
                        <p class="truncate text-sm text-gray-500 dark:text-gray-400">
                            {{ user?.email }}
                        </p>
                    </div>
                    <ul class="py-1">
                        <li>
                            <Link
                                :href="route('profile.edit')"
                                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-600"
                            >
                                My profile
                            </Link>
                        </li>
                    </ul>
                    <ul class="py-1">
                        <li>
                            <button
                                type="button"
                                class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-gray-100 dark:hover:bg-gray-600"
                                @click="logout"
                            >
                                Sign out
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
</template>

<script setup>
import { ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import InputError from '@/Components/InputError.vue'

const showPassword = ref(false)
const slide = ref(0)

const slides = [
    {
        title: 'One Platform to Run',
        accent: 'Your Whole Store',
        body: 'Products, variants, stock and orders in a single place — built for computers, accessories and watches.',
    },
    {
        title: 'Every Variant,',
        accent: 'Priced and Tracked',
        body: 'RAM, storage, case size and strap — each combination carries its own SKU, price and stock level.',
    },
    {
        title: 'Bilingual by Default,',
        accent: 'English and Khmer',
        body: 'Product names, categories and descriptions are stored per language and served in the customer’s locale.',
    },
]

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

const submit = () => {
    form.post(route('admin.login.post'), {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <Head title="Sign in" />

    <div class="flex min-h-screen items-center justify-center bg-gray-100 p-4 dark:bg-gray-950 sm:p-8">
        <div
            class="grid w-full max-w-5xl overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800 lg:grid-cols-2"
        >
            <!-- Left: brand panel -->
            <div
                class="relative hidden flex-col justify-end bg-gradient-to-br from-gray-900 via-gray-900 to-amber-950 p-10 lg:flex"
            >
                <div
                    class="absolute left-10 top-10 flex h-11 w-11 items-center justify-center rounded-xl bg-amber-500 text-lg font-bold text-gray-900"
                >
                    V
                </div>

                <div class="mb-10">
                    <h2 class="text-3xl font-semibold leading-tight text-white">
                        {{ slides[slide].title }}<br />
                        <span class="text-amber-400">{{ slides[slide].accent }}</span>
                    </h2>
                    <p class="mt-4 max-w-sm text-sm leading-relaxed text-gray-400">
                        {{ slides[slide].body }}
                    </p>
                </div>

                <div class="flex gap-2">
                    <button
                        v-for="(s, i) in slides"
                        :key="i"
                        type="button"
                        class="h-1.5 rounded-full transition-all"
                        :class="i === slide ? 'w-6 bg-amber-400' : 'w-1.5 bg-gray-600 hover:bg-gray-500'"
                        :aria-label="`Slide ${i + 1}`"
                        @click="slide = i"
                    />
                </div>
            </div>

            <!-- Right: sign-in form -->
            <div class="flex flex-col p-8 sm:p-12">
                <div class="mb-10 flex items-center justify-between">
                    <div
                        class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-500 text-sm font-bold text-gray-900 lg:hidden"
                    >
                        V
                    </div>
                    <span class="ml-auto text-xs text-gray-400">Staff access only</span>
                </div>

                <div class="mx-auto w-full max-w-sm flex-1">
                    <div class="mb-8 text-center">
                        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Welcome back</h1>
                        <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">
                            Please enter your details to sign in
                        </p>
                    </div>

                    <form class="space-y-5" @submit.prevent="submit">
                        <div>
                            <label
                                for="email"
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Email
                            </label>
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="you@example.com"
                                class="block w-full rounded-lg border-gray-300 text-sm shadow-sm transition focus:border-amber-500 focus:ring-amber-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder-gray-500"
                                :class="form.errors.email ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : ''"
                            />
                            <InputError class="mt-1.5" :message="form.errors.email" />
                        </div>

                        <div>
                            <label
                                for="password"
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Password
                            </label>
                            <div class="relative">
                                <input
                                    id="password"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    required
                                    autocomplete="current-password"
                                    placeholder="minimum 8 characters"
                                    class="block w-full rounded-lg border-gray-300 pr-11 text-sm shadow-sm transition focus:border-amber-500 focus:ring-amber-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder-gray-500"
                                    :class="form.errors.password ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : ''"
                                />
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 transition hover:text-gray-600 dark:hover:text-gray-300"
                                    :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                    @click="showPassword = !showPassword"
                                >
                                    <svg
                                        v-if="!showPassword"
                                        class="h-5 w-5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="1.6"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M2.036 12.322a1 1 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178a1 1 0 010 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178z"
                                        />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <svg
                                        v-else
                                        class="h-5 w-5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="1.6"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.774 3.162 10.066 7.5a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243"
                                        />
                                    </svg>
                                </button>
                            </div>
                            <InputError class="mt-1.5" :message="form.errors.password" />
                        </div>

                        <div class="flex items-center justify-between">
                            <label class="inline-flex items-center gap-2">
                                <input
                                    v-model="form.remember"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-300 text-amber-500 focus:ring-amber-500"
                                />
                                <span class="text-sm text-gray-600 dark:text-gray-400">Remember me</span>
                            </label>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="flex w-full items-center justify-center gap-2 rounded-lg bg-amber-500 px-5 py-2.5 text-sm font-semibold text-gray-900 transition hover:bg-amber-400 focus:ring-4 focus:ring-amber-200 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <svg v-if="form.processing" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                            </svg>
                            {{ form.processing ? 'Signing in…' : 'Sign In' }}
                            <span v-if="!form.processing">→</span>
                        </button>

                        <div class="text-center">
                            <Link
                                :href="route('password.request')"
                                class="text-sm font-medium text-gray-600 underline underline-offset-4 transition hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                            >
                                Forgot password?
                            </Link>
                        </div>
                    </form>
                </div>

                <div class="mt-10 flex items-center justify-between text-xs text-gray-400">
                    <span>© {{ new Date().getFullYear() }} Veha Electronics</span>
                    <span>Too many attempts will lock sign-in</span>
                </div>
            </div>
        </div>
    </div>
</template>

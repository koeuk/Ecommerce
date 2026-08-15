<script setup>
import { ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { Eye, EyeOff, ArrowRight, Loader2 } from 'lucide-vue-next'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
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

    <div class="flex min-h-screen items-center justify-center bg-muted p-4 sm:p-8">
        <div
            class="grid w-full max-w-5xl overflow-hidden rounded-xl border bg-card shadow-xl lg:grid-cols-2"
        >
            <!-- Left: brand panel -->
            <div
                class="relative hidden flex-col justify-end bg-gradient-to-br from-zinc-900 via-zinc-900 to-amber-950 p-10 lg:flex"
            >
                <div
                    class="absolute left-10 top-10 flex size-11 items-center justify-center rounded-lg bg-primary text-lg font-bold text-primary-foreground"
                >
                    V
                </div>

                <div class="mb-10">
                    <h2 class="text-3xl font-semibold leading-tight text-white">
                        {{ slides[slide].title }}<br />
                        <span class="text-primary">{{ slides[slide].accent }}</span>
                    </h2>
                    <p class="mt-4 max-w-sm text-sm leading-relaxed text-zinc-400">
                        {{ slides[slide].body }}
                    </p>
                </div>

                <div class="flex gap-2">
                    <button
                        v-for="(s, i) in slides"
                        :key="i"
                        type="button"
                        class="h-1.5 rounded-full transition-all"
                        :class="i === slide ? 'w-6 bg-primary' : 'w-1.5 bg-zinc-600 hover:bg-zinc-500'"
                        :aria-label="`Slide ${i + 1}`"
                        @click="slide = i"
                    />
                </div>
            </div>

            <!-- Right: sign-in form -->
            <div class="flex flex-col p-8 sm:p-12">
                <div class="mb-10 flex items-center justify-between">
                    <div
                        class="flex size-9 items-center justify-center rounded-lg bg-primary text-sm font-bold text-primary-foreground lg:hidden"
                    >
                        V
                    </div>
                    <span class="ml-auto text-xs text-muted-foreground">Staff access only</span>
                </div>

                <div class="mx-auto w-full max-w-sm flex-1">
                    <div class="mb-8 text-center">
                        <h1 class="text-2xl font-semibold tracking-tight">Welcome back</h1>
                        <p class="mt-1.5 text-sm text-muted-foreground">
                            Please enter your details to sign in
                        </p>
                    </div>

                    <form class="space-y-5" @submit.prevent="submit">
                        <div class="space-y-2">
                            <Label for="email">Email</Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="you@example.com"
                                :class="form.errors.email ? 'border-destructive focus-visible:ring-destructive' : ''"
                            />
                            <InputError :message="form.errors.email" />
                        </div>

                        <div class="space-y-2">
                            <Label for="password">Password</Label>
                            <div class="relative">
                                <Input
                                    id="password"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    required
                                    autocomplete="current-password"
                                    placeholder="minimum 8 characters"
                                    class="pr-10"
                                    :class="form.errors.password ? 'border-destructive focus-visible:ring-destructive' : ''"
                                />
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-0 flex items-center px-3 text-muted-foreground transition-colors hover:text-foreground"
                                    :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                    @click="showPassword = !showPassword"
                                >
                                    <EyeOff v-if="showPassword" class="size-4" />
                                    <Eye v-else class="size-4" />
                                </button>
                            </div>
                            <InputError :message="form.errors.password" />
                        </div>

                        <div class="flex items-center">
                            <label class="inline-flex items-center gap-2">
                                <input
                                    v-model="form.remember"
                                    type="checkbox"
                                    class="size-4 rounded border-input text-primary focus:ring-2 focus:ring-ring"
                                />
                                <span class="text-sm text-muted-foreground">Remember me</span>
                            </label>
                        </div>

                        <Button type="submit" class="w-full" :disabled="form.processing">
                            <Loader2 v-if="form.processing" class="animate-spin" />
                            {{ form.processing ? 'Signing in…' : 'Sign In' }}
                            <ArrowRight v-if="!form.processing" />
                        </Button>

                        <div class="text-center">
                            <Button variant="link" as-child class="text-muted-foreground">
                                <Link :href="route('password.request')">Forgot password?</Link>
                            </Button>
                        </div>
                    </form>
                </div>

                <div class="mt-10 flex items-center justify-between text-xs text-muted-foreground">
                    <span>© {{ new Date().getFullYear() }} Veha Electronics</span>
                    <span>Too many attempts will lock sign-in</span>
                </div>
            </div>
        </div>
    </div>
</template>

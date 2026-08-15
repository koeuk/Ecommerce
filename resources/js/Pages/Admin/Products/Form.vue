<script setup>
import { computed, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '../Components/AdminLayout.vue'
import PageHeader from '@/Components/Admin/PageHeader.vue'
import FlashMessages from '@/Components/Admin/FlashMessages.vue'
import TranslatableInput from '@/Components/Admin/TranslatableInput.vue'
import InputError from '@/Components/InputError.vue'
import VariantMatrix from './VariantMatrix.vue'
import SpecBuilder from './SpecBuilder.vue'

const props = defineProps({
    product: { type: Object, default: null },
    options: { type: Object, required: true },
})

const isEdit = computed(() => Boolean(props.product))
const tab = ref('general')

const tabs = [
    { key: 'general', label: 'General' },
    { key: 'variants', label: 'Variants' },
    { key: 'specs', label: 'Specifications' },
    { key: 'images', label: 'Images' },
    { key: 'seo', label: 'SEO' },
]

const form = useForm({
    title: props.product?.title ?? { en: '' },
    slug: props.product?.slug ?? '',
    sku: props.product?.sku ?? '',
    short_description: props.product?.short_description ?? {},
    description: props.product?.description ?? {},
    meta_title: props.product?.meta_title ?? {},
    meta_description: props.product?.meta_description ?? {},
    brand_id: props.product?.brand_id ?? null,
    category_id: props.product?.category_id ?? null,
    price: props.product?.price ?? 0,
    compare_at_price: props.product?.compare_at_price ?? null,
    cost_price: props.product?.cost_price ?? null,
    status: props.product?.status ?? 'draft',
    condition: props.product?.condition ?? 'new',
    is_featured: props.product?.is_featured ?? false,
    warranty_months: props.product?.warranty_months ?? null,
    release_year: props.product?.release_year ?? null,
    weight: props.product?.weight ?? null,
    length: props.product?.length ?? null,
    width: props.product?.width ?? null,
    height: props.product?.height ?? null,
    tags: props.product?.tags ?? [],
    variants: props.product?.variants ? JSON.parse(JSON.stringify(props.product.variants)) : [],
    specifications: props.product?.specifications ? JSON.parse(JSON.stringify(props.product.specifications)) : [],
    images: [],
})

const submit = () => {
    if (isEdit.value) {
        // Multipart requests need the method spoofed.
        form.transform((data) => ({ ...data, _method: 'put' }))
            .post(route('admin.products.update', props.product.id), {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => { form.images = [] },
            })
    } else {
        form.post(route('admin.products.store'), { forceFormData: true })
    }
}

const deleteImage = (image) => {
    router.delete(route('admin.products.images.destroy', [props.product.id, image.id]), {
        preserveScroll: true,
    })
}

const makePrimary = (image) => {
    router.put(route('admin.products.images.primary', [props.product.id, image.id]), {}, {
        preserveScroll: true,
    })
}

// Highlight tabs that contain validation errors
const errorTabs = computed(() => {
    const keys = Object.keys(form.errors)
    return {
        general: keys.some((k) => /^(title|slug|sku|price|compare_at_price|cost_price|status|condition|brand_id|category_id|warranty|release|weight|length|width|height|short_description|description)/.test(k)),
        variants: keys.some((k) => k.startsWith('variants')),
        specs: keys.some((k) => k.startsWith('specifications')),
        images: keys.some((k) => k.startsWith('images')),
        seo: keys.some((k) => k.startsWith('meta')),
    }
})

const field = 'block w-full rounded-lg border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600 dark:bg-gray-700 dark:border-gray-600 dark:text-white'
</script>

<template>
    <Head :title="isEdit ? 'Edit product' : 'Add product'" />

    <AdminLayout>
        <main class="p-4 pt-20 md:ml-64">
            <FlashMessages />

            <PageHeader
                :title="isEdit ? 'Edit product' : 'Add product'"
                :subtitle="isEdit ? `${product.title.en} · ${product.stock_quantity} in stock` : 'Create a new product'"
            />

            <form class="max-w-5xl" @submit.prevent="submit">
                <!-- Tabs -->
                <div class="mb-5 flex flex-wrap gap-1 border-b border-gray-200 dark:border-gray-700">
                    <button
                        v-for="t in tabs"
                        :key="t.key"
                        type="button"
                        class="relative -mb-px border-b-2 px-4 py-2.5 text-sm font-medium transition"
                        :class="tab === t.key
                            ? 'border-brand-700 text-brand-800 dark:border-brand-400 dark:text-brand-300'
                            : 'border-transparent text-gray-500 hover:text-gray-800 dark:hover:text-gray-300'"
                        @click="tab = t.key"
                    >
                        {{ t.label }}
                        <span v-if="errorTabs[t.key]" class="ml-1 text-red-500">•</span>
                    </button>
                </div>

                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <!-- General -->
                    <div v-show="tab === 'general'" class="space-y-5">
                        <TranslatableInput v-model="form.title" name="title" label="Title" required :errors="form.errors" />

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">SKU *</label>
                                <input v-model="form.sku" type="text" :class="field" placeholder="SKU-0001" />
                                <InputError :message="form.errors.sku" class="mt-1" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Slug</label>
                                <input v-model="form.slug" type="text" :class="field" placeholder="Auto-generated if blank" />
                                <InputError :message="form.errors.slug" class="mt-1" />
                            </div>
                        </div>

                        <TranslatableInput
                            v-model="form.short_description"
                            name="short_description"
                            label="Short description"
                            type="textarea"
                            :errors="form.errors"
                        />
                        <TranslatableInput
                            v-model="form.description"
                            name="description"
                            label="Description"
                            type="textarea"
                            :errors="form.errors"
                        />

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Brand</label>
                                <select v-model="form.brand_id" :class="field">
                                    <option :value="null">— None —</option>
                                    <option v-for="b in options.brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Category</label>
                                <select v-model="form.category_id" :class="field">
                                    <option :value="null">— None —</option>
                                    <option v-for="c in options.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Price *</label>
                                <input v-model.number="form.price" type="number" step="0.01" min="0" :class="field" />
                                <InputError :message="form.errors.price" class="mt-1" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Compare at</label>
                                <input v-model.number="form.compare_at_price" type="number" step="0.01" min="0" :class="field" />
                                <InputError :message="form.errors.compare_at_price" class="mt-1" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Cost</label>
                                <input v-model.number="form.cost_price" type="number" step="0.01" min="0" :class="field" />
                            </div>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-4">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                                <select v-model="form.status" :class="field">
                                    <option v-for="s in options.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Condition</label>
                                <select v-model="form.condition" :class="field">
                                    <option v-for="c in options.conditions" :key="c.value" :value="c.value">{{ c.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Warranty (months)</label>
                                <input v-model.number="form.warranty_months" type="number" min="0" :class="field" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Release year</label>
                                <input v-model.number="form.release_year" type="number" min="1990" :class="field" />
                                <InputError :message="form.errors.release_year" class="mt-1" />
                            </div>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-4">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Weight (kg)</label>
                                <input v-model.number="form.weight" type="number" step="0.001" min="0" :class="field" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Length (cm)</label>
                                <input v-model.number="form.length" type="number" step="0.01" min="0" :class="field" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Width (cm)</label>
                                <input v-model.number="form.width" type="number" step="0.01" min="0" :class="field" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Height (cm)</label>
                                <input v-model.number="form.height" type="number" step="0.01" min="0" :class="field" />
                            </div>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Tags</label>
                            <div class="flex flex-wrap gap-1.5">
                                <label
                                    v-for="tag in options.tags"
                                    :key="tag.id"
                                    class="cursor-pointer rounded-full border px-3 py-1 text-xs transition"
                                    :class="form.tags.includes(tag.id)
                                        ? 'border-brand-700 bg-brand-700 text-white'
                                        : 'border-gray-300 text-gray-600 hover:border-brand-600 dark:border-gray-600 dark:text-gray-300'"
                                >
                                    <input v-model="form.tags" type="checkbox" :value="tag.id" class="sr-only" />
                                    {{ tag.name }}
                                </label>
                            </div>
                        </div>

                        <label class="inline-flex items-center gap-2">
                            <input
                                v-model="form.is_featured"
                                type="checkbox"
                                class="h-4 w-4 rounded border-gray-300 text-brand-700 focus:ring-brand-600"
                            />
                            <span class="text-sm text-gray-700 dark:text-gray-300">Featured product</span>
                        </label>
                    </div>

                    <!-- Variants -->
                    <div v-show="tab === 'variants'">
                        <VariantMatrix
                            v-model="form.variants"
                            :attributes="options.attributes"
                            :base-sku="form.sku"
                            :base-price="form.price"
                            :errors="form.errors"
                        />
                    </div>

                    <!-- Specifications -->
                    <div v-show="tab === 'specs'">
                        <SpecBuilder v-model="form.specifications" />
                    </div>

                    <!-- Images -->
                    <div v-show="tab === 'images'" class="space-y-5">
                        <div v-if="isEdit && product.images.length" class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                            <div
                                v-for="image in product.images"
                                :key="image.id"
                                class="group relative overflow-hidden rounded-lg border dark:border-gray-700"
                                :class="image.is_primary ? 'ring-2 ring-brand-600' : ''"
                            >
                                <img :src="image.url" alt="" class="aspect-square w-full object-cover" />
                                <span
                                    v-if="image.is_primary"
                                    class="absolute left-1 top-1 rounded bg-brand-700 px-1.5 py-0.5 text-[10px] font-medium text-white"
                                >
                                    Primary
                                </span>
                                <div
                                    class="absolute inset-x-0 bottom-0 flex justify-between gap-1 bg-black/60 p-1.5 opacity-0 transition group-hover:opacity-100"
                                >
                                    <button
                                        v-if="!image.is_primary"
                                        type="button"
                                        class="text-[11px] text-white hover:underline"
                                        @click="makePrimary(image)"
                                    >
                                        Set primary
                                    </button>
                                    <span v-else />
                                    <button
                                        type="button"
                                        class="text-[11px] text-red-300 hover:underline"
                                        @click="deleteImage(image)"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>

                        <p v-else-if="isEdit" class="rounded-lg bg-gray-50 py-8 text-center text-sm text-gray-500 dark:bg-gray-700/40">
                            No images yet.
                        </p>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Upload images
                            </label>
                            <input
                                type="file"
                                accept="image/*"
                                multiple
                                class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:text-brand-800 hover:file:bg-brand-100 dark:text-gray-400"
                                @input="form.images = Array.from($event.target.files)"
                            />
                            <p class="mt-1 text-xs text-gray-500">
                                Up to 12 files, 4 MB each. The first upload becomes the primary image.
                            </p>
                            <progress v-if="form.progress" :value="form.progress.percentage" max="100" class="mt-2 w-full" />
                            <InputError :message="form.errors.images" class="mt-1" />
                        </div>
                    </div>

                    <!-- SEO -->
                    <div v-show="tab === 'seo'" class="space-y-5">
                        <TranslatableInput v-model="form.meta_title" name="meta_title" label="Meta title" :errors="form.errors" />
                        <TranslatableInput
                            v-model="form.meta_description"
                            name="meta_description"
                            label="Meta description"
                            type="textarea"
                            :errors="form.errors"
                        />
                    </div>
                </div>

                <div class="mt-5 flex items-center gap-3">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-brand-700 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-brand-800 disabled:opacity-50"
                    >
                        {{ form.processing ? 'Saving…' : (isEdit ? 'Save changes' : 'Create product') }}
                    </button>
                    <Link
                        :href="route('admin.products.index')"
                        class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"
                    >
                        Cancel
                    </Link>
                    <p v-if="Object.keys(form.errors).length" class="text-sm text-red-600">
                        Please fix the highlighted fields.
                    </p>
                </div>
            </form>
        </main>
    </AdminLayout>
</template>

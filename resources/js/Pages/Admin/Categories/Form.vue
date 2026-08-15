<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '../Components/AdminLayout.vue'
import PageHeader from '@/Components/Admin/PageHeader.vue'
import TranslatableInput from '@/Components/Admin/TranslatableInput.vue'
import InputError from '@/Components/InputError.vue'

const props = defineProps({
    category: { type: Object, default: null },
    parents: { type: Array, default: () => [] },
})

const isEdit = computed(() => Boolean(props.category))

const form = useForm({
    parent_id: props.category?.parent_id ?? null,
    name: props.category?.name ?? { en: '' },
    description: props.category?.description ?? {},
    slug: props.category?.slug ?? '',
    image: null,
    icon: props.category?.icon ?? '',
    is_active: props.category?.is_active ?? true,
    is_featured: props.category?.is_featured ?? false,
    sort_order: props.category?.sort_order ?? 0,
})

const submit = () => {
    if (isEdit.value) {
        form.transform((data) => ({ ...data, _method: 'put' }))
            .post(route('admin.categories.update', props.category.id), { forceFormData: true })
    } else {
        form.post(route('admin.categories.store'), { forceFormData: true })
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit category' : 'Add category'" />

    <AdminLayout>
        <main class="p-4 md:ml-64 pt-20">
            <PageHeader
                :title="isEdit ? 'Edit category' : 'Add category'"
                :subtitle="isEdit ? category.name.en : 'Create a new category'"
            />

            <form class="max-w-3xl space-y-6" @submit.prevent="submit">
                <div class="p-6 space-y-5 bg-white rounded-lg shadow dark:bg-gray-800">
                    <TranslatableInput
                        v-model="form.name"
                        name="name"
                        label="Name"
                        required
                        :errors="form.errors"
                    />

                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Parent category
                        </label>
                        <select
                            v-model="form.parent_id"
                            class="block w-full rounded-lg border-gray-300 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        >
                            <option :value="null">— None (top level) —</option>
                            <option v-for="parent in parents" :key="parent.id" :value="parent.id">
                                {{ '— '.repeat(parent.depth) }}{{ parent.name }}
                            </option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">
                            A category cannot be moved beneath one of its own descendants.
                        </p>
                        <InputError :message="form.errors.parent_id" class="mt-1" />
                    </div>

                    <TranslatableInput
                        v-model="form.description"
                        name="description"
                        label="Description"
                        type="textarea"
                        :errors="form.errors"
                    />

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                Slug
                            </label>
                            <input
                                v-model="form.slug"
                                type="text"
                                placeholder="Auto-generated if blank"
                                class="block w-full rounded-lg border-gray-300 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            />
                            <InputError :message="form.errors.slug" class="mt-1" />
                        </div>

                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                Icon
                            </label>
                            <input
                                v-model="form.icon"
                                type="text"
                                placeholder="e.g. laptop"
                                class="block w-full rounded-lg border-gray-300 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            />
                            <InputError :message="form.errors.icon" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Image
                        </label>
                        <img
                            v-if="category?.image_url"
                            :src="category.image_url"
                            alt=""
                            class="object-cover w-24 h-16 mb-2 rounded"
                        />
                        <input
                            type="file"
                            accept="image/*"
                            class="block w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-gray-100 file:text-sm hover:file:bg-gray-200 dark:text-gray-400"
                            @input="form.image = $event.target.files[0]"
                        />
                        <InputError :message="form.errors.image" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                        <div>
                            <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                Sort order
                            </label>
                            <input
                                v-model.number="form.sort_order"
                                type="number"
                                min="0"
                                class="block w-full rounded-lg border-gray-300 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            />
                        </div>

                        <div class="flex items-end">
                            <label class="inline-flex items-center gap-2">
                                <input v-model="form.is_active" type="checkbox" class="w-4 h-4 rounded border-gray-300 text-brand-700" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">Active</span>
                            </label>
                        </div>

                        <div class="flex items-end">
                            <label class="inline-flex items-center gap-2">
                                <input v-model="form.is_featured" type="checkbox" class="w-4 h-4 rounded border-gray-300 text-brand-700" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">Featured</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-5 py-2.5 text-sm font-medium text-white bg-brand-700 rounded-lg hover:bg-brand-800 disabled:opacity-50"
                    >
                        {{ form.processing ? 'Saving…' : (isEdit ? 'Update category' : 'Create category') }}
                    </button>
                    <Link
                        :href="route('admin.categories.index')"
                        class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600"
                    >
                        Cancel
                    </Link>
                </div>
            </form>
        </main>
    </AdminLayout>
</template>

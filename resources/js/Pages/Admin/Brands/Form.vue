<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '../Components/AdminLayout.vue'
import PageHeader from '@/Components/Admin/PageHeader.vue'
import TranslatableInput from '@/Components/Admin/TranslatableInput.vue'
import InputError from '@/Components/InputError.vue'

const props = defineProps({
    brand: { type: Object, default: null },
})

const isEdit = computed(() => Boolean(props.brand))

const form = useForm({
    name: props.brand?.name ?? { en: '' },
    description: props.brand?.description ?? {},
    slug: props.brand?.slug ?? '',
    logo: null,
    is_active: props.brand?.is_active ?? true,
    sort_order: props.brand?.sort_order ?? 0,
})

const submit = () => {
    if (isEdit.value) {
        // Laravel needs the method spoofed for multipart PUT requests.
        form.transform((data) => ({ ...data, _method: 'put' }))
            .post(route('admin.brands.update', props.brand.id), { forceFormData: true })
    } else {
        form.post(route('admin.brands.store'), { forceFormData: true })
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit brand' : 'Add brand'" />

    <AdminLayout>
        <main class="p-4 md:ml-64 pt-20">
            <PageHeader
                :title="isEdit ? 'Edit brand' : 'Add brand'"
                :subtitle="isEdit ? brand.name.en : 'Create a new brand'"
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

                    <TranslatableInput
                        v-model="form.description"
                        name="description"
                        label="Description"
                        type="textarea"
                        :errors="form.errors"
                    />

                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Slug
                        </label>
                        <input
                            v-model="form.slug"
                            type="text"
                            placeholder="Leave blank to generate from the English name"
                            class="block w-full rounded-lg border-gray-300 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                        />
                        <InputError :message="form.errors.slug" class="mt-1" />
                    </div>

                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Logo
                        </label>
                        <img
                            v-if="brand?.logo_url"
                            :src="brand.logo_url"
                            alt=""
                            class="object-contain w-16 h-16 mb-2"
                        />
                        <input
                            type="file"
                            accept="image/*"
                            class="block w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-gray-100 file:text-sm hover:file:bg-gray-200 dark:text-gray-400"
                            @input="form.logo = $event.target.files[0]"
                        />
                        <progress v-if="form.progress" :value="form.progress.percentage" max="100" class="w-full mt-2" />
                        <InputError :message="form.errors.logo" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
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
                            <InputError :message="form.errors.sort_order" class="mt-1" />
                        </div>

                        <div class="flex items-end">
                            <label class="inline-flex items-center gap-2">
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    class="w-4 h-4 rounded border-gray-300 text-brand-700 focus:ring-brand-600"
                                />
                                <span class="text-sm text-gray-700 dark:text-gray-300">Active</span>
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
                        {{ form.processing ? 'Saving…' : (isEdit ? 'Update brand' : 'Create brand') }}
                    </button>
                    <Link
                        :href="route('admin.brands.index')"
                        class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600"
                    >
                        Cancel
                    </Link>
                </div>
            </form>
        </main>
    </AdminLayout>
</template>

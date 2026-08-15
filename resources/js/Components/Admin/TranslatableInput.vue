<script setup>
import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import InputError from '@/Components/InputError.vue'

const props = defineProps({
    modelValue: { type: Object, default: () => ({}) },
    label: { type: String, required: true },
    name: { type: String, required: true },
    type: { type: String, default: 'text' }, // text | textarea
    required: { type: Boolean, default: false },
    errors: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:modelValue'])

const page = usePage()
const locales = computed(() => page.props.locales ?? ['en'])
const localeNames = { en: 'English', km: 'ខ្មែរ' }

const active = ref(locales.value[0])

const update = (locale, value) => {
    emit('update:modelValue', { ...props.modelValue, [locale]: value })
}

// Surface the error on whichever locale tab actually failed validation.
const errorFor = (locale) => props.errors?.[`${props.name}.${locale}`]
const hasError = (locale) => Boolean(errorFor(locale))
</script>

<template>
    <div>
        <div class="flex items-center justify-between mb-1">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ label }}
                <span v-if="required" class="text-red-500">*</span>
            </label>

            <div class="flex gap-1">
                <button
                    v-for="locale in locales"
                    :key="locale"
                    type="button"
                    class="px-2 py-0.5 text-xs rounded border transition"
                    :class="[
                        active === locale
                            ? 'bg-blue-600 text-white border-blue-600'
                            : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600',
                        hasError(locale) ? 'ring-1 ring-red-500' : '',
                    ]"
                    @click="active = locale"
                >
                    {{ localeNames[locale] ?? locale }}
                    <span v-if="locale === 'en'" class="opacity-60">*</span>
                </button>
            </div>
        </div>

        <template v-for="locale in locales" :key="locale">
            <textarea
                v-if="type === 'textarea'"
                v-show="active === locale"
                :value="modelValue?.[locale] ?? ''"
                rows="5"
                class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                @input="update(locale, $event.target.value)"
            />
            <input
                v-else
                v-show="active === locale"
                :value="modelValue?.[locale] ?? ''"
                type="text"
                class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                @input="update(locale, $event.target.value)"
            />
        </template>

        <InputError v-for="locale in locales" :key="`err-${locale}`" :message="errorFor(locale)" class="mt-1" />
    </div>
</template>

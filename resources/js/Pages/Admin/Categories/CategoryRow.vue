<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    node: { type: Object, required: true },
    depth: { type: Number, default: 0 },
})

defineEmits(['delete'])
</script>

<template>
    <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
        <td class="px-6 py-3">
            <div class="flex items-center" :style="{ paddingLeft: `${depth * 20}px` }">
                <span v-if="depth > 0" class="mr-2 text-gray-400">└</span>
                <span class="font-medium text-gray-900 dark:text-white">{{ node.name.en }}</span>
                <span
                    v-if="node.is_featured"
                    class="ml-2 px-1.5 py-0.5 text-[10px] font-medium text-amber-800 bg-amber-100 rounded dark:bg-amber-900 dark:text-amber-300"
                >
                    Featured
                </span>
            </div>
        </td>
        <td class="px-6 py-3 font-mono text-xs">{{ node.slug }}</td>
        <td class="px-6 py-3">{{ node.products_count }}</td>
        <td class="px-6 py-3">
            <span
                class="px-2 py-0.5 text-xs font-medium rounded"
                :class="node.is_active
                    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
                    : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'"
            >
                {{ node.is_active ? 'Active' : 'Inactive' }}
            </span>
        </td>
        <td class="px-6 py-3 text-right whitespace-nowrap">
            <Link
                :href="route('admin.categories.edit', node.id)"
                class="font-medium text-blue-600 hover:underline dark:text-blue-500"
            >
                Edit
            </Link>
            <button
                type="button"
                class="ml-4 font-medium text-red-600 hover:underline dark:text-red-500"
                @click="$emit('delete', node)"
            >
                Delete
            </button>
        </td>
    </tr>

    <!-- Recursive: renders the subtree beneath this row -->
    <CategoryRow
        v-for="child in node.children"
        :key="child.id"
        :node="child"
        :depth="depth + 1"
        @delete="$emit('delete', $event)"
    />
</template>

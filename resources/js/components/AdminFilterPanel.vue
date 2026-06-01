<script setup lang="ts">
withDefaults(
    defineProps<{
        title?: string;
        subtitle?: string;
    }>(),
    {
        title: '',
        subtitle: '',
    },
);
</script>

<template>
    <section class="relative z-[70] rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div v-if="title || subtitle || $slots.actions" class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div v-if="title || subtitle">
                <h2 v-if="title" class="text-sm font-bold text-gray-900">{{ title }}</h2>
                <p v-if="subtitle" class="text-xs text-gray-500">{{ subtitle }}</p>
            </div>

            <slot name="actions" />
        </div>

        <slot />
    </section>
</template>

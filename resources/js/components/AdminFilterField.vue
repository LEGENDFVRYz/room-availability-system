<script setup lang="ts">
defineProps<{
    label: string;
}>();
</script>

<template>
    <label class="flex min-w-0 flex-col gap-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">
        <span>{{ label }}</span>
        <slot />
    </label>
</template>

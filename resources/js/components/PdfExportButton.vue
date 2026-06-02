<script setup lang="ts">
import { FileDown } from 'lucide-vue-next';
import { computed } from 'vue';

type Primitive = string | number | boolean | null | undefined;
type QueryValue = Primitive | Primitive[];

const props = withDefaults(
    defineProps<{
        href: string;
        filters?: Record<string, QueryValue>;
        label?: string;
    }>(),
    {
        filters: () => ({}),
        label: 'Export PDF',
    },
);

const exportUrl = computed(() => {
    const query = new URLSearchParams();

    Object.entries(props.filters).forEach(([key, value]) => {
        if (Array.isArray(value)) {
            value
                .filter((item) => item !== null && item !== undefined && item !== '')
                .forEach((item) => query.append(`${key}[]`, String(item)));
            return;
        }

        if (value !== null && value !== undefined && value !== '') {
            query.set(key, String(value));
        }
    });

    const queryString = query.toString();

    return queryString ? `${props.href}?${queryString}` : props.href;
});
</script>

<template>
    <a
        :href="exportUrl"
        target="_blank"
        rel="noopener"
        class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-pup-maroon/20 bg-white px-3 text-xs font-bold text-pup-maroon shadow-sm transition hover:bg-pup-maroon-pale"
    >
        <FileDown class="h-3.5 w-3.5" />
        {{ label }}
    </a>
</template>

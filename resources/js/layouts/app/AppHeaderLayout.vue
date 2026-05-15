<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppHeader from '@/components/AppHeader.vue';
import AppShell from '@/components/AppShell.vue';
import type { BreadcrumbItemType, PageHeader } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
    pageheader?: PageHeader;
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell class="flex-col">
        <AppHeader :breadcrumbs="breadcrumbs" />

        <!-- Page Header -->
        <div
            v-if="pageheader"
            class="relative overflow-hidden border-b-[3px] border-pup-gold bg-[linear-gradient(135deg,var(--tw-gradient-stops))] from-pup-maroon-deep via-pup-maroon via-60% to-pup-maroon-light pt-8 pb-7 px-8 after:box-content after:absolute after:-right-10 after:-top-10 after:h-[200px] after:w-[200px] after:rounded-full after:border-[40px] after:border-[rgba(240,180,41,0.08)] after:content-[''] after:pointer-events-none"
        >
            <h1 class="relative z-10 mb-1 text-[22px] font-semibold text-white">{{ pageheader.title }}</h1>
            <p v-if="pageheader.desc" class="relative z-10 text-[13px] text-white/65">{{ pageheader.desc }}</p>
        </div>

        <AppContent>
            <slot />
        </AppContent>
    </AppShell>
</template>

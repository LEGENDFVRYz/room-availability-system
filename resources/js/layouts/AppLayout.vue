<script setup lang="ts">
import AppLayout from '@/layouts/app/AppHeaderLayout.vue';
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
    <AppLayout :breadcrumbs="breadcrumbs" :pageheader="pageheader">
        <slot />
    </AppLayout>
</template>

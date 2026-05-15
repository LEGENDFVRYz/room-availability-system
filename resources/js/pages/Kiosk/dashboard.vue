<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, PageHeader } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Room Map', href: '/dashboard' },
];

const pageheader: PageHeader = {
    title: 'Room Availability Map',
    desc: 'Computer Engineering Department — A. Mabini Campus, Santa Mesa, Manila',
};
</script>

<template>
    <Head title="Public Dashboard" />
    
    <AppLayout :breadcrumbs="breadcrumbs" :pageheader="pageheader">
        <h1>PUBLIC</h1>
    </AppLayout>
</template>

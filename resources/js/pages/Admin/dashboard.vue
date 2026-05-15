<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, PageHeader } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
];

const pageheader: PageHeader = {
    title: 'Admin Dashboard',
    desc: 'Manage rooms, schedules, and announcements · CPE Department',
};
</script>

<template>
    <Head title="Admin Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs" :pageheader="pageheader">
        <h1>ADMIN</h1>
    </AppLayout>
</template>

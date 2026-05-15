<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, PageHeader } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Schedules', href: '/admin/schedules' },
];

const pageheader: PageHeader = {
    title: 'Schedules',
    desc: 'Manage class schedules · CPE Department',
};
</script>


<template>
    <Head title="Schedules" />

    <AppLayout :breadcrumbs="breadcrumbs" :pageheader="pageheader">
        <h1>SCHEDULES ADMIN</h1>
    </AppLayout>
</template>

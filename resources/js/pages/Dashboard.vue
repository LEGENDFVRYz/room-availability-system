<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import AdminDashboard from './Admin/index.vue';
import KioskDashboard from './Kiosk/Index.vue';

const props = defineProps<{
    role: 'admin' | 'kiosk';
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <AdminDashboard v-if="props.role === 'admin'" />
        <KioskDashboard v-else-if="props.role === 'kiosk'" />
    </AppLayout>
</template>

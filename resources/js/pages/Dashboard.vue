<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import AdminDashboard from './Admin/index.vue';
import KioskDashboard from './Kiosk/Index.vue';
import { computed } from 'vue';

const props = defineProps<{
    role: 'admin' | 'kiosk';
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

const pageheader = computed(() =>
    props.role === 'admin'
        ? { title: 'Admin Dashboard', desc: 'Manage rooms, schedules, and announcements · CPE Department' }
        : { title: 'Room Availability Map', desc: 'Computer Engineering Department — A. Mabini Campus, Santa Mesa, Manila' },
);
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs" :pageheader="pageheader">
        <AdminDashboard v-if="props.role === 'admin'" />
        <KioskDashboard v-else-if="props.role === 'kiosk'" />
    </AppLayout>
</template>

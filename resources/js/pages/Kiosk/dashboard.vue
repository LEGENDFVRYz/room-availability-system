<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, PageHeader } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Floorplan } from '@/components/floorplan';


const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Room Map', href: '/dashboard' },
];

const pageheader: PageHeader = {
    title: 'Room Availability Map',
    desc: 'Computer Engineering Department — A. Mabini Campus, Santa Mesa, Manila',
};
</script>

<template>
    <Head title="Room Availability" />

    <!-- Remove temporary the page header -->
    <!-- <AppLayout :breadcrumbs="breadcrumbs" :pageheader="pageheader"> -->
    <AppLayout>
        <Floorplan
            :show-panel="false"
            api-url="/kiosk/dashboard/floor-status?floor=3"
            color-mode="status"
            :poll-interval-ms="5000"
        />
    </AppLayout>
</template>

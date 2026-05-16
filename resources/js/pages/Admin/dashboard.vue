<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, PageHeader } from '@/types';
import { Head } from '@inertiajs/vue3';

interface RoomStats {
    available:   number;
    occupied:    number;
    reserved:    number;
    maintenance: number;
}

defineProps<{ roomStats: RoomStats }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
];

const pageheader: PageHeader = {
    title: 'Admin Dashboard',
    desc: 'Manage rooms, schedules, and announcements · CPE Department',
};

const STATUS_CARDS = [
    {
        key:      'available' as const,
        label:    'Available',
        sub:      'rooms free now',
        accent:   'bg-green-500',
        labelCls: 'text-green-600',
    },
    {
        key:      'occupied' as const,
        label:    'Occupied',
        sub:      'classes ongoing',
        accent:   'bg-red-500',
        labelCls: 'text-red-600',
    },
    {
        key:      'reserved' as const,
        label:    'Reserved',
        sub:      'held for booking',
        accent:   'bg-yellow-400',
        labelCls: 'text-yellow-600',
    },
    {
        key:      'maintenance' as const,
        label:    'Maintenance',
        sub:      'unavailable',
        accent:   'bg-pup-maroon',
        labelCls: 'text-pup-maroon',
    },
] as const;
</script>

<template>
    <Head title="Admin Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs" :pageheader="pageheader">
        <div class="flex flex-col gap-6">

            <!-- Room Status Widget -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div
                    v-for="card in STATUS_CARDS"
                    :key="card.key"
                    class="flex overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm"
                >
                    <!-- Accent bar -->
                    <div class="w-1 shrink-0" :class="card.accent" />

                    <!-- Content -->
                    <div class="px-5 py-4">
                        <p
                            class="text-[10px] font-semibold uppercase tracking-widest"
                            :class="card.labelCls"
                        >
                            {{ card.label }}
                        </p>
                        <p class="mt-2 text-4xl font-bold leading-none text-gray-900">
                            {{ roomStats[card.key] }}
                        </p>
                        <p class="mt-1.5 text-sm text-gray-400">{{ card.sub }}</p>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>

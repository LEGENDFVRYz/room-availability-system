<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PillTabs from '@/components/PillTabs.vue';
import type { BreadcrumbItem, PageHeader } from '@/types';
import { Head } from '@inertiajs/vue3';
import { SquareActivity, ScrollText, Search, Plus, ChevronDown } from 'lucide-vue-next';
import { ref, computed } from 'vue';

// Page Setup and Template
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard',           href: '/admin/dashboard' },
    { title: 'Room Usage Logs',     href: '/admin/records/usage' },
    { title: 'Admin Activity Logs', href: '/admin/records/activity' },
];

const pageheader: PageHeader = {
    title: 'Room Usage Logs',
    desc: 'Lorem ipsummmmmmmmmmmmmmmm.',
};

const manageTabs = [
    { label: 'Room Usage',  href: '/admin/records/usage',   icon: SquareActivity },
    { label: 'Activity',    href: '/admin/records/activity',  icon: ScrollText },
];

</script>


<template>
    <Head title="Room Usage Logs" />

    <AppLayout :breadcrumbs="breadcrumbs" :pageheader="pageheader">
        <!-- Tab switcher -->
        <div class="mb-6">
            <PillTabs :tabs="manageTabs" />
        </div>

        <!-- Add tre content here -->
        <h1>TRY: USAGE</h1>

    </AppLayout>
</template>

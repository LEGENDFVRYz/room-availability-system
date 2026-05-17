<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PillTabs from '@/components/PillTabs.vue';
import type { BreadcrumbItem, PageHeader } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { CalendarDays, School, GraduationCap, Server, Plus, ChevronDown } from 'lucide-vue-next';

// Types
interface CurrentTerm {
    school_year: string;
    semester_label: string;
    year_start: number;
    semester: number;
}

// Props and template setup
const props = defineProps<{ currentTerm: CurrentTerm | null }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard',       href: '/admin/dashboard' },
    { title: 'Daily Schedule',  href: '/admin/operations/daily' },
    { title: 'Room Status',     href: '/admin/operations/room-status' },
];

const pageheader: PageHeader = {
    title: 'Room Status',
    desc: 'Manage room restrictions, maintenance blocks, and non-class reservations.',
};

const manageTabs = [
    { label: 'Daily Schedule', href: '/admin/operations/daily',       icon: CalendarDays },
    { label: 'Room Status',    href: '/admin/operations/room-status', icon: School },
];


</script>

<template>
    <Head title="Room Status" />

    <AppLayout :breadcrumbs="breadcrumbs" :pageheader="pageheader">

        <!-- Tab switcher -->
        <div class="mb-6">
            <PillTabs :tabs="manageTabs" />
        </div>

        <!-- Content -->


    </AppLayout>
</template>

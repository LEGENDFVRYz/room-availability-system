<script setup lang="ts">
import PillTabs from '@/components/PillTabs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, PageHeader } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AnnouncementCard from './components/AnnouncementCard.vue';

type NoticeDesign = 'general' | 'exception' | 'override';
type NoticeFilter = 'all' | NoticeDesign;

type AnnouncementType =
    | 'academic_term'
    | 'schedule_update'
    | 'class_cancellation'
    | 'room_change'
    | 'special_class'
    | 'room_maintenance'
    | 'room_reserved';

type Announcement = {
    id: number;
    title: string;
    body: string;
    type: AnnouncementType;
    typeLabel: string;
    design: NoticeDesign;
    room?: string | null;
    schedule?: string | null;
    postedAt?: string | null;
    expiresAt?: string | null;
    isPinned?: boolean;
};

const props = withDefaults(defineProps<{
    announcements?: Announcement[];
}>(), {
    announcements: () => [],
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Announcements', href: '/announcements' },
];

const pageheader: PageHeader = {
    title: 'Announcements',
    desc: 'Public notices from the CPE Department.',
};

const activeFilter = ref<NoticeFilter>('all');

const allAnnouncements = computed(() => props.announcements);

const filteredAnnouncements = computed(() => {
    if (activeFilter.value === 'all') {
        return allAnnouncements.value;
    }

    return allAnnouncements.value.filter((announcement) => announcement.design === activeFilter.value);
});

const filterTabs = computed(() => [
    {
        label: `All (${allAnnouncements.value.length})`,
        value: 'all',
    },
    {
        label: 'General',
        value: 'general',
    },
    {
        label: 'Exception',
        value: 'exception',
    },
    {
        label: 'Override',
        value: 'override',
    },
]);
</script>

<template>
    <Head title="Announcements" />

    <AppLayout :breadcrumbs="breadcrumbs" :pageheader="pageheader">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <PillTabs
                v-model:active-value="activeFilter"
                :tabs="filterTabs"
            />

            <div class="rounded-full border border-pup-gold/40 bg-pup-gold-pale px-4 py-2 text-sm font-semibold text-pup-maroon-deep">
                {{ filteredAnnouncements.length }} active notice<span v-if="filteredAnnouncements.length !== 1">s</span>
            </div>
        </div>
        
        <section class="mx-auto mt-4 max-w-4xl space-y-6">
            <div class="space-y-3">
                <AnnouncementCard
                    v-for="announcement in filteredAnnouncements"
                    :key="announcement.id"
                    :title="announcement.title"
                    :body="announcement.body"
                    :type="announcement.type"
                    :type-label="announcement.typeLabel"
                    :design="announcement.design"
                    :room="announcement.room ?? undefined"
                    :schedule="announcement.schedule ?? undefined"
                    :posted-at="announcement.postedAt ?? undefined"
                    :expires-at="announcement.expiresAt ?? undefined"
                    :is-pinned="announcement.isPinned"
                />
            </div>

            <div
                v-if="filteredAnnouncements.length === 0"
                class="rounded-2xl border border-dashed border-pup-gray-200 bg-pup-white p-8 text-center text-sm text-pup-gray-600"
            >
                No active announcements for this filter.
            </div>
        </section>
    </AppLayout>
</template>

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
    room?: string;
    schedule?: string;
    postedAt?: string;
    expiresAt?: string;
    isPinned?: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Announcements', href: '/announcements' },
];

const pageheader: PageHeader = {
    title: 'Announcements',
    desc: 'Public notices from the CPE Department.',
};

const activeFilter = ref<NoticeFilter>('all');

const announcements: Announcement[] = [
    {
        id: 1,
        title: 'New academic term is now active',
        body: 'The current academic term has been updated. Room schedules now follow the active term configuration.',
        type: 'academic_term',
        typeLabel: 'Academic Term',
        design: 'general',
        postedAt: 'Today, 6:45 AM',
        expiresAt: 'Until replaced',
        isPinned: true,
    },
    {
        id: 2,
        title: 'Room schedule has been updated',
        body: 'Some weekly class schedules were revised. Please check the latest room availability before proceeding to a room.',
        type: 'schedule_update',
        typeLabel: 'Schedule Update',
        design: 'general',
        postedAt: 'Today, 7:10 AM',
        expiresAt: 'Tonight, 11:59 PM',
    },
    {
        id: 3,
        title: 'BS CPE 4A - Embedded Systems class cancelled today',
        body: 'The scheduled class for this section is cancelled today only. Please check the room board before proceeding, as the room may become available after the protected schedule window.',
        type: 'class_cancellation',
        typeLabel: 'Class Cancellation',
        design: 'exception',
        room: 'CEA 301',
        schedule: '10:00 AM to 12:00 PM',
        postedAt: 'Today, 7:15 AM',
        expiresAt: 'Today, 12:00 PM',
    },
    {
        id: 4,
        title: 'BS CPE 3A - Digital Logic moved to CEA 413',
        body: 'A same day room change was approved for this section. Students and faculty should proceed to the replacement room shown below.',
        type: 'room_change',
        typeLabel: 'Room Change',
        design: 'exception',
        room: 'CEA 207 → CEA 413',
        schedule: '1:00 PM to 3:00 PM',
        postedAt: 'Today, 8:20 AM',
        expiresAt: 'Today, 3:00 PM',
    },
    {
        id: 5,
        title: 'BS CPE 2B - Data Structures special class added',
        body: 'A special or makeup class was added for this section today. Please follow the assigned room and time shown below.',
        type: 'special_class',
        typeLabel: 'Special Class',
        design: 'exception',
        room: 'CEA 300',
        schedule: '3:00 PM to 5:00 PM',
        postedAt: 'Today, 8:35 AM',
        expiresAt: 'Today, 5:00 PM',
    },
    {
        id: 6,
        title: 'CEA 301 is under maintenance',
        body: 'This room is temporarily unavailable. Normal schedules are overridden while this notice is active.',
        type: 'room_maintenance',
        typeLabel: 'Maintenance',
        design: 'override',
        room: 'CEA 301',
        schedule: '8:00 AM to 5:00 PM',
        postedAt: 'Today, 6:30 AM',
        expiresAt: 'Today, 5:00 PM',
    },
    {
        id: 7,
        title: 'CEA 413 reserved for department activity',
        body: 'This room is reserved for an official department activity. Availability is blocked while this notice is active.',
        type: 'room_reserved',
        typeLabel: 'Room Reserved',
        design: 'override',
        room: 'CEA 413',
        schedule: '9:00 AM to 12:00 PM',
        postedAt: 'Today, 7:00 AM',
        expiresAt: 'Today, 12:00 PM',
    },
];

const filteredAnnouncements = computed(() => {
    if (activeFilter.value === 'all') {
        return announcements;
    }

    return announcements.filter((announcement) => announcement.design === activeFilter.value);
});

const filterTabs = computed(() => [
    {
        label: `All (${announcements.length})`,
        value: 'all',
    },
    {
        label: `General`,
        value: 'general',
    },
    {
        label: `Exception`,
        value: 'exception',
    },
    {
        label: `Override`,
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
                    :room="announcement.room"
                    :schedule="announcement.schedule"
                    :posted-at="announcement.postedAt"
                    :expires-at="announcement.expiresAt"
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

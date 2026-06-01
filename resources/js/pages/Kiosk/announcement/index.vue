<script setup lang="ts">
import PillTabs from '@/components/PillTabs.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, PageHeader } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
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
    | 'room_reserved'
    | 'general';

type Announcement = {
    id: number;
    title: string;
    body: string;
    type: AnnouncementType;
    typeLabel: string;
    sourceType?: string | null;
    design: NoticeDesign;
    room?: string | null;
    schedule?: string | null;
    postedAt?: string | null;
    expiresAt?: string | null;
    isPinned?: boolean;
};

type AnnouncementApiResponse = {
    data?: Announcement[];
    meta?: {
        count?: number;
        last_updated_at?: string;
        poll_interval_ms?: number;
    };
};

const props = withDefaults(defineProps<{
    announcements?: Announcement[];
    pollIntervalMs?: number;
}>(), {
    announcements: () => [],
    pollIntervalMs: 15000,
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Announcements', href: '/announcements' },
];

const pageheader: PageHeader = {
    title: 'Announcements',
    desc: 'Public notices from the CPE Department.',
};

const activeFilter = ref<NoticeFilter>('all');
const announcements = ref<Announcement[]>([...props.announcements]);
const isRefreshing = ref(false);
const refreshError = ref<string | null>(null);
const lastUpdatedAt = ref<string | null>(null);
const pollInterval = ref(props.pollIntervalMs);

let refreshTimer: ReturnType<typeof window.setInterval> | null = null;
let requestController: AbortController | null = null;
let isFetching = false;

const allAnnouncements = computed(() => announcements.value);

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
        label: `General (${countByDesign('general')})`,
        value: 'general',
    },
    {
        label: `Exception (${countByDesign('exception')})`,
        value: 'exception',
    },
    {
        label: `Override (${countByDesign('override')})`,
        value: 'override',
    },
]);

const lastUpdatedLabel = computed(() => {
    if (!lastUpdatedAt.value) {
        return 'Waiting for update';
    }

    return new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: '2-digit',
        second: '2-digit',
    }).format(new Date(lastUpdatedAt.value));
});

function countByDesign(design: NoticeDesign): number {
    return allAnnouncements.value.filter((announcement) => announcement.design === design).length;
}

function restartPolling() {
    if (refreshTimer) {
        window.clearInterval(refreshTimer);
    }

    refreshTimer = window.setInterval(() => {
        fetchAnnouncements();
    }, pollInterval.value);
}

async function fetchAnnouncements() {
    if (isFetching) {
        return;
    }

    isFetching = true;
    isRefreshing.value = true;
    refreshError.value = null;

    requestController?.abort();
    requestController = new AbortController();

    try {
        const response = await fetch('/kiosk/api/announcements', {
            method: 'GET',
            headers: {
                Accept: 'application/json',
            },
            cache: 'no-store',
            signal: requestController.signal,
        });

        if (!response.ok) {
            throw new Error(`Announcement request failed with status ${response.status}`);
        }

        const payload = await response.json() as AnnouncementApiResponse;

        announcements.value = Array.isArray(payload.data) ? payload.data : [];
        lastUpdatedAt.value = payload.meta?.last_updated_at ?? new Date().toISOString();

        if (payload.meta?.poll_interval_ms && payload.meta.poll_interval_ms !== pollInterval.value) {
            pollInterval.value = payload.meta.poll_interval_ms;
            restartPolling();
        }
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
            return;
        }

        refreshError.value = 'Unable to refresh announcements. Showing latest loaded notices.';
        console.error(error);
    } finally {
        isRefreshing.value = false;
        isFetching = false;
    }
}

onMounted(() => {
    fetchAnnouncements();
    restartPolling();
});

onBeforeUnmount(() => {
    if (refreshTimer) {
        window.clearInterval(refreshTimer);
    }

    requestController?.abort();
});
</script>

<template>
    <Head title="Announcements" />

    <AppLayout :breadcrumbs="breadcrumbs" :pageheader="pageheader">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <PillTabs
                v-model:active-value="activeFilter"
                :tabs="filterTabs"
            />

            <div class="flex flex-wrap items-center gap-3 text-sm text-pup-gray-600">
                <span
                    class="inline-flex items-center gap-2 rounded-full border border-pup-gray-200 bg-pup-white px-4 py-2 font-semibold"
                    :class="isRefreshing ? 'text-pup-maroon' : 'text-pup-gray-600'"
                >
                    <span
                        class="size-2 rounded-full"
                        :class="isRefreshing ? 'animate-pulse bg-pup-gold-dark' : 'bg-pup-gray-400'"
                    />
                    Updated {{ lastUpdatedLabel }}
                </span>

                <span class="rounded-full border border-pup-gold/40 bg-pup-gold-pale px-4 py-2 font-semibold text-pup-maroon-deep">
                    {{ filteredAnnouncements.length }} active notice<span v-if="filteredAnnouncements.length !== 1">s</span>
                </span>
            </div>
        </div>

        <p
            v-if="refreshError"
            class="mx-auto mt-4 max-w-4xl rounded-xl border border-pup-gold/40 bg-pup-gold-pale px-4 py-3 text-sm font-medium text-pup-maroon-deep"
        >
            {{ refreshError }}
        </p>

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

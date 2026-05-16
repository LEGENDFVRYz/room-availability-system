<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PillTabs from '@/components/PillTabs.vue';
import ScheduleModal, { type ScheduleFormData } from './Components/ScheduleModal.vue';
import ScheduleDeleteModal from './Components/ScheduleDeleteModal.vue';
import SectionScheduleView, { type ScheduleEntry } from './Components/SectionScheduleView.vue';
import type { BreadcrumbItem, PageHeader } from '@/types';
import { Head } from '@inertiajs/vue3';
import { LayoutGrid, Building2, Plus } from 'lucide-vue-next';
import { ref } from 'vue';


// Types
interface Room {
    id: number;
    name: string;
    code: string;
}

interface Section {
    label: string;
    year: number;
    schedules: ScheduleEntry[];
}

interface TermInfo {
    label: string;
    semester: string;
}


// Props
defineProps<{
    sections: Section[];
    rooms: Room[];
    currentTerm: TermInfo | null;
}>();


// --- Layouts
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Schedules', href: '/admin/schedules/sections' },
];

const pageheader: PageHeader = {
    title: 'Schedules',
    desc: 'Manage class schedules for all batches · CPE Department',
};

const scheduleTabs = [
    { label: 'By Section', href: '/admin/schedules/sections', icon: LayoutGrid },
    { label: 'By Room',    href: '/admin/schedules/rooms',    icon: Building2 },
];


// --- Modal States
const showScheduleModal = ref(false);
const showDeleteModal   = ref(false);
const editingSchedule   = ref<ScheduleFormData | null>(null);
const currentSection    = ref<string>('');
const deletingSchedule  = ref<{
    id: number; subject_title: string; subject_code: string; section: string;
} | null>(null);

function openCreate() {
    editingSchedule.value  = null;
    showScheduleModal.value = true;
}

function openEdit(entry: ScheduleEntry) {
    editingSchedule.value = {
        id:              entry.id,
        subject_code:    entry.subject_code,
        subject_title:   entry.subject,
        section:         entry.section,
        instructor_name: entry.instructor,
        room_id:         entry.room_id,
        day_of_week:     entry.day + 1,
        start_time:      entry.start_time,
        end_time:        entry.end_time,
    };
    showScheduleModal.value = true;
}

function openDelete(schedule: ScheduleFormData) {
    deletingSchedule.value  = {
        id:            schedule.id,
        subject_title: schedule.subject_title,
        subject_code:  schedule.subject_code,
        section:       schedule.section,
    };
    showScheduleModal.value = false;
    showDeleteModal.value   = true;
}

function closeScheduleModal() {
    showScheduleModal.value = false;
    editingSchedule.value   = null;
}

function closeDeleteModal() {
    showDeleteModal.value  = false;
    deletingSchedule.value = null;
}
</script>


<template>
    <Head title="Schedules · By Section" />

    <AppLayout :breadcrumbs="breadcrumbs" :pageheader="pageheader">
        <div class="flex flex-col gap-5">

            <!-- No active term warning -->
            <div
                v-if="!currentTerm"
                class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
            >
                No active academic term is set. Go to
                <a href="/admin/manage/configs" class="font-semibold underline">Manage → Config</a>
                to configure one before adding schedules.
            </div>

            <!-- Top bar -->
            <div class="flex items-center justify-between gap-3">
                <PillTabs :tabs="scheduleTabs" />

                <button
                    :disabled="!currentTerm"
                    @click="openCreate"
                    class="flex items-center gap-1.5 rounded-lg bg-pup-maroon px-3.5 py-2 text-sm font-medium text-white transition hover:bg-pup-maroon-deep disabled:cursor-not-allowed disabled:opacity-40"
                >
                    <Plus class="h-4 w-4" />
                    Add Schedule
                </button>
            </div>

            <SectionScheduleView
                :sections="sections"
                :current-term="currentTerm"
                @open-edit="openEdit"
                @section-changed="label => currentSection = label"
            />
        </div>
    </AppLayout>

    <!-- Modal Declarations -->
    <ScheduleModal
        v-if="showScheduleModal"
        :schedule="editingSchedule"
        :default-section="currentSection"
        :rooms="rooms"
        @close="closeScheduleModal"
        @request-delete="openDelete"
    />

    <ScheduleDeleteModal
        v-if="showDeleteModal && deletingSchedule"
        :schedule="deletingSchedule"
        @close="closeDeleteModal"
    />
</template>

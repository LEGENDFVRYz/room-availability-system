<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PillTabs from '@/components/PillTabs.vue';
import type { BreadcrumbItem, PageHeader, SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Building2, Settings2, GraduationCap, Server, Plus, ChevronDown } from 'lucide-vue-next';
import { computed } from 'vue';

// Page Props
const props = defineProps<{
    configs: {
        claim_grace_minutes: number;
        room_status_poll_interval: number;
        kiosk_poll_interval: number;
        status_warning_minutes: number;
        kiosk_display_name: string;
        kiosk_show_clock: boolean;
        kiosk_notice_rotation_seconds: number;
    }
}>();

// Props and template config
const page = usePage<SharedData>();
const currentTerm = computed(() => page.props.currentTerm);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Manage',    href: '/admin/manage/rooms' },
    { title: 'Config',    href: '/admin/manage/configs' },
];

const pageheader: PageHeader = {
    title: 'Manage System Config',
    desc: 'Configure active academic terms, school years, and global system parameters.',
};

const manageTabs = [
    { label: 'Manage Rooms',  href: '/admin/manage/rooms',   icon: Building2 },
    { label: 'Manage Config', href: '/admin/manage/configs',  icon: Settings2 },
];

// ── Academic Term form ────────────────────────────────────────────────────────

const currentYear = new Date().getFullYear();

const yearOptions = [
    { value: currentYear - 2, label: `SY ${currentYear - 2}–${currentYear - 1}` },
    { value: currentYear - 1, label: `SY ${currentYear - 1}–${currentYear}` },
    { value: currentYear,     label: `SY ${currentYear}–${currentYear + 1}` },
    { value: currentYear + 1, label: `SY ${currentYear + 1}–${currentYear + 2}` },
];

const semesterOptions = [
    { value: 1, label: '1st Semester' },
    { value: 2, label: '2nd Semester' },
    { value: 3, label: 'Summer' },
];

const form = useForm({
    year_start: currentTerm.value?.year_start ?? currentYear,
    semester:   currentTerm.value?.semester   ?? 1,
});

function submit() {
    form.post('/admin/manage/configs/terms/set-current', {
        preserveScroll: true,
    });
}
        // claim_grace_minutes: number;
        // room_status_poll_interval: number;
        // kiosk_poll_interval: number;
        // status_warning_minutes: number;
        // kiosk_display_name: string;
        // kiosk_show_clock: boolean;
        // kiosk_notice_rotation_seconds: number;
// System info 
const systemInfo = [
    { label: 'System Version',      value: 'v1.0.0-alpha' },
    { label: 'Department',          value: 'Computer Engineering' },
    { 
        label: 'Kiosk Refresh Rate',  
        value: `Every ${props.configs.kiosk_poll_interval / 1000} seconds` 
    },
    { 
        label: 'Kiosk Notice Rotation', 
        value: `Every ${props.configs.kiosk_notice_rotation_seconds} seconds` 
    },
    { 
        label: 'Public Page Refresh', 
        value: `Every ${props.configs.room_status_poll_interval / 1000} seconds` 
    },
    { 
        label: 'Daily Operation Grace Period', 
        value: `Every ${props.configs.claim_grace_minutes} minutes` 
    },
];
</script>

<template>
    <Head title="Manage Config" />

    <AppLayout :breadcrumbs="breadcrumbs" :pageheader="pageheader">

        <!-- Tab switcher -->
        <div class="mb-6">
            <PillTabs :tabs="manageTabs" />
        </div>

        <!-- 2-column grid (matching original design) -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

            <!--  Academic Settings card -->
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="mb-5 flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-pup-maroon/10">
                        <GraduationCap class="h-5 w-5 text-pup-maroon" />
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">Academic Settings</h3>
                        <p class="text-xs text-gray-500">School year and semester configuration</p>
                    </div>
                </div>

                <!-- Active term display -->
                <div
                    :class="[
                        'mb-5 rounded-lg border px-4 py-3.5',
                        currentTerm
                            ? 'border-pup-maroon/20 bg-pup-maroon/[0.04]'
                            : 'border-dashed border-gray-200 bg-gray-50',
                    ]"
                >
                    <p class="mb-1 text-[10px] font-semibold uppercase tracking-widest text-gray-400">
                        Active Term
                    </p>
                    <template v-if="currentTerm">
                        <p class="text-base font-bold text-pup-maroon">SY {{ currentTerm.school_year }}</p>
                        <p class="text-sm text-gray-500">{{ currentTerm.semester_label }}</p>
                    </template>
                    <p v-else class="text-sm italic text-gray-400">No active term set</p>
                </div>

                <!-- Change term -->
                <div class="space-y-3">
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400">
                        Set Active Term
                    </p>

                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-gray-600">School Year</label>
                        <div class="relative">
                            <select
                                v-model.number="form.year_start"
                                class="h-9 w-full appearance-none rounded-lg border border-gray-200 bg-white pl-3 pr-8 text-sm text-gray-700 focus:border-pup-maroon/40 focus:outline-none focus:ring-2 focus:ring-pup-maroon/10"
                            >
                                <option v-for="y in yearOptions" :key="y.value" :value="y.value">
                                    {{ y.label }}
                                </option>
                            </select>
                            <ChevronDown class="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-gray-600">Semester</label>
                        <div class="relative">
                            <select
                                v-model.number="form.semester"
                                class="h-9 w-full appearance-none rounded-lg border border-gray-200 bg-white pl-3 pr-8 text-sm text-gray-700 focus:border-pup-maroon/40 focus:outline-none focus:ring-2 focus:ring-pup-maroon/10"
                            >
                                <option v-for="s in semesterOptions" :key="s.value" :value="s.value">
                                    {{ s.label }}
                                </option>
                            </select>
                            <ChevronDown class="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                        </div>
                    </div>

                    <div class="pt-1">
                        <button
                            type="button"
                            :disabled="form.processing"
                            @click="submit"
                            class="w-full rounded-lg bg-pup-maroon px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-pup-maroon-light disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{ form.processing ? 'Applying…' : 'Set as Active Term' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- System information card -->
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="mb-5 flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-pup-gold/15">
                        <Server class="h-5 w-5 text-pup-gold" />
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">System Information</h3>
                        <p class="text-xs text-gray-500">Current runtime and system configuration</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <div
                        v-for="item in systemInfo"
                        :key="item.label"
                        class="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-2.5"
                    >
                        <span class="text-xs font-medium text-gray-500">{{ item.label }}</span>
                        <span class="text-xs font-semibold text-gray-700">{{ item.value }}</span>
                    </div>
                </div>
            </div>

            <!-- Future config placeholder -->
            <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50/50 py-10 lg:col-span-2">
                <Plus class="mb-2 h-8 w-8 text-gray-300" />
                <p class="text-sm font-medium text-gray-400">More configuration options coming soon</p>
                <p class="mt-0.5 text-xs text-gray-400">Additional system settings will appear here in future updates</p>
            </div>

        </div>

    </AppLayout>
</template>

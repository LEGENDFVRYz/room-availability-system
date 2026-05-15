<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, PageHeader } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Building2, Settings2, GraduationCap, Server, Plus } from 'lucide-vue-next';
import PillTabs from '@/components/PillTabs.vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Manage',    href: '/admin/manage/rooms' },
    { title: 'Config',    href: '/admin/manage/configs' },
];

const pageheader: PageHeader = {
    title: 'Manage Config',
    desc: 'System and academic configuration · CPE Department',
};

const systemInfo = [
    { label: 'System Version',      value: 'v1.0.0-alpha' },
    { label: 'Department',          value: 'Computer Engineering' },
    { label: 'Kiosk Refresh Rate',  value: 'Every 15 seconds' },
    { label: 'Public Page Refresh', value: 'Every 30 seconds' },
];
</script>

<template>
    <Head title="Manage Config" />

    <AppLayout :breadcrumbs="breadcrumbs" :pageheader="pageheader">

        <!-- Tab switcher -->
        <div class="mb-6">
            <PillTabs :tabs="[
                { label: 'Manage Rooms',  href: '/admin/manage/rooms',   icon: Building2 },
                { label: 'Manage Config', href: '/admin/manage/configs',  icon: Settings2 },
            ]" />
        </div>
        

        <!-- Config content -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            
            <!-- Academic Settings card -->
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

                <div class="space-y-4">
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-gray-600">
                            Current School Year
                        </label>
                        <input
                            type="text"
                            value="2025 – 2026"
                            disabled
                            class="w-full cursor-not-allowed rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700"
                        />
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-gray-600">
                            Current Semester
                        </label>
                        <select
                            disabled
                            class="w-full cursor-not-allowed rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700"
                        >
                            <option>2nd Semester</option>
                        </select>
                    </div>

                    <div class="pt-1">
                        <button
                            disabled
                            class="w-full cursor-not-allowed rounded-lg bg-pup-maroon px-4 py-2 text-sm font-medium text-white opacity-40"
                        >
                            Save Academic Settings
                        </button>
                        <p class="mt-2 text-center text-xs text-gray-400">
                            Academic settings management coming soon
                        </p>
                    </div>
                </div>
            </div>

            <!-- System Info card -->
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

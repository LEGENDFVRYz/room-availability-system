<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, PageHeader } from '@/types';
import { Head } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    BarChart3,
    CalendarDays,
    CheckCircle2,
    ChevronRight,
    ClipboardList,
    Clock,
    DoorOpen,
    FileSpreadsheet,
    History,
    List,
    School,
    ShieldAlert,
    TrendingDown,
    TrendingUp,
    Users,
    Wrench,
} from 'lucide-vue-next';

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
        label:    'Available Now',
        sub:      'rooms without active class',
        accent:   'border-l-status-available',
        iconCls:  'text-status-available',
        bgCls:    'bg-status-available-bg',
        icon:     DoorOpen,
    },
    {
        key:      'reserved' as const,
        label:    'Reserved (Upcoming)',
        sub:      'classes waiting to claim',
        accent:   'border-l-status-reserved',
        iconCls:  'text-status-reserved',
        bgCls:    'bg-status-reserved-bg',
        icon:     ClipboardList,
    },
    {
        key:      'occupied' as const,
        label:    'Occupied Now',
        sub:      'rooms currently occupied',
        accent:   'border-l-status-occupied',
        iconCls:  'text-status-occupied',
        bgCls:    'bg-status-occupied-bg',
        icon:     Users,
    },
    {
        key:      'maintenance' as const,
        label:    'Risk / Exceptions',
        sub:      'daily changes for selected date',
        accent:   'border-l-status-warning',
        iconCls:  'text-status-warning',
        bgCls:    'bg-status-warning-bg',
        icon:     Wrench,
    },
] as const;

const scheduleHealth = [
    { label: 'Room Conflicts',     value: 2, status: 'warning' },
    { label: 'Invalid Schedules',  value: 1, status: 'warning' },
    { label: 'Unassigned Rooms',   value: 0, status: 'good' },
    { label: 'Duplicate Entries',  value: 0, status: 'good' },
];

const activityFeed = [
    { time: '09:00 AM', action: 'CEA314 reserved by BSCPE 3-1',      type: 'reserved' },
    { time: '10:15 AM', action: 'Reservation cancelled for CEA300',  type: 'cancelled' },
    { time: '11:00 AM', action: 'Room changed CEA312 → CEA314',      type: 'change' },
    { time: '11:30 AM', action: 'Schedule imported via CSV',         type: 'import' },
];

const quickActions = [
    { label: 'Add Schedule',    icon: CalendarDays, href: '#' },
    { label: 'View Operations', icon: Activity,     href: '#' },
    { label: 'Add Room',        icon: DoorOpen,     href: '#' },
    { label: 'View Records',    icon: History,      href: '#' },
];

const activityTypeMap: Record<string, { dot: string; label: string; text: string; bg: string }> = {
    reserved:  { dot: 'bg-status-reserved',  label: 'Reserved',  text: 'text-status-reserved', bg: 'bg-status-reserved-bg' },
    cancelled: { dot: 'bg-status-occupied',  label: 'Cancelled', text: 'text-status-occupied', bg: 'bg-status-occupied-bg' },
    change:    { dot: 'bg-status-notice',    label: 'Change',    text: 'text-status-notice',   bg: 'bg-status-notice-bg' },
    import:    { dot: 'bg-pup-gold',         label: 'Import',    text: 'text-pup-gold-dark',   bg: 'bg-pup-gold-pale' },
};
</script>

<template>
    <Head title="Admin Dashboard"/>

    <AppLayout :breadcrumbs="breadcrumbs" :pageheader="pageheader">
        <div class="flex flex-col gap-5">

            <!-- ═══════════════════════════════════════════
                 TOP ROW: Unified Academic Term Overview
            ════════════════════════════════════════════ -->
            <div class="relative flex w-full flex-col justify-between overflow-hidden rounded-xl bg-gradient-to-br from-pup-maroon-deep via-pup-maroon-dark to-pup-maroon px-8 py-7 shadow-lg lg:flex-row lg:items-center">
                
                <!-- Decorative pattern -->
                <div class="pointer-events-none absolute -right-10 -top-20 size-64 rounded-full bg-pup-maroon-light/10 blur-3xl"></div>
                <div class="pointer-events-none absolute -bottom-16 -left-10 size-48 rounded-full border-[20px] border-white/5 opacity-60"></div>
                
                <!-- Left Panel: Academic Year & Emphasized Semester -->
                <div class="relative z-10 flex flex-col items-start gap-6 sm:flex-row sm:items-center">
                    <div class="hidden size-16 shrink-0 items-center justify-center rounded-2xl border border-pup-maroon-light/20 bg-pup-maroon-deep/50 shadow-inner backdrop-blur-sm sm:flex">
                        <School class="size-8 text-pup-gold"/>
                    </div>
                    <div class="flex flex-col items-start">
                        <div class="flex items-center gap-3">
                            <span class="text-[10px] font-extrabold uppercase tracking-widest text-pup-gold">
                                Academic Term Overview
                            </span>
                            <span class="flex items-center gap-1.5 rounded-full border border-[#4ade80]/30 bg-transparent px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider text-[#4ade80]">
                                <span class="size-1.5 rounded-full bg-[#4ade80]"></span> Active
                            </span>
                        </div>
                        
                        <div class="mt-1.5 flex flex-col items-start sm:flex-row sm:items-center sm:gap-4">
                            <h2 class="text-3xl font-black tracking-tight text-white xl:text-4xl drop-shadow-sm">
                                SY 2025–2026
                            </h2>
                            <span class="mt-2 inline-flex items-center rounded-lg bg-pup-gold px-3.5 py-1.5 text-xs font-black uppercase tracking-wider text-pup-maroon-deep shadow-md sm:mt-0">
                                2nd Semester
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Right Panel: Data Block (Icons beside numbers) -->
                <div class="relative z-10 mt-8 flex flex-wrap items-center justify-start gap-3 sm:gap-4 lg:mt-0 lg:justify-end">
                    
                    <!-- Rooms -->
                    <div class="group flex items-center gap-4 rounded-xl border border-white/10 bg-gradient-to-br from-white/10 to-transparent px-5 py-3 shadow-xl backdrop-blur-md transition-all hover:-translate-y-1 hover:border-pup-gold/30 hover:from-white/15">
                        <DoorOpen class="size-7 text-pup-gold/60 transition-colors group-hover:text-pup-gold" />
                        <div class="flex flex-col">
                            <span class="text-3xl font-black leading-none text-pup-gold">11</span>
                            <span class="mt-1 text-[9px] font-extrabold uppercase tracking-widest text-pup-maroon-pale">Rooms</span>
                        </div>
                    </div>
                    
                    <!-- Schedules -->
                    <div class="group flex items-center gap-4 rounded-xl border border-white/10 bg-gradient-to-br from-white/10 to-transparent px-5 py-3 shadow-xl backdrop-blur-md transition-all hover:-translate-y-1 hover:border-pup-gold/30 hover:from-white/15">
                        <CalendarDays class="size-7 text-pup-gold/60 transition-colors group-hover:text-pup-gold" />
                        <div class="flex flex-col">
                            <span class="text-3xl font-black leading-none text-pup-gold">486</span>
                            <span class="mt-1 text-[9px] font-extrabold uppercase tracking-widest text-pup-maroon-pale">Schedules</span>
                        </div>
                    </div>
                    
                    <!-- Faculty -->
                    <div class="group flex items-center gap-4 rounded-xl border border-white/10 bg-gradient-to-br from-white/10 to-transparent px-5 py-3 shadow-xl backdrop-blur-md transition-all hover:-translate-y-1 hover:border-pup-gold/30 hover:from-white/15">
                        <Users class="size-7 text-pup-gold/60 transition-colors group-hover:text-pup-gold" />
                        <div class="flex flex-col">
                            <span class="text-3xl font-black leading-none text-pup-gold">21</span>
                            <span class="mt-1 text-[9px] font-extrabold uppercase tracking-widest text-pup-maroon-pale">Faculty</span>
                        </div>
                    </div>

                </div>
                
            </div>

            <!-- ═══════════════════════════════════════════
                 STATUS CARDS
            ════════════════════════════════════════════ -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div
                    v-for="card in STATUS_CARDS"
                    :key="card.key"
                    class="group flex flex-col overflow-hidden rounded-xl border border-pup-gray-100 bg-white p-5 shadow-sm border-l-[5px] transition-all hover:shadow-md"
                    :class="card.accent"
                >
                    <div class="flex items-start justify-between">
                        <p class="text-[10px] font-extrabold uppercase tracking-widest text-pup-gray-400">
                            {{ card.label }}
                        </p>
                        <div class="flex size-7 items-center justify-center rounded-md" :class="card.bgCls">
                            <component :is="card.icon" class="size-3.5" :class="card.iconCls" />
                        </div>
                    </div>
                    
                    <div class="mt-1.5 text-4xl font-black text-pup-gray-800">
                        {{ roomStats[card.key] }}
                    </div>

                    <p class="mt-1 text-[11px] font-semibold text-pup-gray-400">{{ card.sub }}</p>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════
                 MAIN CONTENT GRID (Analytics Left, Operations Right)
            ════════════════════════════════════════════ -->
            <div class="grid gap-5 lg:grid-cols-2">

                <!-- LEFT COLUMN: Analytics & Health -->
                <div class="flex flex-col gap-5">
                    
                    <!-- Utilization Insights -->
                    <div class="flex flex-col overflow-hidden rounded-xl border border-pup-gray-100 bg-white shadow-sm">
                        <div class="border-b border-pup-gray-50 px-5 py-4 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <BarChart3 class="size-4 text-pup-maroon/70" />
                                <p class="text-[10px] font-extrabold uppercase tracking-widest text-pup-gray-800">
                                    Utilization Insights
                                </p>
                            </div>
                            <span class="rounded bg-pup-gray-50 px-2 py-0.5 text-[9px] font-bold text-pup-gray-400 border border-pup-gray-100">Daily Average</span>
                        </div>

                        <div class="flex flex-col gap-4 p-5">
                            <div class="grid grid-cols-2 gap-4">
                                <!-- Most used -->
                                <div class="flex flex-col rounded-xl border border-pup-maroon/20 bg-pup-maroon-pale p-5">
                                    <div class="flex items-center justify-between">
                                        <p class="text-[10px] font-extrabold uppercase tracking-widest text-pup-maroon/80">Most Used</p>
                                        <TrendingUp class="size-3.5 text-pup-maroon/60" />
                                    </div>
                                    <div class="mt-4 flex items-end justify-between gap-2">
                                        <p class="text-2xl font-black leading-none text-pup-maroon">CEA314</p>
                                        <span class="text-4xl font-black leading-none text-pup-maroon">87%</span>
                                    </div>
                                    <div class="mt-4 h-2 w-full overflow-hidden rounded-full bg-pup-white/80">
                                        <div class="h-full w-[87%] rounded-full bg-pup-maroon" />
                                    </div>
                                </div>

                                <!-- Least used -->
                                <div class="flex flex-col rounded-xl border border-pup-gray-100 bg-pup-gray-50 p-5">
                                    <div class="flex items-center justify-between">
                                        <p class="text-[10px] font-extrabold uppercase tracking-widest text-pup-gray-600">Least Used</p>
                                        <TrendingDown class="size-3.5 text-pup-gray-400" />
                                    </div>
                                    <div class="mt-4 flex items-end justify-between gap-2">
                                        <p class="text-2xl font-black leading-none text-pup-gray-800">CEA300</p>
                                        <span class="text-4xl font-black leading-none text-pup-gray-600">23%</span>
                                    </div>
                                    <div class="mt-4 h-2 w-full overflow-hidden rounded-full bg-pup-gray-200">
                                        <div class="h-full w-[23%] rounded-full bg-pup-gray-400" />
                                    </div>
                                </div>
                            </div>

                            <!-- Peak time -->
                            <div class="flex items-center gap-4 rounded-xl border border-pup-gold/30 bg-pup-gold-pale px-5 py-4">
                                <div class="flex size-10 items-center justify-center rounded-full bg-white shadow-sm">
                                    <Clock class="size-5 text-pup-gold-dark"/>
                                </div>
                                <div class="flex-1 flex items-center justify-between">
                                    <div>
                                        <p class="text-[10px] font-extrabold uppercase tracking-widest text-pup-gold-dark">Peak Department Hours</p>
                                        <p class="mt-0.5 text-[15px] font-black text-pup-gray-800">10:30 AM – 1:30 PM</p>
                                    </div>
                                    <Activity class="size-5 text-pup-gold-dark/40" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- System Health -->
                    <div class="flex flex-col overflow-hidden rounded-xl border border-pup-gray-100 bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-pup-gray-50 px-5 py-4">
                            <div class="flex items-center gap-2">
                                <ShieldAlert class="size-4 text-pup-gray-400" />
                                <p class="text-[10px] font-extrabold uppercase tracking-widest text-pup-gray-800">
                                    Schedule Health Diagnostics
                                </p>
                            </div>
                            <div class="flex items-center gap-1 rounded bg-pup-gold-pale px-2 py-0.5 text-[9px] font-bold text-pup-gold-dark border border-pup-gold/20">
                                <Activity class="size-3"/> Live
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 p-5 sm:grid-cols-2">
                            <div
                                v-for="item in scheduleHealth"
                                :key="item.label"
                                class="flex items-center justify-between rounded-lg border p-3.5 transition-colors"
                                :class="item.status === 'good' ? 'border-pup-gray-100 bg-white hover:bg-pup-gray-50' : 'border-status-warning/30 bg-status-warning-bg'"
                            >
                                <div class="flex items-center gap-2.5">
                                    <div class="flex size-7 items-center justify-center rounded-md" :class="item.status === 'good' ? 'bg-status-available-bg text-status-available' : 'bg-white text-status-warning shadow-sm'">
                                        <component
                                            :is="item.status === 'good' ? CheckCircle2 : AlertTriangle"
                                            class="size-3.5"
                                        />
                                    </div>
                                    <span class="text-xs font-bold text-pup-gray-800">{{ item.label }}</span>
                                </div>
                                <span class="text-sm font-black" :class="item.status === 'good' ? 'text-status-available' : 'text-status-warning'">
                                    {{ item.value }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN: Operations & Logs -->
                <div class="flex flex-col gap-5">
                    
                    <!-- Quick Actions -->
                    <div class="grid grid-cols-4 gap-3">
                        <a
                            v-for="action in quickActions"
                            :key="action.label"
                            :href="action.href"
                            class="group flex flex-col items-center justify-center gap-2 rounded-xl border border-pup-gray-100 bg-white px-2 py-3.5 shadow-sm transition-all hover:-translate-y-0.5 hover:border-pup-maroon-light hover:shadow-md"
                        >
                            <div class="flex size-9 items-center justify-center rounded-lg bg-pup-gray-50 text-pup-gray-400 transition-colors group-hover:bg-pup-maroon-pale group-hover:text-pup-maroon">
                                <component :is="action.icon" class="size-4.5" />
                            </div>
                            <span class="text-center text-[10px] font-extrabold leading-tight text-pup-gray-600 group-hover:text-pup-maroon">{{ action.label }}</span>
                        </a>
                    </div>

                    <!-- Live Audit Trail -->
                    <div class="flex flex-1 flex-col overflow-hidden rounded-xl border border-pup-gray-100 bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-pup-gray-50 px-5 py-4">
                            <div class="flex items-center gap-2">
                                <div class="relative flex size-2 items-center justify-center">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-pup-maroon opacity-75"></span>
                                    <span class="relative inline-flex size-1.5 rounded-full bg-pup-maroon"></span>
                                </div>
                                <p class="text-[10px] font-extrabold uppercase tracking-widest text-pup-gray-800">
                                    Live Audit Trail
                                </p>
                            </div>
                            <p class="text-[10px] font-bold text-pup-gray-400">
                                {{ new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) }}
                            </p>
                        </div>

                        <div class="flex-1 p-5">
                            <div class="flex flex-col gap-4">
                                <div
                                    v-for="(item, i) in activityFeed"
                                    :key="item.time + item.action"
                                    class="relative flex items-start gap-4"
                                >
                                    <div 
                                        v-if="i !== activityFeed.length - 1" 
                                        class="absolute bottom-[-16px] left-[11px] top-6 w-[2px] bg-pup-gray-100"
                                    />

                                    <div class="relative z-10 mt-1.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-white ring-1 ring-pup-gray-200 shadow-sm">
                                        <div
                                            class="size-2 rounded-full"
                                            :class="activityTypeMap[item.type]?.dot ?? 'bg-pup-gray-400'"
                                        />
                                    </div>

                                    <div class="flex-1 rounded-xl border border-pup-gray-100 bg-pup-gray-50/60 p-3.5 transition-colors hover:border-pup-maroon/30 hover:bg-pup-maroon-pale">
                                        <div class="mb-2 flex items-center justify-between">
                                            <span
                                                class="rounded-md px-2 py-1 text-[9px] font-extrabold uppercase tracking-widest shadow-sm"
                                                :class="[activityTypeMap[item.type]?.bg, activityTypeMap[item.type]?.text]"
                                            >
                                                {{ activityTypeMap[item.type]?.label }}
                                            </span>
                                            <span class="flex items-center gap-1 text-[10px] font-bold text-pup-gray-400">
                                                <Clock class="size-3" />
                                                {{ item.time }}
                                            </span>
                                        </div>
                                        <p class="text-xs font-bold text-pup-gray-800">{{ item.action }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                </div>

            </div>
        </div>
    </AppLayout>
</template>
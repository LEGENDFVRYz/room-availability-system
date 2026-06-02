<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppHeader from '@/components/AppHeader.vue';
import AppShell from '@/components/AppShell.vue';
import ToastNotification from '@/components/ToastNotification.vue';
import type { BreadcrumbItemType, PageHeader } from '@/types';
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
    pageheader?: PageHeader;
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const user = computed(() => (usePage().props as any).auth?.user);

</script>

<template>
    <AppShell class="flex-col bg-pup-off-white">
        <AppHeader :breadcrumbs="breadcrumbs" />

        <!-- Page Header -->
        <div
            v-if="pageheader"
            class="relative overflow-hidden border-b-[3px] border-pup-gold bg-[linear-gradient(135deg,var(--tw-gradient-stops))] from-pup-maroon-deep via-pup-maroon via-60% to-pup-maroon-light pt-8 pb-7 px-8 after:box-content after:absolute after:-right-10 after:-top-10 after:h-[200px] after:w-[200px] after:rounded-full after:border-[40px] after:border-[rgba(240,180,41,0.08)] after:content-[''] after:pointer-events-none"
        >
            <h1 class="relative z-10 mb-1 text-[22px] font-semibold text-white">{{ pageheader.title }}</h1>
            <p v-if="pageheader.desc" class="relative z-10 text-[13px] text-white/65">{{ pageheader.desc }}</p>
        </div>

        <AppContent>
            <slot />
        </AppContent>

        <!-- Page Footer -->
        <footer class="w-full mt-auto bg-pup-maroon-deep px-6 py-5 relative z-20">
            <div class="mx-auto flex w-full max-w-6xl flex-col items-center justify-between gap-4 text-center sm:flex-row sm:text-left">
                
                <div class="text-[11px] leading-relaxed text-white/50">
                    <strong class="font-medium text-pup-gold-light">Polytechnic University of the Philippines - Manila</strong>
                    <span class="hidden sm:inline"> · </span><br class="sm:hidden" />
                    College of Engineering · Computer Engineering Department<br>
                    CPE Room Availability &amp; Scheduling System · © June 2026
                </div>

                <div class="flex items-center gap-3 text-[11px] font-medium text-white/70">
                    
                    <template v-if="user">
                        <Link :href="route('logout')" method="post" :data="{ redirect_to: 'kiosk' }" as="button" class="hover:text-pup-gold transition-colors">
                            Go to Public View
                        </Link>
                        <span class="text-white/20">|</span>
                        <Link :href="route('logout')" method="post" as="button" class="hover:text-white transition-colors">
                            Log Out
                        </Link>
                        </template>
                        
                        <template v-else>
                        <div class="flex items-center gap-1.5">
                            <span class="text-white/40">Need staff access?</span>
                            <Link :href="route('register')" class="font-bold text-pup-gold hover:text-pup-gold-light transition-colors hover:underline underline-offset-2">
                                Register Here
                            </Link>
                        </div>
                        <span class="text-white/20">|</span>
                        <Link :href="route('login')" class="font-bold hover:text-white transition-colors">
                            Admin Sign In
                        </Link>
                        </template>

                </div>
                
            </div>
        </footer>

        <ToastNotification />
    </AppShell>
</template>

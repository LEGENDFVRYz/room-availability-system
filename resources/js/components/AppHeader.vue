<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { getInitials } from '@/composables/useInitials';
import type { BreadcrumbItem, NavItem, SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { CalendarDays, ClipboardList, LayoutDashboard, Megaphone, Menu, Settings2, Activity } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    breadcrumbs?: BreadcrumbItem[];
}

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage<SharedData>();
const auth = computed(() => page.props.auth);

const inAdminDomain = computed(() => page.url.startsWith('/admin'));
const inKioskDomain = computed(() => page.url.startsWith('/kiosk'));

const isAdmin = computed(() => (page.props.auth?.user ? true : false));

const isActiveNavItem = (item: NavItem): boolean => {
    const url = page.url.replace(/\/$/, '');
    const base = item.href.replace(/\/$/, '');

    if (base === '/admin/dashboard') {
        return url === '/admin' || url.startsWith('/admin/dashboard');
    }
    return url === base || url.startsWith(base + '/');
};

const adminNavItems: NavItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard/', icon: LayoutDashboard },
    { title: 'Operations', href: '/admin/operations/', icon: Activity },
    { title: 'Schedules', href: '/admin/schedules/', icon: CalendarDays },
    { title: 'Manages', href: '/admin/manage/', icon: Settings2 },
    { title: 'Records', href: '#', icon: ClipboardList },
];

const kioskNavItems: NavItem[] = [
    { title: 'Dashboard', href: '/kiosk/dashboard/', icon: LayoutDashboard },
    { title: 'Schedules', href: '/kiosk/schedules/', icon: CalendarDays },
    { title: 'Announcements', href: '/kiosk/schedules/', icon: Megaphone },
];

const navItems = computed<NavItem[]>(() => {
    if (inAdminDomain.value) return adminNavItems;
    if (inKioskDomain.value) return kioskNavItems;
    return [];
});

const dashboardLink = computed(() => {
    if (inAdminDomain.value) return '/admin/dashboard';
    if (inKioskDomain.value) return '/kiosk/dashboard';
    return '/';
});
</script>


<template>
    <div>
        <!-- Topbar -->
        <div class="flex items-center bg-pup-maroon-deep px-6 py-1.5">
            <span class="text-[11px] tracking-[0.04em] text-white/60">
                <strong class="font-medium text-pup-gold-light">PUP Manila</strong>
                · College of Engineering · Computer Engineering Department
            </span>
        </div>

        <!-- Main Nav -->
        <nav class="sticky top-0 z-[100] flex h-[60px] items-center justify-between border-b-[3px] border-pup-gold bg-pup-maroon px-6">
            <!-- Brand -->
            <Link :href="dashboardLink" class="flex shrink-0 items-center gap-3">
                <div
                    class="flex h-[38px] w-[38px] shrink-0 items-center justify-center rounded-full border-2 border-pup-gold bg-pup-maroon-deep"
                >
                    <AppLogoIcon class="size-[20px] fill-current text-pup-gold" />
                </div>
                <div class="hidden sm:block">
                    <div class="text-[15px] font-semibold leading-snug text-white">CPE Room System</div>
                    <div class="text-[11px] text-pup-gold-light">Real-Time Availability</div>
                </div>
            </Link>

            <!-- Right Side: Nav Links + Avatar -->
            <div class="flex items-center gap-5">
                <!-- Desktop Nav Links -->
                <div class="hidden items-center gap-1 lg:flex">
                    <Link
                        v-for="item in navItems"
                        :key="item.title"
                        :href="item.href"
                        class="flex items-center gap-1.5 rounded-md px-3.5 py-1.5 text-[13px] font-medium transition-all duration-150"
                        :class="
                            isActiveNavItem(item)
                                ? 'bg-pup-gold text-pup-maroon-deep'
                                : 'text-white/75 hover:bg-white/10 hover:text-white'
                        "
                    >
                        <component v-if="item.icon" :is="item.icon" class="h-4 w-4" />
                        {{ item.title }}
                    </Link>
                </div>

                <!-- Mobile Menu -->
                <div class="lg:hidden">
                    <Sheet>
                        <SheetTrigger :as-child="true">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-9 w-9 text-white/75 hover:bg-white/10 hover:text-white"
                            >
                                <Menu class="h-5 w-5" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="left" class="w-[280px] bg-pup-maroon-dark p-0">
                            <SheetTitle class="sr-only">Navigation Menu</SheetTitle>
                            <SheetHeader
                                class="flex flex-row items-center gap-3 border-b border-white/10 p-5"
                            >
                                <div
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border-2 border-pup-gold bg-pup-maroon-deep"
                                >
                                    <AppLogoIcon class="size-4 fill-current text-pup-gold-light" />
                                </div>
                                <div class="text-left">
                                    <div class="text-sm font-semibold text-white">CPE Room System</div>
                                    <div class="text-[11px] text-pup-gold-light">
                                        {{ inAdminDomain ? 'Admin Panel' : 'Kiosk Display' }}
                                    </div>
                                </div>
                            </SheetHeader>
                            <nav class="flex flex-col gap-1 p-4">
                                <Link
                                    v-for="item in navItems"
                                    :key="item.title"
                                    :href="item.href"
                                    class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium transition-all duration-150"
                                    :class="
                                        isActiveNavItem(item)
                                            ? 'bg-pup-gold text-pup-maroon-deep'
                                            : 'text-white/75 hover:bg-white/10 hover:text-white'
                                    "
                                >
                                    <component v-if="item.icon" :is="item.icon" class="h-4 w-4" />
                                    {{ item.title }}
                                </Link>
                            </nav>
                        </SheetContent>
                    </Sheet>
                </div>

                <!-- User Dropdown (Admin only) -->
                <DropdownMenu v-if="isAdmin">
                    <DropdownMenuTrigger :as-child="true">
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-[38px] w-[38px] rounded-full border-2 border-pup-gold bg-pup-maroon-deep p-0 hover:border-pup-gold-light hover:bg-pup-maroon focus-within:ring-2 focus-within:ring-pup-gold"
                        >
                            <Avatar class="size-full overflow-hidden rounded-full bg-pup-maroon-deep">
                                <AvatarImage :src="auth.user.avatar ?? ''" :alt="auth.user.name" />
                                <AvatarFallback
                                    class="rounded-full bg-transparent text-[13px] font-semibold text-pup-gold-light"
                                >
                                    {{ getInitials(auth.user?.name) }}
                                </AvatarFallback>
                            </Avatar>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56">
                        <UserMenuContent :user="auth.user" />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </nav>

        <!-- Breadcrumbs -->
        <!-- TEMPORARY REMOVED DUE TO DESIGN CONFLICTS -->
        <!-- <div v-if="props.breadcrumbs.length > 1" class="flex w-full border-b border-sidebar-border/70">
            <div class="mx-auto flex h-12 w-full items-center justify-start px-4 text-neutral-500 md:max-w-7xl">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </div>
        </div> -->
    </div>
</template>

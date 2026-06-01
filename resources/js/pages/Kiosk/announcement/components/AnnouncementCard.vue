<script setup lang="ts">
type AnnouncementType =
    | 'academic_term'
    | 'schedule_update'
    | 'class_cancellation'
    | 'room_change'
    | 'special_class'
    | 'room_maintenance'
    | 'room_reserved';

type NoticeDesign = 'general' | 'exception' | 'override';

const props = defineProps<{
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
}>();

const designStyles: Record<NoticeDesign, {
    rail: string;
    badge: string;
    dot: string;
    label: string;
}> = {
    general: {
        rail: 'bg-pup-maroon',
        badge: 'border-pup-maroon/15 bg-pup-maroon-pale/70 text-pup-maroon-deep',
        dot: 'bg-pup-maroon',
        label: 'General Notice',
    },
    exception: {
        rail: 'bg-pup-gold-dark',
        badge: 'border-pup-gold/35 bg-pup-gold-pale/80 text-pup-maroon-deep',
        dot: 'bg-pup-gold-dark',
        label: 'Daily Exception',
    },
    override: {
        rail: 'bg-pup-gray-400',
        badge: 'border-pup-gray-200 bg-pup-gray-100 text-pup-gray-800',
        dot: 'bg-pup-gray-400',
        label: 'Room Override',
    },
};

const currentStyle = designStyles[props.design];
</script>

<template>
    <article class="relative overflow-hidden rounded-2xl border border-pup-gray-200 bg-pup-white p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md">
        <div class="absolute inset-y-0 left-0 w-1.5" :class="currentStyle.rail" />

        <div class="space-y-4 pl-2">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-bold uppercase tracking-wide"
                        :class="currentStyle.badge"
                    >
                        <span class="size-2 rounded-full" :class="currentStyle.dot" />
                        {{ typeLabel }}
                    </span>

                    <span v-if="isPinned" class="text-xs font-semibold uppercase tracking-wide text-pup-maroon">
                        Pinned
                    </span>
                </div>

                <span class="text-xs font-medium text-pup-gray-400">
                    {{ currentStyle.label }}
                </span>
            </header>

            <div class="space-y-1.5">
                <h3 class="text-base font-bold leading-snug text-pup-gray-800 sm:text-lg">
                    {{ title }}
                </h3>
                <p class="max-w-3xl text-sm leading-6 text-pup-gray-600">
                    {{ body }}
                </p>
            </div>

            <footer class="border-t border-pup-gray-500 pt-3 text-xs font-medium text-pup-gray-600 flex justify-between">
                <div v-if="postedAt || expiresAt" class="mt-1 flex flex-wrap gap-x-2 gap-y-1">
                    <span v-if="postedAt">Posted {{ postedAt }}</span>
                    <span v-if="postedAt && expiresAt" aria-hidden="true">•</span>
                    <span v-if="expiresAt">Expires {{ expiresAt }}</span>
                </div>

                <div v-if="room || schedule" class="flex flex-wrap gap-x-2 gap-y-1 text-pup-maroon">
                    <span v-if="room" class="font-bold">{{ room }}</span>
                    <span v-if="room && schedule" aria-hidden="true">•</span>
                    <span v-if="schedule">{{ schedule }}</span>
                </div>
            </footer>
        </div>
    </article>
</template>

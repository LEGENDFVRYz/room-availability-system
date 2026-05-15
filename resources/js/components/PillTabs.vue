<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import type { Component } from 'vue';
import { computed } from 'vue';

interface Tab {
    label: string;
    href: string;
    icon?: Component;
}

defineProps<{ tabs: Tab[] }>();

const page = usePage();
const url  = computed(() => page.url);

function isActive(href: string) {
    return url.value === href
        || url.value.startsWith(href + '?')
        || url.value.startsWith(href + '/');
}
</script>

<template>
    <div class="inline-flex items-center rounded-full bg-pup-gold/15 p-1 ring-1 ring-black/[0.06]">
        <Link
            v-for="tab in tabs"
            :key="tab.href"
            :href="tab.href"
            :class="[
                'flex items-center gap-2 rounded-full px-5 py-1.5 text-sm font-medium transition-all duration-150',
                isActive(tab.href)
                    ? 'bg-pup-maroon text-white shadow-sm'
                    : 'text-gray-500 hover:text-pup-maroon',
            ]"
        >
            <component v-if="tab.icon" :is="tab.icon" class="h-4 w-4" />
            {{ tab.label }}
        </Link>
    </div>
</template>

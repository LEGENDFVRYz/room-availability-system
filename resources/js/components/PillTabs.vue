<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import type { Component } from 'vue';
import { computed } from 'vue';

interface Tab {
    label: string;
    href?: string;
    value?: string;
    icon?: Component;
}

const props = defineProps<{
    tabs: Tab[];
    activeValue?: string;
}>();

const emit = defineEmits<{
    'update:activeValue': [value: string];
}>();

const page = usePage();
const url = computed(() => page.url);

function isActive(tab: Tab) {
    if (props.activeValue !== undefined) {
        return tab.value === props.activeValue;
    }

    if (!tab.href) {
        return false;
    }

    return url.value === tab.href
        || url.value.startsWith(tab.href + '?')
        || url.value.startsWith(tab.href + '/');
}

function tabClasses(tab: Tab) {
    return [
        'flex items-center gap-2 rounded-full px-5 py-1.5 text-sm font-medium transition-all duration-150',
        isActive(tab)
            ? 'bg-pup-maroon text-white shadow-sm'
            : 'text-pup-gray-600 hover:text-pup-maroon',
    ];
}

function selectTab(tab: Tab) {
    if (tab.value === undefined) {
        return;
    }

    emit('update:activeValue', tab.value);
}
</script>

<template>
    <div class="inline-flex items-center rounded-full bg-pup-gold/15 p-1 ring-1 ring-black/[0.06]">
        <template
            v-for="tab in tabs"
            :key="tab.href ?? tab.value ?? tab.label"
        >
            <Link
                v-if="tab.href"
                :href="tab.href"
                :class="tabClasses(tab)"
            >
                <component v-if="tab.icon" :is="tab.icon" class="h-4 w-4" />
                {{ tab.label }}
            </Link>

            <button
                v-else
                type="button"
                :class="tabClasses(tab)"
                @click="selectTab(tab)"
            >
                <component v-if="tab.icon" :is="tab.icon" class="h-4 w-4" />
                {{ tab.label }}
            </button>
        </template>
    </div>
</template>

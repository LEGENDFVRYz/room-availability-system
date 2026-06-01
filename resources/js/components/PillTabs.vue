<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import type { Component } from 'vue';
import { computed } from 'vue';

type LinkTab = {
    label: string;
    href: string;
    icon?: Component;
};

type ValueTab = {
    label: string;
    value: string;
    icon?: Component;
};

type Tab = LinkTab | ValueTab;

const props = defineProps<{
    tabs: Tab[];
    activeValue?: string;
}>();

const emit = defineEmits<{
    'update:activeValue': [value: string];
}>();

const page = usePage();
const url = computed(() => page.url);

function isValueTab(tab: Tab): tab is ValueTab {
    return 'value' in tab;
}

function getTabKey(tab: Tab): string {
    return isValueTab(tab) ? tab.value : tab.href;
}

function isActive(tab: Tab): boolean {
    if (isValueTab(tab)) {
        return props.activeValue === tab.value;
    }

    return url.value === tab.href
        || url.value.startsWith(tab.href + '?')
        || url.value.startsWith(tab.href + '/');
}

function selectTab(tab: ValueTab) {
    emit('update:activeValue', tab.value);
}

function tabClass(tab: Tab): string[] {
    return [
        'flex items-center gap-2 rounded-full px-5 py-1.5 text-sm font-medium transition-all duration-150',
        isActive(tab)
            ? 'bg-pup-maroon text-white shadow-sm'
            : 'text-gray-500 hover:text-pup-maroon',
    ];
}
</script>

<template>
    <div class="inline-flex items-center rounded-full bg-pup-gold/15 p-1 ring-1 ring-black/[0.06]">
        <template v-for="tab in tabs" :key="getTabKey(tab)">
            <button
                v-if="isValueTab(tab)"
                type="button"
                :class="tabClass(tab)"
                @click="selectTab(tab)"
            >
                <component v-if="tab.icon" :is="tab.icon" class="h-4 w-4" />
                {{ tab.label }}
            </button>

            <Link
                v-else
                :href="tab.href"
                :class="tabClass(tab)"
            >
                <component v-if="tab.icon" :is="tab.icon" class="h-4 w-4" />
                {{ tab.label }}
            </Link>
        </template>
    </div>
</template>

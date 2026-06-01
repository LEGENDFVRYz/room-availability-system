<script setup lang="ts">
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="min-h-screen w-full bg-[#f9f3f3]">
        <slot />
    </div>
</template>

 
        <!-- LARGE WATERMARK
        <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
            <img
                src="/images/pup-logo.png"
                alt="PUP Watermark"
                class="w-[700px] opacity-[0.035] blur-[1px]"
            />
        </div> -->
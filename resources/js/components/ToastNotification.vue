<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import type { SharedData } from '@/types';
import { CheckCircle2, XCircle, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Toast { type: 'success' | 'error'; message: string }

const toast = ref<Toast | null>(null);
let timer: ReturnType<typeof setTimeout> | null = null;

const page = usePage<SharedData>();

watch(
    () => page.props.flash,
    (flash) => {
        const message = flash?.success || flash?.error;
        if (!message) return;
        if (timer) clearTimeout(timer);
        toast.value = { type: flash?.success ? 'success' : 'error', message };
        timer = setTimeout(() => { toast.value = null; }, 3000);
    },
    { immediate: true, deep: true },
);

function dismiss() {
    if (timer) clearTimeout(timer);
    toast.value = null;
}
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-all duration-300 ease-out"
            enter-from-class="translate-x-full opacity-0"
            enter-to-class="translate-x-0 opacity-100"
            leave-active-class="transition-all duration-200 ease-in"
            leave-from-class="translate-x-0 opacity-100"
            leave-to-class="translate-x-full opacity-0"
        >
            <div
                v-if="toast"
                class="fixed bottom-6 right-6 z-[200] w-80 overflow-hidden rounded-xl border bg-white shadow-xl"
                :class="toast.type === 'success' ? 'border-green-200' : 'border-red-200'"
            >
                <!-- Content -->
                <div class="flex items-start gap-3 px-4 py-3.5">
                    <CheckCircle2
                        v-if="toast.type === 'success'"
                        class="mt-0.5 h-5 w-5 shrink-0 text-green-500"
                    />
                    <XCircle
                        v-else
                        class="mt-0.5 h-5 w-5 shrink-0 text-red-400"
                    />
                    <p class="flex-1 text-sm font-medium text-gray-800 leading-snug">
                        {{ toast.message }}
                    </p>
                    <button
                        @click="dismiss"
                        class="rounded p-0.5 text-gray-400 transition-colors hover:text-gray-700"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <!-- Auto-dismiss progress bar -->
                <div
                    class="h-1"
                    :class="toast.type === 'success' ? 'bg-green-50' : 'bg-red-50'"
                >
                    <div
                        :class="['h-full toast-progress', toast.type === 'success' ? 'bg-green-400' : 'bg-red-400']"
                    />
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.toast-progress {
    animation: toast-shrink 3s linear forwards;
}

@keyframes toast-shrink {
    from { width: 100%; }
    to   { width: 0%; }
}
</style>

<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { X, TriangleAlert } from 'lucide-vue-next';
import { ref, onMounted, onUnmounted } from 'vue';

interface Room {
    id: number;
    code: string;
    name: string;
}

const props = defineProps<{ room: Room }>();
const emit  = defineEmits<{ close: [] }>();

const processing = ref(false);

function confirm() {
    processing.value = true;
    router.delete(`/admin/manage/rooms/${props.room.id}`, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
        onFinish:  () => { processing.value = false; },
    });
}

function handleKey(e: KeyboardEvent) {
    if (e.key === 'Escape') emit('close');
}
onMounted(() => document.addEventListener('keydown', handleKey));
onUnmounted(() => document.removeEventListener('keydown', handleKey));
</script>

<template>
    <Teleport to="body">
        <div
            class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4 backdrop-blur-[2px]"
            @click.self="emit('close')"
        >
            <div class="w-full max-w-sm overflow-hidden rounded-2xl bg-white shadow-2xl">

                <!-- Header -->
                <div class="flex items-start justify-between bg-pup-maroon-deep px-6 py-5">
                    <div>
                        <h2 class="text-[17px] font-semibold text-white">Deactivate Room</h2>
                        <p class="mt-0.5 text-xs text-white/55">This action can be reversed via Edit</p>
                    </div>
                    <button
                        @click="emit('close')"
                        class="rounded-lg p-1.5 text-white/60 transition hover:bg-white/10 hover:text-white"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <!-- Body -->
                <div class="px-6 py-6">
                    <div class="flex gap-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100">
                            <TriangleAlert class="h-5 w-5 text-red-500" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-800">
                                Deactivate
                                <span class="text-pup-maroon">"{{ room.name }}"</span>?
                            </p>
                            <p class="mt-1.5 text-sm text-gray-500 leading-relaxed">
                                This room will be marked as <strong>inactive</strong> and hidden from public views and schedules.
                                You can reactivate it anytime through the Edit form.
                            </p>
                            <p class="mt-2 font-mono text-xs text-gray-400">{{ room.code }}</p>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-end gap-2 border-t border-gray-100 px-6 py-4">
                    <button
                        type="button"
                        @click="emit('close')"
                        class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        :disabled="processing"
                        @click="confirm"
                        class="rounded-lg bg-red-600 px-5 py-2 text-sm font-medium text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{ processing ? 'Deactivating…' : 'Deactivate' }}
                    </button>
                </div>

            </div>
        </div>
    </Teleport>
</template>

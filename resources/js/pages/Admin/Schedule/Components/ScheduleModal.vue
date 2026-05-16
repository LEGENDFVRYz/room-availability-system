<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { X, ChevronDown, Pencil, Trash2 } from 'lucide-vue-next';
import { computed, watch, onMounted, onUnmounted } from 'vue';

// ─── Types ────────────────────────────────────────────────────────────────────

export interface ScheduleFormData {
    id: number;
    subject_code: string;
    subject_title: string;
    section: string;
    instructor_name: string | null;
    room_id: number | null;
    day_of_week: number; // 1–7
    start_time: string;  // "HH:MM"
    end_time: string;    // "HH:MM"
}

interface Room {
    id: number;
    name: string;
    code: string;
}

// ─── Props / emits ────────────────────────────────────────────────────────────

const props = defineProps<{
    schedule?: ScheduleFormData | null;
    defaultSection?: string;
    rooms: Room[];
}>();

const emit = defineEmits<{
    close: [];
    requestDelete: [schedule: ScheduleFormData];
}>();

// ─── Mode ─────────────────────────────────────────────────────────────────────

const isCreate = computed(() => !props.schedule);
const title    = computed(() => isCreate.value ? 'Add Schedule' : 'Edit Schedule');

// ─── Form ─────────────────────────────────────────────────────────────────────

const form = useForm({
    subject_code:    props.schedule?.subject_code    ?? '',
    subject_title:   props.schedule?.subject_title   ?? '',
    section:         props.schedule?.section         ?? (props.defaultSection ?? ''),
    instructor_name: props.schedule?.instructor_name ?? '',
    room_id:         props.schedule?.room_id         ?? (props.rooms[0]?.id ?? null),
    day_of_week:     props.schedule?.day_of_week     ?? 1,
    start_time:      props.schedule?.start_time      ?? '07:30',
    end_time:        props.schedule?.end_time        ?? '09:00',
});

watch(() => props.schedule, (s) => {
    form.subject_code    = s?.subject_code    ?? '';
    form.subject_title   = s?.subject_title   ?? '';
    form.section         = s?.section         ?? (props.defaultSection ?? '');
    form.instructor_name = s?.instructor_name ?? '';
    form.room_id         = s?.room_id         ?? (props.rooms[0]?.id ?? null);
    form.day_of_week     = s?.day_of_week     ?? 1;
    form.start_time      = s?.start_time      ?? '07:30';
    form.end_time        = s?.end_time        ?? '09:00';
    form.clearErrors();
});

function submit() {
    if (isCreate.value) {
        form.post('/admin/schedules', {
            preserveScroll: true,
            onSuccess: () => emit('close'),
        });
    } else {
        form.patch(`/admin/schedules/${props.schedule!.id}`, {
            preserveScroll: true,
            onSuccess: () => emit('close'),
        });
    }
}

// ─── Keyboard / backdrop ──────────────────────────────────────────────────────

function handleKey(e: KeyboardEvent) {
    if (e.key === 'Escape') emit('close');
}
onMounted(() => document.addEventListener('keydown', handleKey));
onUnmounted(() => document.removeEventListener('keydown', handleKey));

// ─── Helpers ──────────────────────────────────────────────────────────────────

const DAY_OPTIONS = [
    { value: 1, label: 'Monday' },
    { value: 2, label: 'Tuesday' },
    { value: 3, label: 'Wednesday' },
    { value: 4, label: 'Thursday' },
    { value: 5, label: 'Friday' },
    { value: 6, label: 'Saturday' },
    { value: 7, label: 'Sunday' },
];

const inputClass = (hasError: boolean) =>
    'h-9 w-full rounded-lg border bg-gray-50/50 px-3 text-sm text-gray-800 placeholder-gray-400 ' +
    'focus:bg-white focus:outline-none focus:ring-2 focus:ring-pup-maroon/10 ' +
    (hasError ? 'border-red-300 focus:border-red-400' : 'border-gray-200 focus:border-pup-maroon/40');
</script>

<template>
    <Teleport to="body">
        <div
            class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4 backdrop-blur-[2px]"
            @click.self="emit('close')"
        >
            <div class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">

                <!-- Header -->
                <div class="flex items-start justify-between bg-pup-maroon-deep px-6 py-5">
                    <div>
                        <h2 class="text-[17px] font-semibold text-white">{{ title }}</h2>
                        <p class="mt-0.5 text-xs text-white/55">
                            <template v-if="isCreate">Fill in the details to add a class schedule</template>
                            <template v-else>{{ schedule!.section }} · {{ schedule!.subject_code }}</template>
                        </p>
                    </div>
                    <div class="flex items-center gap-1">
                        <button
                            v-if="!isCreate"
                            @click="emit('requestDelete', schedule!)"
                            class="rounded-lg p-1.5 text-white/50 transition hover:bg-red-500/20 hover:text-red-300"
                            title="Delete this schedule entry"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                        <button
                            @click="emit('close')"
                            class="rounded-lg p-1.5 text-white/60 transition hover:bg-white/10 hover:text-white"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                </div>

                <!-- Body -->
                <div class="px-6 py-5">
                    <p class="mb-4 text-[10px] font-semibold uppercase tracking-widest text-pup-maroon">
                        Schedule Information
                    </p>

                    <div class="space-y-3">

                        <!-- Section -->
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-gray-600">
                                Section <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.section"
                                type="text"
                                maxlength="30"
                                placeholder="e.g. BSCPE 4-3"
                                :class="inputClass(!!form.errors.section)"
                            />
                            <p v-if="form.errors.section" class="mt-1 text-xs text-red-500">{{ form.errors.section }}</p>
                        </div>

                        <!-- Subject Code + Subject Title -->
                        <div class="grid grid-cols-5 gap-3">
                            <div class="col-span-2">
                                <label class="mb-1.5 block text-xs font-medium text-gray-600">
                                    Subject Code <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="form.subject_code"
                                    type="text"
                                    maxlength="20"
                                    placeholder="e.g. CMPE 401"
                                    :class="inputClass(!!form.errors.subject_code) + ' font-mono'"
                                />
                                <p v-if="form.errors.subject_code" class="mt-1 text-xs text-red-500">{{ form.errors.subject_code }}</p>
                            </div>
                            <div class="col-span-3">
                                <label class="mb-1.5 block text-xs font-medium text-gray-600">
                                    Subject Title <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="form.subject_title"
                                    type="text"
                                    maxlength="100"
                                    placeholder="e.g. Embedded Systems"
                                    :class="inputClass(!!form.errors.subject_title)"
                                />
                                <p v-if="form.errors.subject_title" class="mt-1 text-xs text-red-500">{{ form.errors.subject_title }}</p>
                            </div>
                        </div>

                        <!-- Room -->
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-gray-600">
                                Room <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <select
                                    v-model.number="form.room_id"
                                    :class="inputClass(!!form.errors.room_id) + ' appearance-none pl-3 pr-8'"
                                >
                                    <option v-for="r in rooms" :key="r.id" :value="r.id">
                                        {{ r.name }} ({{ r.code }})
                                    </option>
                                </select>
                                <ChevronDown class="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                            </div>
                            <p v-if="form.errors.room_id" class="mt-1 text-xs text-red-500">{{ form.errors.room_id }}</p>
                        </div>

                        <!-- Day + Start Time + End Time -->
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-gray-600">
                                    Day <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <select
                                        v-model.number="form.day_of_week"
                                        :class="inputClass(!!form.errors.day_of_week) + ' appearance-none pl-3 pr-8'"
                                    >
                                        <option v-for="d in DAY_OPTIONS" :key="d.value" :value="d.value">
                                            {{ d.label }}
                                        </option>
                                    </select>
                                    <ChevronDown class="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                                </div>
                                <p v-if="form.errors.day_of_week" class="mt-1 text-xs text-red-500">{{ form.errors.day_of_week }}</p>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-gray-600">
                                    Start Time <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="form.start_time"
                                    type="time"
                                    :class="inputClass(!!form.errors.start_time)"
                                />
                                <p v-if="form.errors.start_time" class="mt-1 text-xs text-red-500">{{ form.errors.start_time }}</p>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-gray-600">
                                    End Time <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="form.end_time"
                                    type="time"
                                    :class="inputClass(!!form.errors.end_time)"
                                />
                                <p v-if="form.errors.end_time" class="mt-1 text-xs text-red-500">{{ form.errors.end_time }}</p>
                            </div>
                        </div>

                        <!-- Instructor Name -->
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-gray-600">
                                Instructor
                                <span class="ml-1 text-gray-400">(optional)</span>
                            </label>
                            <input
                                v-model="form.instructor_name"
                                type="text"
                                maxlength="100"
                                placeholder="e.g. Prof. Santos"
                                :class="inputClass(!!form.errors.instructor_name)"
                            />
                            <p v-if="form.errors.instructor_name" class="mt-1 text-xs text-red-500">{{ form.errors.instructor_name }}</p>
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
                        :disabled="form.processing"
                        @click="submit"
                        class="flex items-center gap-1.5 rounded-lg bg-pup-maroon px-5 py-2 text-sm font-medium text-white transition hover:bg-pup-maroon-deep disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <Pencil v-if="!isCreate" class="h-3.5 w-3.5" />
                        {{ form.processing ? 'Saving…' : (isCreate ? 'Add Schedule' : 'Save Changes') }}
                    </button>
                </div>

            </div>
        </div>
    </Teleport>
</template>

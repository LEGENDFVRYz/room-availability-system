<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { X, ChevronDown, Building2, Hash, Layers, Users, ArrowUpDown, Pencil } from 'lucide-vue-next';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

export interface Room {
    id: number;
    code: string;
    name: string;
    room_type: string;
    type_label: string;
    floor: number | null;
    capacity: number | null;
    display_order: number;
    is_active: boolean;
}

const props = defineProps<{ room?: Room | null }>();
const emit  = defineEmits<{ close: [] }>();

// ── Mode ──────────────────────────────────────────────────────────────────────

const isCreateMode = computed(() => !props.room);
const isEditing    = ref(false);
const showForm     = computed(() => isCreateMode.value || isEditing.value);

const headerTitle = computed(() => {
    if (isCreateMode.value)  return 'Add New Room';
    if (isEditing.value)     return 'Edit Room';
    return 'Room Details';
});

// ── Form ──────────────────────────────────────────────────────────────────────

const form = useForm({
    code:          props.room?.code          ?? '',
    name:          props.room?.name          ?? '',
    room_type:     props.room?.room_type     ?? 'classroom',
    floor:         (props.room?.floor        ?? '') as number | '',
    capacity:      (props.room?.capacity     ?? '') as number | '',
    display_order: props.room?.display_order ?? 0,
    is_active:     props.room?.is_active     ?? true,
});

watch(() => props.room, (r) => {
    form.code          = r?.code          ?? '';
    form.name          = r?.name          ?? '';
    form.room_type     = r?.room_type     ?? 'classroom';
    form.floor         = r?.floor         ?? '';
    form.capacity      = r?.capacity      ?? '';
    form.display_order = r?.display_order ?? 0;
    form.is_active     = r?.is_active     ?? true;
    isEditing.value    = false;
});

function startEditing() {
    isEditing.value = true;
}

function cancelEditing() {
    form.reset();
    isEditing.value = false;
}

function submit() {
    if (isCreateMode.value) {
        form.post('/admin/manage/rooms', {
            preserveScroll: true,
            onSuccess: () => emit('close'),
        });
    } else {
        form.patch(`/admin/manage/rooms/${props.room!.id}`, {
            preserveScroll: true,
            onSuccess: () => emit('close'),
        });
    }
}

// ── Keyboard + backdrop ───────────────────────────────────────────────────────

function handleKey(e: KeyboardEvent) {
    if (e.key !== 'Escape') return;
    if (isEditing.value) cancelEditing();
    else emit('close');
}
onMounted(() => document.addEventListener('keydown', handleKey));
onUnmounted(() => document.removeEventListener('keydown', handleKey));

// ── Helpers ───────────────────────────────────────────────────────────────────

const ROOM_TYPES = [
    { value: 'classroom',    label: 'Classroom' },
    { value: 'laboratory',   label: 'Laboratory' },
    { value: 'office',       label: 'Office' },
    { value: 'special_room', label: 'Special Room' },
    { value: 'other',        label: 'Other' },
];

const TYPE_BADGE: Record<string, string> = {
    classroom:    'bg-blue-50 text-blue-700 border-blue-200',
    laboratory:   'bg-violet-50 text-violet-700 border-violet-200',
    office:       'bg-orange-50 text-orange-700 border-orange-200',
    special_room: 'bg-teal-50 text-teal-700 border-teal-200',
    other:        'bg-gray-100 text-gray-600 border-gray-200',
};
</script>

<template>
    <Teleport to="body">
        <div
            class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4 backdrop-blur-[2px]"
            @click.self="isEditing ? cancelEditing() : emit('close')"
        >
            <div class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">

                <!-- ── Header ──────────────────────────────────────────── -->
                <div class="flex items-start justify-between bg-pup-maroon-deep px-6 py-5">
                    <div>
                        <h2 class="text-[17px] font-semibold text-white">{{ headerTitle }}</h2>
                        <p class="mt-0.5 text-xs text-white/55" :class="{ 'font-mono': !isCreateMode }">
                            <template v-if="isCreateMode">Fill in the details to register a new room</template>
                            <template v-else>{{ room!.code }}</template>
                        </p>
                    </div>
                    <button
                        @click="isEditing ? cancelEditing() : emit('close')"
                        class="rounded-lg p-1.5 text-white/60 transition hover:bg-white/10 hover:text-white"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <!-- ── Body ────────────────────────────────────────────── -->
                <div class="px-6 py-5">

                    <!-- READ-ONLY VIEW (view mode only) -->
                    <template v-if="!showForm">
                        <p class="mb-3 text-[10px] font-semibold uppercase tracking-widest text-pup-maroon">
                            Room Information
                        </p>
                        <div class="mb-5 overflow-hidden rounded-xl border border-gray-100 bg-gray-50/60">
                            <div class="grid grid-cols-2 divide-x divide-gray-100">
                                <div class="flex items-start gap-2.5 p-4">
                                    <Building2 class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                                    <div>
                                        <p class="text-[10px] font-medium uppercase tracking-wide text-gray-400">Name</p>
                                        <p class="mt-0.5 text-sm font-semibold text-gray-800">{{ room!.name }}</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-2.5 p-4">
                                    <Hash class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                                    <div>
                                        <p class="text-[10px] font-medium uppercase tracking-wide text-gray-400">Code</p>
                                        <p class="mt-0.5 font-mono text-sm font-semibold text-gray-800">{{ room!.code }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-3 divide-x divide-gray-100 border-t border-gray-100">
                                <div class="flex items-start gap-2 p-4">
                                    <Layers class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                                    <div>
                                        <p class="text-[10px] font-medium uppercase tracking-wide text-gray-400">Floor</p>
                                        <p class="mt-0.5 text-sm font-semibold text-gray-800">{{ room!.floor ?? '—' }}</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-2 p-4">
                                    <Users class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                                    <div>
                                        <p class="text-[10px] font-medium uppercase tracking-wide text-gray-400">Capacity</p>
                                        <p class="mt-0.5 text-sm font-semibold text-gray-800">{{ room!.capacity ?? '—' }}</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-2 p-4">
                                    <ArrowUpDown class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                                    <div>
                                        <p class="text-[10px] font-medium uppercase tracking-wide text-gray-400">Order</p>
                                        <p class="mt-0.5 text-sm font-semibold text-gray-800">{{ room!.display_order }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p class="mb-3 text-[10px] font-semibold uppercase tracking-widest text-pup-maroon">
                            Classification &amp; Status
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <span :class="['inline-flex items-center rounded-full border px-3 py-1 text-xs font-medium', TYPE_BADGE[room!.room_type] ?? TYPE_BADGE.other]">
                                {{ room!.type_label }}
                            </span>
                            <span
                                v-if="room!.is_active"
                                class="inline-flex items-center rounded-full border border-green-200 bg-green-100 px-3 py-1 text-xs font-semibold text-green-700"
                            >
                                Active
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center rounded-full border border-gray-200 bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-500"
                            >
                                Inactive
                            </span>
                        </div>
                    </template>

                    <!-- FORM (create or edit mode) -->
                    <template v-else>
                        <p class="mb-4 text-[10px] font-semibold uppercase tracking-widest text-pup-maroon">
                            Room Information
                        </p>

                        <div class="space-y-3">
                            <!-- Code + Name -->
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="mb-1.5 block text-xs font-medium text-gray-600">
                                        Room Code <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        v-model="form.code"
                                        type="text"
                                        maxlength="20"
                                        placeholder="e.g. CPE-LAB-01"
                                        class="h-9 w-full rounded-lg border border-gray-200 bg-gray-50/50 px-3 font-mono text-sm text-gray-800 placeholder-gray-400 focus:border-pup-maroon/40 focus:bg-white focus:outline-none focus:ring-2 focus:ring-pup-maroon/10"
                                        :class="{ 'border-red-300': form.errors.code }"
                                    />
                                    <p v-if="form.errors.code" class="mt-1 text-xs text-red-500">{{ form.errors.code }}</p>
                                </div>

                                <div>
                                    <label class="mb-1.5 block text-xs font-medium text-gray-600">
                                        Room Name <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        v-model="form.name"
                                        type="text"
                                        maxlength="100"
                                        placeholder="e.g. Computer Laboratory 1"
                                        class="h-9 w-full rounded-lg border border-gray-200 bg-gray-50/50 px-3 text-sm text-gray-800 placeholder-gray-400 focus:border-pup-maroon/40 focus:bg-white focus:outline-none focus:ring-2 focus:ring-pup-maroon/10"
                                        :class="{ 'border-red-300': form.errors.name }"
                                    />
                                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                                </div>
                            </div>

                            <!-- Type -->
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-gray-600">
                                    Room Type <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <select
                                        v-model="form.room_type"
                                        class="h-9 w-full appearance-none rounded-lg border border-gray-200 bg-gray-50/50 pl-3 pr-8 text-sm text-gray-800 focus:border-pup-maroon/40 focus:bg-white focus:outline-none focus:ring-2 focus:ring-pup-maroon/10"
                                        :class="{ 'border-red-300': form.errors.room_type }"
                                    >
                                        <option v-for="t in ROOM_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
                                    </select>
                                    <ChevronDown class="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                                </div>
                                <p v-if="form.errors.room_type" class="mt-1 text-xs text-red-500">{{ form.errors.room_type }}</p>
                            </div>

                            <!-- Floor + Capacity -->
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="mb-1.5 block text-xs font-medium text-gray-600">
                                        Floor <span class="text-gray-400">(optional)</span>
                                    </label>
                                    <input
                                        v-model.number="form.floor"
                                        type="number"
                                        min="1" max="20"
                                        placeholder="e.g. 2"
                                        class="h-9 w-full rounded-lg border border-gray-200 bg-gray-50/50 px-3 text-sm text-gray-800 placeholder-gray-400 focus:border-pup-maroon/40 focus:bg-white focus:outline-none focus:ring-2 focus:ring-pup-maroon/10"
                                        :class="{ 'border-red-300': form.errors.floor }"
                                    />
                                    <p v-if="form.errors.floor" class="mt-1 text-xs text-red-500">{{ form.errors.floor }}</p>
                                </div>

                                <div>
                                    <label class="mb-1.5 block text-xs font-medium text-gray-600">
                                        Capacity <span class="text-gray-400">(optional)</span>
                                    </label>
                                    <input
                                        v-model.number="form.capacity"
                                        type="number"
                                        min="1" max="500"
                                        placeholder="e.g. 40"
                                        class="h-9 w-full rounded-lg border border-gray-200 bg-gray-50/50 px-3 text-sm text-gray-800 placeholder-gray-400 focus:border-pup-maroon/40 focus:bg-white focus:outline-none focus:ring-2 focus:ring-pup-maroon/10"
                                        :class="{ 'border-red-300': form.errors.capacity }"
                                    />
                                    <p v-if="form.errors.capacity" class="mt-1 text-xs text-red-500">{{ form.errors.capacity }}</p>
                                </div>
                            </div>

                            <!-- Display Order + Active toggle -->
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="mb-1.5 block text-xs font-medium text-gray-600">Display Order</label>
                                    <input
                                        v-model.number="form.display_order"
                                        type="number"
                                        min="0"
                                        placeholder="0"
                                        class="h-9 w-full rounded-lg border border-gray-200 bg-gray-50/50 px-3 text-sm text-gray-800 placeholder-gray-400 focus:border-pup-maroon/40 focus:bg-white focus:outline-none focus:ring-2 focus:ring-pup-maroon/10"
                                    />
                                </div>

                                <div class="flex flex-col justify-end">
                                    <label class="flex cursor-pointer items-center gap-2.5 rounded-lg border border-gray-200 bg-gray-50/50 px-3 py-2.5">
                                        <input
                                            v-model="form.is_active"
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-gray-300 text-pup-maroon focus:ring-pup-maroon/30"
                                        />
                                        <span class="text-sm font-medium text-gray-700">Active Room</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- ── Footer ──────────────────────────────────────────── -->
                <div class="flex items-center justify-end gap-2 border-t border-gray-100 px-6 py-4">
                    <!-- Create mode -->
                    <template v-if="isCreateMode">
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
                            class="rounded-lg bg-pup-maroon px-5 py-2 text-sm font-medium text-white transition hover:bg-pup-maroon-light disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{ form.processing ? 'Saving…' : 'Save Room' }}
                        </button>
                    </template>

                    <!-- View mode (read-only) -->
                    <template v-else-if="!isEditing">
                        <button
                            type="button"
                            @click="emit('close')"
                            class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50"
                        >
                            Close
                        </button>
                        <button
                            type="button"
                            @click="startEditing"
                            class="flex items-center gap-1.5 rounded-lg bg-pup-maroon px-5 py-2 text-sm font-medium text-white transition hover:bg-pup-maroon-light"
                        >
                            <Pencil class="h-3.5 w-3.5" />
                            Edit Room
                        </button>
                    </template>

                    <!-- Edit mode -->
                    <template v-else>
                        <button
                            type="button"
                            @click="cancelEditing"
                            class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50"
                        >
                            Cancel Edit
                        </button>
                        <button
                            type="button"
                            :disabled="form.processing"
                            @click="submit"
                            class="rounded-lg bg-pup-maroon px-5 py-2 text-sm font-medium text-white transition hover:bg-pup-maroon-light disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{ form.processing ? 'Saving…' : 'Save Changes' }}
                        </button>
                    </template>
                </div>

            </div>
        </div>
    </Teleport>
</template>

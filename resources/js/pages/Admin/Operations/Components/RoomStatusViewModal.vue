<script setup lang="ts">
import { ChevronDown, Pencil, X } from 'lucide-vue-next';
import { reactive, watch } from 'vue';

interface RoomOption {
    id: number;
    code: string;
    name: string;
    room_type?: string;
}

type OverrideStatus = 'maintenance' | 'unavailable' | 'reserved';
type OverrideState = 'active' | 'upcoming' | 'history';

interface RoomOverrideItem {
    id: number;
    room_id: number;
    room_code: string;
    room_name: string;
    status: OverrideStatus;
    reason: string | null;
    starts_at: string;
    ends_at: string | null;
    is_active: boolean;
    created_by_name: string;
    updated_by_name?: string | null;
}

interface RoomStatusForm {
    room_id: number;
    status: OverrideStatus;
    reason: string;
    starts_at: string;
    ends_at: string;
    indefinite: boolean;
}

const props = defineProps<{
    mode: 'create' | 'view';
    isEditing: boolean;
    selectedOverride: RoomOverrideItem | null;
    rooms: RoomOption[];
    form: RoomStatusForm;
    errors?: Partial<Record<keyof RoomStatusForm | string, string>>;
    processing?: boolean;
    overrideStatuses: OverrideStatus[];
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'cancel'): void;
    (e: 'enable-edit'): void;
    (e: 'save', payload: RoomStatusForm): void;
}>();

const localForm = reactive<RoomStatusForm>({ ...props.form });

watch(
    () => props.form,
    (value) => {
        Object.assign(localForm, value);
    },
    { deep: true, immediate: true },
);

watch(
    () => localForm.indefinite,
    (isIndefinite) => {
        if (isIndefinite) {
            localForm.ends_at = '';
        }
    },
);

function parseDate(value: string): Date {
    return new Date(value);
}

function overrideState(item: RoomOverrideItem): OverrideState {
    const current = new Date();
    const startsAt = parseDate(item.starts_at);
    const endsAt = item.ends_at ? parseDate(item.ends_at) : null;

    if (!item.is_active || (endsAt && endsAt <= current)) {
        return 'history';
    }

    if (startsAt > current) {
        return 'upcoming';
    }

    return 'active';
}

function statusLabel(status: OverrideStatus): string {
    return {
        maintenance: 'Maintenance',
        unavailable: 'Unavailable',
        reserved: 'Reserved',
    }[status];
}

function statusDescription(status: OverrideStatus): string {
    return {
        maintenance: 'Room is broken, being repaired, cleaned, inspected, or prepared.',
        unavailable: 'Room is administratively closed, locked, unsafe, or inaccessible.',
        reserved: 'Room is claimed for a non-class purpose such as a meeting or event.',
    }[status];
}

function statusBadgeClass(status: OverrideStatus): string {
    return {
        maintenance: 'border-gray-200 bg-status-maintenance-bg text-gray-600',
        unavailable: 'border-gray-300 bg-gray-200 text-gray-800',
        reserved: 'border-status-reserved-border bg-status-reserved-bg text-status-reserved',
    }[status];
}

function fieldError(field: keyof RoomStatusForm): string | undefined {
    return props.errors?.[field];
}
</script>

<template>
    <Teleport to="body">
        <div
            class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4 backdrop-blur-[2px]"
            @click.self="emit('close')"
        >
            <div class="w-full max-w-xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between bg-pup-maroon-deep px-6 py-5">
                    <div>
                        <h2 class="text-[17px] font-semibold text-white">
                            <template v-if="mode === 'create'">Create Room Override</template>
                            <template v-else-if="mode === 'view' && isEditing">Edit Room Override</template>
                            <template v-else>Room Override Details</template>
                        </h2>
                        <p class="mt-0.5 text-xs text-white/60">
                            Maintenance, unavailable, and reserved are the only manual room states.
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="emit('close')"
                        class="rounded-lg p-1.5 text-white/60 transition hover:bg-white/10 hover:text-white"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="max-h-[70vh] overflow-y-auto px-6 py-5">
                    <div class="space-y-4">
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-gray-600">
                                Room <span v-if="mode === 'create' || isEditing" class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <select
                                    v-model.number="localForm.room_id"
                                    :disabled="mode === 'view' && !isEditing"
                                    class="h-10 w-full appearance-none rounded-lg border border-gray-200 bg-white px-3 pr-9 text-sm text-gray-700 disabled:bg-gray-50 disabled:text-gray-500 focus:border-pup-maroon/40 focus:outline-none focus:ring-2 focus:ring-pup-maroon/10"
                                >
                                    <option v-for="room in rooms" :key="room.id" :value="room.id">
                                        {{ room.code }} · {{ room.name }}
                                    </option>
                                </select>
                                <ChevronDown class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                            </div>
                            <p v-if="fieldError('room_id')" class="mt-1 text-xs text-red-600">{{ fieldError('room_id') }}</p>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-gray-600">
                                Override Status <span v-if="mode === 'create' || isEditing" class="text-red-500">*</span>
                            </label>
                            <div class="grid gap-3 sm:grid-cols-3">
                                <button
                                    v-for="status in overrideStatuses"
                                    :key="status"
                                    type="button"
                                    :disabled="mode === 'view' && !isEditing"
                                    @click="localForm.status = status"
                                    :class="[
                                        'rounded-xl border p-3 text-left transition disabled:cursor-default',
                                        localForm.status === status
                                            ? 'border-pup-maroon bg-pup-maroon-pale ring-2 ring-pup-maroon/10'
                                            : 'border-gray-200 bg-white hover:bg-gray-50',
                                    ]"
                                >
                                    <span :class="['inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold', statusBadgeClass(status)]">
                                        {{ statusLabel(status) }}
                                    </span>
                                    <p class="mt-2 text-[11px] leading-relaxed text-gray-500">{{ statusDescription(status) }}</p>
                                </button>
                            </div>
                            <p v-if="fieldError('status')" class="mt-1 text-xs text-red-600">{{ fieldError('status') }}</p>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-gray-600">Reason</label>
                            <textarea
                                v-model="localForm.reason"
                                :disabled="mode === 'view' && !isEditing"
                                rows="4"
                                placeholder="e.g. Projector repair, room locked, faculty meeting"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50/50 px-3 py-2 text-sm text-gray-800 placeholder-gray-400 disabled:bg-gray-50 disabled:text-gray-500 focus:border-pup-maroon/40 focus:bg-white focus:outline-none focus:ring-2 focus:ring-pup-maroon/10"
                            />
                            <p v-if="fieldError('reason')" class="mt-1 text-xs text-red-600">{{ fieldError('reason') }}</p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-gray-600">
                                    Starts At <span v-if="mode === 'create' || isEditing" class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="localForm.starts_at"
                                    :disabled="mode === 'view' && !isEditing"
                                    type="datetime-local"
                                    class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm text-gray-700 disabled:bg-gray-50 disabled:text-gray-500 focus:border-pup-maroon/40 focus:outline-none focus:ring-2 focus:ring-pup-maroon/10"
                                />
                                <p v-if="fieldError('starts_at')" class="mt-1 text-xs text-red-600">{{ fieldError('starts_at') }}</p>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-gray-600">Ends At</label>
                                <input
                                    v-model="localForm.ends_at"
                                    :disabled="(mode === 'view' && !isEditing) || localForm.indefinite"
                                    type="datetime-local"
                                    class="h-10 w-full rounded-lg border border-gray-200 bg-white px-3 text-sm text-gray-700 disabled:bg-gray-50 disabled:text-gray-500 focus:border-pup-maroon/40 focus:outline-none focus:ring-2 focus:ring-pup-maroon/10"
                                />
                                <p v-if="fieldError('ends_at')" class="mt-1 text-xs text-red-600">{{ fieldError('ends_at') }}</p>
                            </div>
                        </div>

                        <label class="flex cursor-pointer items-center gap-2.5 rounded-lg border border-gray-200 bg-gray-50/60 px-3 py-2.5">
                            <input
                                v-model="localForm.indefinite"
                                :disabled="mode === 'view' && !isEditing"
                                type="checkbox"
                                class="h-4 w-4 rounded border-gray-300 text-pup-maroon focus:ring-pup-maroon/30"
                            />
                            <span class="text-sm font-medium text-gray-700">Indefinite override until manually cleared</span>
                        </label>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-gray-100 px-6 py-4">
                    <button
                        type="button"
                        :disabled="processing"
                        @click="emit('cancel')"
                        class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{ mode === 'view' && !isEditing ? 'Close' : mode === 'view' && isEditing ? 'Cancel Edit' : 'Cancel' }}
                    </button>

                    <button
                        v-if="mode === 'view' && !isEditing && selectedOverride && overrideState(selectedOverride) !== 'history'"
                        type="button"
                        @click="emit('enable-edit')"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-pup-maroon px-4 py-2 text-sm font-medium text-white transition hover:bg-pup-maroon-pale"
                    >
                        <Pencil class="h-4 w-4" />
                        Edit Details
                    </button>

                    <button
                        v-if="mode === 'create' || (mode === 'view' && isEditing)"
                        type="button"
                        :disabled="processing"
                        @click="emit('save', { ...localForm })"
                        class="rounded-lg bg-pup-maroon px-5 py-2 text-sm font-medium text-white transition hover:bg-pup-maroon-light disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <template v-if="processing">Saving...</template>
                        <template v-else>{{ mode === 'create' ? 'Create Override' : 'Save Changes' }}</template>
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

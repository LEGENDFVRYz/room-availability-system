<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { reactive, watch } from 'vue';
import type { ClassRequestPayload, Room } from './type';

const props = defineProps<{
    show: boolean;
    rooms: Room[];
    selectedDateLabel: string;
    errors?: Record<string, string>;
}>();

const emit = defineEmits<{
    close: [];
    save: [payload: ClassRequestPayload];
}>();

const labelClass = 'flex flex-col gap-1.5 text-sm font-semibold text-gray-600';
const inputClass = 'h-11 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-800 shadow-sm outline-none transition placeholder:text-gray-400 focus:border-pup-maroon focus:ring-2 focus:ring-pup-maroon/15';
const selectClass = 'h-11 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-800 shadow-sm outline-none transition focus:border-pup-maroon focus:ring-2 focus:ring-pup-maroon/15';
const textareaClass = 'min-h-[92px] resize-none rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm font-medium text-gray-800 shadow-sm outline-none transition placeholder:text-gray-400 focus:border-pup-maroon focus:ring-2 focus:ring-pup-maroon/15';

function fieldError(field: string): string {
    return props.errors?.[field] ?? '';
}

const classForm = reactive<ClassRequestPayload>({
    event_type: 'special_class',
    room_id: null,
    subject_code: '',
    subject_title: '',
    section: '',
    instructor_name: '',
    start_time: '08:00',
    end_time: '10:00',
    reason: '',
});

function resetClassForm() {
    classForm.event_type = 'special_class';
    classForm.room_id = props.rooms[0]?.id ?? null;
    classForm.subject_code = '';
    classForm.subject_title = '';
    classForm.section = '';
    classForm.instructor_name = '';
    classForm.start_time = '08:00';
    classForm.end_time = '10:00';
    classForm.reason = '';
}

function closeModal() {
    emit('close');
}

function saveClass() {
    if (!classForm.room_id || !classForm.subject_code || !classForm.subject_title || !classForm.section) return;

    emit('save', { ...classForm });
}

watch(
    () => props.show,
    (show) => {
        if (show) resetClassForm();
    },
);
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-[200] flex items-center justify-center bg-black/50 p-4">
            <div class="max-h-[calc(100vh-2rem)] w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl flex flex-col">
            <div class="flex items-start justify-between border-b border-gray-200 px-6 py-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-pup-maroon">Daily operation</p>
                    <h3 class="mt-1 text-xl font-bold text-gray-900">Request Class</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Create a one-date class entry for {{ selectedDateLabel }} and classify it as a special or makeup class.
                    </p>
                </div>
                <button type="button" @click="closeModal" class="rounded-full p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700">
                    <X class="h-5 w-5" />
                </button>
            </div>

            <div class="grid flex-1 gap-4 overflow-y-auto px-6 py-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <p class="text-sm font-semibold text-gray-700">Request type</p>
                    <div class="mt-2 grid gap-3 sm:grid-cols-2">
                        <button
                            type="button"
                            @click="classForm.event_type = 'special_class'"
                            :class="[
                                'rounded-xl border px-4 py-3 text-left transition',
                                classForm.event_type === 'special_class'
                                    ? 'border-pup-maroon bg-pup-maroon-pale text-pup-maroon shadow-sm'
                                    : 'border-gray-200 bg-white text-gray-600 hover:border-pup-maroon/30 hover:bg-pup-maroon-pale/40',
                            ]"
                        >
                            <span class="block text-sm font-bold">Special Class</span>
                            <span class="mt-1 block text-xs leading-relaxed opacity-80">
                                One-time class added for the selected date with no regular weekly schedule.
                            </span>
                        </button>

                        <button
                            type="button"
                            @click="classForm.event_type = 'makeup_class'"
                            :class="[
                                'rounded-xl border px-4 py-3 text-left transition',
                                classForm.event_type === 'makeup_class'
                                    ? 'border-pup-maroon bg-pup-maroon-pale text-pup-maroon shadow-sm'
                                    : 'border-gray-200 bg-white text-gray-600 hover:border-pup-maroon/30 hover:bg-pup-maroon-pale/40',
                            ]"
                        >
                            <span class="block text-sm font-bold">Makeup Class</span>
                            <span class="mt-1 block text-xs leading-relaxed opacity-80">
                                Replacement class for a missed or adjusted session.
                            </span>
                        </button>
                    </div>
                </div>

                <div
                    v-if="fieldError('room_id') || fieldError('start_time') || fieldError('end_time') || fieldError('event_date')"
                    class="sm:col-span-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                >
                    <p class="font-semibold">Class request cannot be saved.</p>
                    <p class="mt-1">{{ fieldError('room_id') || fieldError('start_time') || fieldError('end_time') || fieldError('event_date') }}</p>
                </div>

                <label :class="labelClass">
                    Room
                    <select v-model="classForm.room_id" :class="selectClass">
                        <option v-for="room in rooms" :key="room.id" :value="room.id">
                            {{ room.code }} · {{ room.name }}
                        </option>
                    </select>
                    <span v-if="fieldError('room_id')" class="text-xs font-medium text-red-600">{{ fieldError('room_id') }}</span>
                </label>

                <label :class="labelClass">
                    Section
                    <input v-model="classForm.section" type="text" :class="inputClass" placeholder="BSCPE 4-2" />
                    <span v-if="fieldError('section')" class="text-xs font-medium text-red-600">{{ fieldError('section') }}</span>
                </label>

                <label :class="labelClass">
                    Subject code
                    <input v-model="classForm.subject_code" type="text" :class="inputClass" placeholder="CMPE 499" />
                    <span v-if="fieldError('subject_code')" class="text-xs font-medium text-red-600">{{ fieldError('subject_code') }}</span>
                </label>

                <label :class="labelClass">
                    Subject title
                    <input v-model="classForm.subject_title" type="text" :class="inputClass" placeholder="Capstone Consultation" />
                    <span v-if="fieldError('subject_title')" class="text-xs font-medium text-red-600">{{ fieldError('subject_title') }}</span>
                </label>

                <label :class="labelClass">
                    Start time
                    <input v-model="classForm.start_time" type="time" :class="inputClass" />
                    <span v-if="fieldError('start_time')" class="text-xs font-medium text-red-600">{{ fieldError('start_time') }}</span>
                </label>

                <label :class="labelClass">
                    End time
                    <input v-model="classForm.end_time" type="time" :class="inputClass" />
                    <span v-if="fieldError('end_time')" class="text-xs font-medium text-red-600">{{ fieldError('end_time') }}</span>
                </label>

                <label :class="[labelClass, 'sm:col-span-2']">
                    Instructor
                    <input v-model="classForm.instructor_name" type="text" :class="inputClass" placeholder="Instructor name" />
                </label>

                <label :class="[labelClass, 'sm:col-span-2']">
                    Reason / note
                    <textarea
                        v-model="classForm.reason"
                        rows="3"
                        :class="textareaClass"
                        :placeholder="classForm.event_type === 'special_class' ? 'Optional reason for this special class' : 'Optional reason for this makeup class'"
                    />
                </label>
            </div>

            <div class="flex shrink-0 justify-end gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4">
                <button type="button" @click="closeModal" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-white">
                    Cancel
                </button>
                <button type="button" @click="saveClass" class="rounded-lg bg-pup-maroon px-4 py-2 text-sm font-semibold text-white hover:bg-pup-maroon-deep">
                    {{ classForm.event_type === 'special_class' ? 'Save Special Class' : 'Save Makeup Class' }}
                </button>
            </div>
        </div>
        </div>
    </Teleport>
</template>

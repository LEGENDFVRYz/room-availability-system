<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { reactive, watch } from 'vue';
interface Room {
    id: number;
    code: string;
    name: string;
    type?: string;
}

interface ClassRequestPayload {
    event_type: 'special_class' | 'makeup_class';
    room_id: number | null;
    subject_code: string;
    subject_title: string;
    section: string;
    instructor_name: string;
    start_time: string;
    end_time: string;
    reason: string;
}

const props = defineProps<{
    show: boolean;
    rooms: Room[];
    selectedDateLabel: string;
}>();

const emit = defineEmits<{
    close: [];
    save: [payload: ClassRequestPayload];
}>();

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
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/45 p-4">
        <div class="w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl">
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

            <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
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

                <label class="flex flex-col gap-1 text-sm font-medium text-gray-600">
                    Room
                    <select v-model="classForm.room_id" class="rounded-lg border-gray-200 text-sm focus:border-pup-maroon focus:ring-pup-maroon">
                        <option v-for="room in rooms" :key="room.id" :value="room.id">
                            {{ room.code }} · {{ room.name }}
                        </option>
                    </select>
                </label>

                <label class="flex flex-col gap-1 text-sm font-medium text-gray-600">
                    Section
                    <input v-model="classForm.section" type="text" class="rounded-lg border-gray-200 text-sm focus:border-pup-maroon focus:ring-pup-maroon" placeholder="BSCPE 4-2" />
                </label>

                <label class="flex flex-col gap-1 text-sm font-medium text-gray-600">
                    Subject code
                    <input v-model="classForm.subject_code" type="text" class="rounded-lg border-gray-200 text-sm focus:border-pup-maroon focus:ring-pup-maroon" placeholder="CMPE 499" />
                </label>

                <label class="flex flex-col gap-1 text-sm font-medium text-gray-600">
                    Subject title
                    <input v-model="classForm.subject_title" type="text" class="rounded-lg border-gray-200 text-sm focus:border-pup-maroon focus:ring-pup-maroon" placeholder="Capstone Consultation" />
                </label>

                <label class="flex flex-col gap-1 text-sm font-medium text-gray-600">
                    Start time
                    <input v-model="classForm.start_time" type="time" class="rounded-lg border-gray-200 text-sm focus:border-pup-maroon focus:ring-pup-maroon" />
                </label>

                <label class="flex flex-col gap-1 text-sm font-medium text-gray-600">
                    End time
                    <input v-model="classForm.end_time" type="time" class="rounded-lg border-gray-200 text-sm focus:border-pup-maroon focus:ring-pup-maroon" />
                </label>

                <label class="flex flex-col gap-1 text-sm font-medium text-gray-600 sm:col-span-2">
                    Instructor
                    <input v-model="classForm.instructor_name" type="text" class="rounded-lg border-gray-200 text-sm focus:border-pup-maroon focus:ring-pup-maroon" placeholder="Instructor name" />
                </label>

                <label class="flex flex-col gap-1 text-sm font-medium text-gray-600 sm:col-span-2">
                    Reason / note
                    <textarea
                        v-model="classForm.reason"
                        rows="3"
                        class="rounded-lg border-gray-200 text-sm focus:border-pup-maroon focus:ring-pup-maroon"
                        :placeholder="classForm.event_type === 'special_class' ? 'Optional reason for this special class' : 'Optional reason for this makeup class'"
                    />
                </label>
            </div>

            <div class="flex justify-end gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4">
                <button type="button" @click="closeModal" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-white">
                    Cancel
                </button>
                <button type="button" @click="saveClass" class="rounded-lg bg-pup-maroon px-4 py-2 text-sm font-semibold text-white hover:bg-pup-maroon-deep">
                    {{ classForm.event_type === 'special_class' ? 'Save Special Class' : 'Save Makeup Class' }}
                </button>
            </div>
        </div>
    </div>
</template>

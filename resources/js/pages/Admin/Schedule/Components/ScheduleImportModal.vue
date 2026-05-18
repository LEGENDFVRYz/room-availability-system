<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { AlertCircle, CheckCircle2, ChevronDown, Download, FileSpreadsheet, Upload, X } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import type { PageProps } from '@inertiajs/core'

interface CurrentTerm {
    year_start: number;
    semester: number;
}

interface RoomOption {
    code: string;
}

interface RejectedScheduleRow {
    row_number: number | string;
    day?: string;
    room_code?: string;
    start_time?: string;
    end_time?: string;
    subject_code?: string;
    subject_title?: string;
    section?: string;
    instructor_name?: string;
    reason: string;
    conflicts_with?: string;
}

interface ImportServerResult {
    imported_count: number;
    skipped_count: number;
    total_rows: number;
    replace_existing: boolean;
    academic_term: string;
    rejected_rows: RejectedScheduleRow[];
}

interface ImportPageProps extends PageProps {
    flash?: {
        schedule_import?: ImportServerResult;
        success?: string;
        error?: string;
    };
}

const props = defineProps<{
    currentTerm?: CurrentTerm | null;
    rooms?: RoomOption[];
}>();

const emit = defineEmits<{
    close: [];
    imported: [result: ImportServerResult | null];
}>();

const EXPECTED_HEADERS = [
    'day',
    'room_code',
    'start_time',
    'end_time',
    'subject_code',
    'subject_title',
    'section',
    'instructor_name',
];

const DAY_NAMES = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

const currentYear = new Date().getFullYear();
const yearOptions = [currentYear - 2, currentYear - 1, currentYear, currentYear + 1, currentYear + 2]
    .filter((value, index, values) => values.indexOf(value) === index)
    .map((year) => ({ value: year, label: `SY ${year}–${year + 1}` }));

const semesterOptions = [
    { value: 1, label: '1st Semester' },
    { value: 2, label: '2nd Semester' },
    { value: 3, label: 'Summer' },
];

const hasSubmitted = ref(false);
const page = usePage<ImportPageProps>();
const lastServerResult = ref<ImportServerResult | null>(null);
const serverResult = computed(() => lastServerResult.value);

const selectedFileName = ref('');
const isValidating = ref(false);
const headerErrors = ref<string[]>([]);
const rowErrors = ref<string[]>([]);
const warningRows = ref<RejectedScheduleRow[]>([]);
const parsedRowCount = ref(0);

const validRowCount = ref(0);

const validRoomCodes = computed(() => {
    return new Set(
        (props.rooms ?? [])
            .map((room) => room.code.trim().toUpperCase())
            .filter(Boolean),
    );
});

const fileInput = ref<HTMLInputElement | null>(null);

const form = useForm({
    year_start: props.currentTerm?.year_start ?? currentYear,
    semester: props.currentTerm?.semester ?? 1,
    replace_existing: false,
    csv_file: null as File | null,
});

watch(
    () => props.currentTerm,
    (term) => {
        if (!term) return;

        form.year_start = term.year_start;
        form.semester = term.semester;
    },
);

const canSubmit = computed(() => {
    return !!form.csv_file
        && headerErrors.value.length === 0
        && rowErrors.value.length === 0
        && validRowCount.value > 0
        && !isValidating.value
        && !form.processing;
});

const feedbackRows = computed<RejectedScheduleRow[]>(() => {
    return serverResult.value?.rejected_rows?.length ? serverResult.value.rejected_rows : warningRows.value;
});

const feedbackTitle = computed(() => {
    if (serverResult.value?.rejected_rows?.length) return 'Rows not imported';
    if (warningRows.value.length) return 'Rows that will not be imported';
    return '';
});

const inputClass = (hasError: boolean) =>
    'h-9 w-full rounded-lg border bg-gray-50/50 px-3 text-sm text-gray-800 placeholder-gray-400 ' +
    'focus:bg-white focus:outline-none focus:ring-2 focus:ring-pup-maroon/10 ' +
    (hasError ? 'border-red-300 focus:border-red-400' : 'border-gray-200 focus:border-pup-maroon/40');

function handleKey(e: KeyboardEvent) {
    if (e.key === 'Escape') emit('close');
}

onMounted(() => document.addEventListener('keydown', handleKey));
onUnmounted(() => document.removeEventListener('keydown', handleKey));

function triggerFilePicker() {
    fileInput.value?.click();
}

async function onFileChange(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    resetValidationState();
    form.csv_file = file;
    selectedFileName.value = file?.name ?? '';

    if (!file) return;

    if (!file.name.toLowerCase().endsWith('.csv')) {
        headerErrors.value = ['The selected file must be a CSV file.'];
        return;
    }

    isValidating.value = true;

    try {
        await validateCsvFile(file);
    } finally {
        isValidating.value = false;
    }
}

function resetValidationState() {
    form.clearErrors();
    headerErrors.value = [];
    rowErrors.value = [];
    warningRows.value = [];
    parsedRowCount.value = 0;
    validRowCount.value = 0;
    hasSubmitted.value = false;
    lastServerResult.value = null;
}

function addClientRejectedRow(
    row: Partial<RejectedScheduleRow>,
    reason: string,
    conflictsWith = '',
) {
    warningRows.value.push({
        row_number: row.row_number ?? '',
        day: row.day ?? '',
        room_code: row.room_code ?? '',
        start_time: row.start_time ?? '',
        end_time: row.end_time ?? '',
        subject_code: row.subject_code ?? '',
        subject_title: row.subject_title ?? '',
        section: row.section ?? '',
        instructor_name: row.instructor_name ?? '',
        reason,
        conflicts_with: conflictsWith,
    });
}

async function validateCsvFile(file: File) {
    const text = await file.text();
    const rows = parseCsv(text).filter((row) => row.some((cell) => cell.trim() !== ''));

    if (rows.length === 0) {
        headerErrors.value = ['The CSV file is empty.'];
        return;
    }

    const headers = rows[0].map((header) => normalizeHeader(header));

    if (headers.length !== EXPECTED_HEADERS.length || !EXPECTED_HEADERS.every((header, index) => headers[index] === header)) {
        headerErrors.value = [
            `Invalid CSV headers. Use exactly: ${EXPECTED_HEADERS.join(', ')}`,
            'The column order must also match the required layout.',
        ];
        return;
    }

    const normalizedRows: Array<RejectedScheduleRow & { start_minutes: number; end_minutes: number; key: string }> = [];

    parsedRowCount.value = rows.length - 1;

    rows.slice(1).forEach((row, index) => {
        const lineNumber = index + 2;

        const record = Object.fromEntries(
            EXPECTED_HEADERS.map((header, cellIndex) => [header, row[cellIndex]?.trim() ?? '']),
        ) as Record<string, string>;

        if (row.length !== EXPECTED_HEADERS.length) {
            addClientRejectedRow(
                { row_number: lineNumber, ...record },
                `Invalid column count. Expected ${EXPECTED_HEADERS.length} columns but found ${row.length}.`,
            );
            return;
        }

        const missingColumns = EXPECTED_HEADERS.filter((header) => header !== 'instructor_name' && !record[header]);

        if (missingColumns.length) {
            addClientRejectedRow(
                { row_number: lineNumber, ...record },
                `Missing required value: ${missingColumns.join(', ')}.`,
            );
            return;
        }

        if (!DAY_NAMES.includes(record.day.toLowerCase())) {
            addClientRejectedRow(
                { row_number: lineNumber, ...record },
                'Invalid day value. Use Monday, Tuesday, Wednesday, Thursday, Friday, Saturday, or Sunday.',
            );
            return;
        }

        const roomCode = record.room_code.trim().toUpperCase();

        if (!validRoomCodes.value.has(roomCode)) {
            addClientRejectedRow(
                { row_number: lineNumber, ...record },
                'Room code was not found. Add the room first or correct the room_code value.',
            );
            return;
        }

        const startMinutes = parseTimeToMinutes(record.start_time);
        const endMinutes = parseTimeToMinutes(record.end_time);

        if (startMinutes === null) {
            addClientRejectedRow(
                { row_number: lineNumber, ...record },
                'Invalid start_time value.',
            );
            return;
        }

        if (endMinutes === null) {
            addClientRejectedRow(
                { row_number: lineNumber, ...record },
                'Invalid end_time value.',
            );
            return;
        }

        if (startMinutes >= endMinutes) {
            addClientRejectedRow(
                { row_number: lineNumber, ...record },
                'end_time must be after start_time.',
            );
            return;
        }

        normalizedRows.push({
            row_number: lineNumber,
            day: record.day,
            room_code: roomCode,
            start_time: record.start_time,
            end_time: record.end_time,
            subject_code: record.subject_code,
            subject_title: record.subject_title,
            section: record.section,
            instructor_name: record.instructor_name,
            reason: '',
            conflicts_with: '',
            start_minutes: startMinutes,
            end_minutes: endMinutes,
            key: `${record.day.trim().toLowerCase()}|${roomCode}`,
        });
    });

    const conflictedRowNumbers = detectInternalConflicts(normalizedRows);

    validRowCount.value = normalizedRows.filter((row) => !conflictedRowNumbers.has(row.row_number)).length;

    if (validRowCount.value === 0) {
        rowErrors.value = ['No importable schedule rows were found. Fix at least one row before importing.'];
    }
}

function detectInternalConflicts(rows: Array<RejectedScheduleRow & { start_minutes: number; end_minutes: number; key: string }>) {
    const conflictMap = new Map<number | string, Set<number | string>>();

    for (let i = 0; i < rows.length; i += 1) {
        for (let j = i + 1; j < rows.length; j += 1) {
            const first = rows[i];
            const second = rows[j];

            if (first.key !== second.key) continue;
            if (!timesOverlap(first.start_minutes, first.end_minutes, second.start_minutes, second.end_minutes)) continue;

            if (!conflictMap.has(first.row_number)) conflictMap.set(first.row_number, new Set());
            if (!conflictMap.has(second.row_number)) conflictMap.set(second.row_number, new Set());

            conflictMap.get(first.row_number)!.add(second.row_number);
            conflictMap.get(second.row_number)!.add(first.row_number);
        }
    }

    const conflictRows = rows
        .filter((row) => conflictMap.has(row.row_number))
        .map((row) => ({
            row_number: row.row_number,
            day: row.day,
            room_code: row.room_code,
            start_time: row.start_time,
            end_time: row.end_time,
            subject_code: row.subject_code,
            subject_title: row.subject_title,
            section: row.section,
            instructor_name: row.instructor_name,
            reason: 'Overlaps another row in this CSV upload.',
            conflicts_with: Array.from(conflictMap.get(row.row_number) ?? []).join(', '),
        }));

    warningRows.value = [...warningRows.value, ...conflictRows];

    return new Set(conflictRows.map((row) => row.row_number));
}

function parseCsv(text: string): string[][] {
    const rows: string[][] = [];
    let row: string[] = [];
    let cell = '';
    let inQuotes = false;

    for (let i = 0; i < text.length; i += 1) {
        const char = text[i];
        const next = text[i + 1];

        if (char === '"') {
            if (inQuotes && next === '"') {
                cell += '"';
                i += 1;
            } else {
                inQuotes = !inQuotes;
            }
            continue;
        }

        if (char === ',' && !inQuotes) {
            row.push(cell);
            cell = '';
            continue;
        }

        if ((char === '\n' || char === '\r') && !inQuotes) {
            if (char === '\r' && next === '\n') i += 1;
            row.push(cell);
            rows.push(row);
            row = [];
            cell = '';
            continue;
        }

        cell += char;
    }

    if (inQuotes) {
        headerErrors.value = ['The CSV has an unfinished quoted value.'];
        return [];
    }

    if (cell !== '' || row.length > 0) {
        row.push(cell);
        rows.push(row);
    }

    return rows;
}

function normalizeHeader(header: string) {
    return header.replace(/^\uFEFF/, '').trim().toLowerCase();
}

function parseTimeToMinutes(value: string): number | null {
    const time = value.trim().toUpperCase();
    let hour = 0;
    let minute = 0;

    let match = time.match(/^(\d{1,2}):(\d{2})(?::\d{2})?$/);
    if (match) {
        hour = Number(match[1]);
        minute = Number(match[2]);

        if (hour > 23 || minute > 59) return null;
        return hour * 60 + minute;
    }

    match = time.match(/^(\d{1,2}):(\d{2})(?::\d{2})?\s*(AM|PM)$/);
    if (!match) return null;

    hour = Number(match[1]);
    minute = Number(match[2]);

    if (hour < 1 || hour > 12 || minute > 59) return null;
    if (match[3] === 'PM' && hour !== 12) hour += 12;
    if (match[3] === 'AM' && hour === 12) hour = 0;

    return hour * 60 + minute;
}

function timesOverlap(firstStart: number, firstEnd: number, secondStart: number, secondEnd: number) {
    return firstStart < secondEnd && secondStart < firstEnd;
}

function submit() {
    if (!canSubmit.value) return;

    hasSubmitted.value = true;
    lastServerResult.value = null;

    form.post('/admin/schedules/import', {
        preserveScroll: true,
        preserveState: true,
        forceFormData: true,
        onSuccess: (page) => {
            const flash = page.props.flash as ImportPageProps['flash'] | undefined;
            lastServerResult.value = flash?.schedule_import ?? null;
            emit('imported', lastServerResult.value);
        },
    });
}

function downloadFeedbackCsv() {
    const rows = feedbackRows.value;
    if (!rows.length) return;

    const headers = [...EXPECTED_HEADERS, 'row_number', 'reason', 'conflicts_with'];
    const csvRows = [
        headers,
        ...rows.map((row) => headers.map((header) => String(row[header as keyof RejectedScheduleRow] ?? ''))),
    ];

    const csv = csvRows.map((row) => row.map(csvEscape).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = 'schedule_import_not_added_rows.csv';
    link.click();
    URL.revokeObjectURL(url);
}

function csvEscape(value: string) {
    if (/[",\n\r]/.test(value)) {
        return `"${value.replace(/"/g, '""')}"`;
    }

    return value;
}
</script>

<template>
    <Teleport to="body">
        <div
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4 backdrop-blur-[2px]"
            @click.self="emit('close')"
        >
            <div class="flex max-h-[92vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                <!-- Header -->
                <div class="flex items-start justify-between bg-pup-maroon-deep px-6 py-5">
                    <div>
                        <h2 class="text-[17px] font-semibold text-white">Import Schedule</h2>
                        <p class="mt-0.5 text-xs text-white/55">
                            Upload a CSV schedule for a selected academic term.
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

                <!-- Body -->
                <div class="overflow-y-auto px-6 py-5">
                    <div class="mb-4 rounded-xl border border-pup-gold/30 bg-pup-gold-pale/60 px-4 py-3 text-xs leading-relaxed text-pup-maroon-dark">
                        <div class="flex gap-2">
                            <AlertCircle class="mt-0.5 h-4 w-4 shrink-0 text-pup-gold-dark" />
                            <div>
                                <p class="font-semibold">CSV format requirement</p>
                                <p>
                                    Upload schedules using the official import template. The first row must contain these headers in this exact order:
                                    <span class="font-mono font-semibold">
                                        {{ EXPECTED_HEADERS.join(', ') }}
                                    </span>.
                                </p>
                                <!-- <p class="mt-1 break-words font-mono text-[11px] font-semibold">
                                    {{ EXPECTED_HEADERS.join(', ') }}
                                </p> -->
                            </div>
                        </div>
                    </div>

                    <p class="mb-4 text-[10px] font-semibold uppercase tracking-widest text-pup-maroon">
                        Import Settings
                    </p>

                    <div class="space-y-4">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-gray-600">
                                    Academic Term <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <select
                                        v-model.number="form.year_start"
                                        :class="inputClass(!!form.errors.year_start) + ' appearance-none pl-3 pr-8'"
                                    >
                                        <option v-for="year in yearOptions" :key="year.value" :value="year.value">
                                            {{ year.label }}
                                        </option>
                                    </select>
                                    <ChevronDown class="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                                </div>
                                <p v-if="form.errors.year_start" class="mt-1 text-xs text-red-500">{{ form.errors.year_start }}</p>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-gray-600">
                                    Semester <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <select
                                        v-model.number="form.semester"
                                        :class="inputClass(!!form.errors.semester) + ' appearance-none pl-3 pr-8'"
                                    >
                                        <option v-for="semester in semesterOptions" :key="semester.value" :value="semester.value">
                                            {{ semester.label }}
                                        </option>
                                    </select>
                                    <ChevronDown class="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                                </div>
                                <p v-if="form.errors.semester" class="mt-1 text-xs text-red-500">{{ form.errors.semester }}</p>
                            </div>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-gray-50/60 p-4">
                            <label class="flex items-start gap-3">
                                <input
                                    v-model="form.replace_existing"
                                    type="checkbox"
                                    class="mt-1 rounded border-gray-300 text-pup-maroon focus:ring-pup-maroon/20"
                                />
                                <span>
                                    <span class="block text-sm font-semibold text-gray-700">Replace existing schedules for this term</span>
                                    <span class="mt-0.5 block text-xs leading-relaxed text-gray-500">
                                        When enabled, current schedules for the selected academic term and semester are deactivated before valid CSV rows are imported. When disabled, new rows are added only if they do not overlap existing schedules.
                                    </span>
                                </span>
                            </label>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-gray-600">
                                CSV File <span class="text-red-500">*</span>
                            </label>
                            <input
                                ref="fileInput"
                                type="file"
                                accept=".csv,text/csv"
                                class="hidden"
                                @change="onFileChange"
                            />
                            <button
                                type="button"
                                @click="triggerFilePicker"
                                class="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-sm font-medium text-gray-600 transition hover:border-pup-maroon/40 hover:bg-pup-maroon-pale/40 hover:text-pup-maroon"
                            >
                                <FileSpreadsheet class="h-5 w-5" />
                                <span>{{ selectedFileName || 'Choose CSV file' }}</span>
                            </button>
                            <p v-if="form.errors.csv_file" class="mt-1 text-xs text-red-500">{{ form.errors.csv_file }}</p>
                        </div>

                        <div v-if="isValidating" class="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-500">
                            Checking CSV layout and row values…
                        </div>

                        <div v-if="headerErrors.length || rowErrors.length" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3">
                            <p class="mb-2 flex items-center gap-1.5 text-sm font-semibold text-red-700">
                                <AlertCircle class="h-4 w-4" />
                                The CSV cannot be imported yet
                            </p>
                            <ul class="max-h-32 list-disc overflow-y-auto pl-5 text-xs leading-relaxed text-red-600">
                                <li v-for="error in [...headerErrors, ...rowErrors]" :key="error">{{ error }}</li>
                            </ul>
                        </div>

                        <div v-if="validRowCount && !headerErrors.length && !rowErrors.length" class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                            <p class="flex items-center gap-1.5 font-semibold">
                                <CheckCircle2 class="h-4 w-4" />
                                CSV layout is valid
                            </p>
                            <p class="mt-0.5 text-xs text-green-600">
                                {{ validRowCount }} importable schedule row{{ validRowCount === 1 ? '' : 's' }} found.
                                <template v-if="warningRows.length">
                                    {{ warningRows.length }} row{{ warningRows.length === 1 ? '' : 's' }} will not be imported and can be reviewed below.
                                </template>
                            </p>
                        </div>

                        <div v-if="serverResult" class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm">
                            <p class="text-sm font-semibold text-gray-800">Import finished</p>
                            <div class="mt-2 grid grid-cols-3 gap-2 text-center text-xs">
                                <div class="rounded-lg bg-green-50 px-2 py-2 text-green-700">
                                    <p class="text-lg font-bold">{{ serverResult.imported_count }}</p>
                                    <p>Imported</p>
                                </div>
                                <div class="rounded-lg bg-amber-50 px-2 py-2 text-amber-700">
                                    <p class="text-lg font-bold">{{ serverResult.skipped_count }}</p>
                                    <p>Not added</p>
                                </div>
                                <div class="rounded-lg bg-gray-50 px-2 py-2 text-gray-600">
                                    <p class="text-lg font-bold">{{ serverResult.total_rows }}</p>
                                    <p>Total rows</p>
                                </div>
                            </div>
                        </div>

                        <div v-if="feedbackRows.length" class="rounded-xl border border-amber-200 bg-amber-50/70 p-4">
                            <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-amber-800">{{ feedbackTitle }}</p>
                                    <p class="text-xs text-amber-700">
                                        Download the feedback CSV so the admin can manually review or re-add these rows.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    @click="downloadFeedbackCsv"
                                    class="inline-flex shrink-0 items-center gap-2 whitespace-nowrap rounded-lg border border-amber-400 px-3 py-2 text-sm font-semibold text-amber-700 transition hover:bg-amber-50"
                                >
                                    <Download class="h-3.5 w-3.5" />
                                    Download CSV
                                </button>
                            </div>

                            <div class="max-h-44 overflow-auto rounded-lg border border-amber-100 bg-white">
                                <table class="min-w-full divide-y divide-amber-100 text-left text-xs">
                                    <thead class="bg-amber-50 text-[10px] uppercase tracking-wide text-amber-700">
                                        <tr>
                                            <th class="px-3 py-2">Line</th>
                                            <th class="px-3 py-2">Room / Day</th>
                                            <th class="px-3 py-2">Time</th>
                                            <th class="px-3 py-2">Class</th>
                                            <th class="px-3 py-2">Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 text-gray-600">
                                        <tr v-for="row in feedbackRows" :key="`${row.row_number}-${row.reason}`">
                                            <td class="whitespace-nowrap px-3 py-2 font-mono">{{ row.row_number }}</td>
                                            <td class="whitespace-nowrap px-3 py-2">{{ row.room_code }} · {{ row.day }}</td>
                                            <td class="whitespace-nowrap px-3 py-2">{{ row.start_time }}–{{ row.end_time }}</td>
                                            <td class="px-3 py-2">{{ row.subject_code }} {{ row.section }}</td>
                                            <td class="px-3 py-2">
                                                {{ row.reason }}
                                                <span v-if="row.conflicts_with" class="block text-[10px] text-gray-400">
                                                    Conflicts with: {{ row.conflicts_with }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
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
                        Close
                    </button>
                    <button
                        type="button"
                        :disabled="!canSubmit"
                        @click="submit"
                        class="flex items-center gap-1.5 rounded-lg bg-pup-maroon px-5 py-2 text-sm font-medium text-white transition hover:bg-pup-maroon-deep disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <Upload class="h-3.5 w-3.5" />
                        {{ form.processing ? 'Importing…' : 'Import CSV' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

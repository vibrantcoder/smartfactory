@extends('admin.layouts.app')

@section('title', 'Shifts')

@section('content')
<div
    x-data="shiftManager(
        {{ $shifts->toJson() }},
        {{ $factoryId ?? 'null' }},
        {{ $factories->toJson() }}
    )"
    x-init="init()"
    class="flex flex-col gap-6"
>

    {{-- ── Header ──────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-base font-semibold text-gray-900">Work Shifts</h2>
            <p class="text-sm text-gray-500 mt-0.5">
                OEE Planned Time = Shift Duration − Break. Availability = (Planned − Alarm) ÷ Planned.
            </p>
        </div>
        <div class="flex items-center gap-3">
            {{-- Factory selector (super-admin only) --}}
            @if($factories->count())
            <select
                @change="switchFactory($event.target.value)"
                class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
                @foreach($factories as $f)
                <option value="{{ $f->id }}" {{ $factoryId == $f->id ? 'selected' : '' }}>{{ $f->name }}</option>
                @endforeach
            </select>
            @endif

            <button
                @click="openCreate()"
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                New Shift
            </button>
        </div>
    </div>

    {{-- Error banner --}}
    <div x-show="error" x-cloak class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" x-text="error"></div>

    {{-- ── Shift Duration Visual ──────────────────────────────── --}}
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200 overflow-hidden">

        {{-- Info strip --}}
        <div class="border-b border-indigo-100 bg-indigo-50 px-5 py-2.5 flex items-center gap-2 text-xs text-indigo-700">
            <svg class="h-4 w-4 text-indigo-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>
                <span class="font-semibold">OEE Planned Time = Duration − Break</span>.
                Availability = (Planned − Alarm) ÷ Planned × 100.
                Night shift duration = (24:00 − start) + end.
            </span>
        </div>

        {{-- 24-hour timeline preview --}}
        <div class="px-5 pt-4 pb-3 border-b border-gray-100">
            <p class="text-xs font-medium uppercase tracking-widest text-gray-400 mb-2">24-Hour Timeline</p>
            <div class="relative h-10 rounded-lg overflow-hidden bg-gray-100">
                {{-- hour ticks --}}
                <template x-for="h in [0,2,4,6,8,10,12,14,16,18,20,22,24]" :key="h">
                    <div class="absolute top-0 h-full border-l border-gray-300/60 text-[9px] text-gray-400 pl-0.5"
                         :style="`left: ${h/24*100}%`"
                         :class="h > 0 && h < 24 ? '' : ''"
                    >
                        <span class="absolute -bottom-0 leading-none" x-text="h === 24 ? '' : String(h).padStart(2,'0')"></span>
                    </div>
                </template>
                {{-- shift bars --}}
                <template x-for="s in shifts" :key="s.id">
                    <template x-if="s.is_active">
                        <div>
                            <template x-if="!s.crosses_midnight">
                                <div
                                    class="absolute top-1.5 h-7 rounded opacity-80 flex items-center justify-center text-[10px] font-bold text-white overflow-hidden"
                                    :style="shiftBarStyle(s)"
                                    x-text="s.name"
                                ></div>
                            </template>
                            <template x-if="s.crosses_midnight">
                                {{-- Night shift: two segments (start→midnight, midnight→end) --}}
                                <div>
                                    <div
                                        class="absolute top-1.5 h-7 rounded-l opacity-80 flex items-center justify-center text-[10px] font-bold text-white"
                                        :style="shiftBarStyleNightLeft(s)"
                                        x-text="s.name"
                                    ></div>
                                    <div
                                        class="absolute top-1.5 h-7 rounded-r opacity-80"
                                        :style="shiftBarStyleNightRight(s)"
                                    ></div>
                                </div>
                            </template>
                        </div>
                    </template>
                </template>
            </div>
            <div class="flex justify-between text-[10px] text-gray-400 mt-0.5 px-0.5">
                <span>00:00</span><span>06:00</span><span>12:00</span><span>18:00</span><span>24:00</span>
            </div>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-100 bg-gray-50">
                    <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <th class="px-5 py-3">Shift Name</th>
                        <th class="px-5 py-3">Start</th>
                        <th class="px-5 py-3">End</th>
                        <th class="px-5 py-3">Duration</th>
                        <th class="px-5 py-3 text-center">Break</th>
                        <th class="px-5 py-3 text-center">OEE Planned</th>
                        <th class="px-5 py-3 text-center">Status</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <template x-for="shift in shifts" :key="shift.id">
                        <tr class="hover:bg-gray-50/60 transition-colors">

                            {{-- Name --}}
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-2.5">
                                    <span
                                        class="h-3 w-3 rounded-full shrink-0"
                                        :style="`background-color: ${shiftColor(shift)}`"
                                    ></span>
                                    <span class="font-semibold text-gray-900" x-text="shift.name"></span>
                                    <template x-if="shift.crosses_midnight">
                                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">
                                            ✦ crosses midnight
                                        </span>
                                    </template>
                                </div>
                            </td>

                            {{-- Start --}}
                            <td class="px-5 py-3.5">
                                <span class="font-mono text-base font-semibold text-gray-800" x-text="shift.start_time.slice(0,5)"></span>
                            </td>

                            {{-- End --}}
                            <td class="px-5 py-3.5">
                                <span class="font-mono text-base font-semibold text-gray-800" x-text="shift.end_time.slice(0,5)"></span>
                                <template x-if="shift.crosses_midnight">
                                    <span class="ml-1.5 text-xs text-amber-600 font-medium">(next day)</span>
                                </template>
                            </td>

                            {{-- Duration --}}
                            <td class="px-5 py-3.5">
                                <div class="flex items-baseline gap-1.5">
                                    <span class="text-lg font-bold text-gray-900" x-text="fmtDuration(shift.duration_min)"></span>
                                    <span class="text-xs text-gray-400" x-text="'(' + shift.duration_min + ' min)'"></span>
                                </div>
                            </td>

                            {{-- Break --}}
                            <td class="px-5 py-3.5 text-center">
                                <template x-if="shift.break_start && shift.break_end">
                                    <div class="flex flex-col items-center gap-0.5">
                                        <span class="font-mono text-xs font-semibold text-orange-700"
                                              x-text="shift.break_start.slice(0,5) + ' – ' + shift.break_end.slice(0,5)"></span>
                                        <span class="rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-600"
                                              x-text="shift.break_min + ' min'"></span>
                                    </div>
                                </template>
                                <template x-if="!shift.break_start || !shift.break_end">
                                    <span class="text-gray-300 text-xs">—</span>
                                </template>
                            </td>

                            {{-- OEE Planned = Duration − Break --}}
                            <td class="px-5 py-3.5 text-center">
                                <div class="flex flex-col items-center gap-1">
                                    <span class="font-bold text-indigo-700" x-text="fmtDuration(shift.duration_min - (shift.break_min || 0))"></span>
                                    <div class="w-20 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                        <div
                                            class="h-full rounded-full bg-indigo-400"
                                            :style="`width: ${Math.min(100, (shift.duration_min - (shift.break_min||0)) / 14.4)}%`"
                                        ></div>
                                    </div>
                                </div>
                            </td>

                            {{-- Status --}}
                            <td class="px-5 py-3.5 text-center">
                                <span
                                    :class="shift.is_active
                                        ? 'bg-green-100 text-green-800'
                                        : 'bg-gray-100 text-gray-500'"
                                    class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                    x-text="shift.is_active ? 'Active' : 'Inactive'"
                                ></span>
                            </td>

                            {{-- Actions --}}
                            <td class="px-5 py-3.5 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button
                                        @click="openEdit(shift)"
                                        class="rounded-lg px-3 py-1.5 text-xs font-medium text-indigo-600 hover:bg-indigo-50 transition-colors"
                                    >Edit</button>
                                    <template x-if="shift.is_active">
                                        <button
                                            @click="confirmDeactivate(shift)"
                                            class="rounded-lg px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 transition-colors"
                                        >Deactivate</button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </template>

                    <template x-if="shifts.length === 0">
                        <tr>
                            <td colspan="8" class="px-5 py-14 text-center">
                                <svg class="mx-auto h-10 w-10 text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="text-gray-400 text-sm">No shifts yet. Click <strong>New Shift</strong> to add one.</p>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════
         CREATE / EDIT MODAL
    ═══════════════════════════════════════════════════════════ --}}
    <div
        x-show="showModal"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
        @keydown.escape.window="closeModal()"
    >
        <div
            x-show="showModal"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            @click.stop
            class="w-full max-w-md rounded-2xl bg-white shadow-2xl"
        >
            {{-- Modal header --}}
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                <h3 class="font-semibold text-gray-900" x-text="editShift ? 'Edit Shift' : 'New Shift'"></h3>
                <button @click="closeModal()" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Modal body --}}
            <div class="px-6 py-5 space-y-4">

                {{-- Modal error --}}
                <div x-show="modalError" x-cloak class="rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700" x-text="modalError"></div>

                {{-- Name --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Shift Name <span class="text-red-500">*</span></label>
                    <input
                        type="text"
                        x-model="form.name"
                        placeholder="e.g. Morning Shift"
                        maxlength="50"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300"
                    >
                </div>

                {{-- Times --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Time <span class="text-red-500">*</span></label>
                        <input
                            type="time"
                            x-model="form.start_time"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">End Time <span class="text-red-500">*</span></label>
                        <input
                            type="time"
                            x-model="form.end_time"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300"
                        >
                    </div>
                </div>

                {{-- Break Time --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Break Time (minutes)</label>
                    <div class="flex items-center gap-3">
                        <input
                            type="number"
                            x-model.number="form.break_min"
                            min="0"
                            max="480"
                            step="5"
                            placeholder="0"
                            class="w-28 rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300"
                        >
                        <span class="text-xs text-gray-500">
                            e.g. 30 for a 30-min lunch break. Subtracted from OEE planned time.
                        </span>
                    </div>
                </div>

                {{-- Duration preview --}}
                <template x-if="form.start_time && form.end_time">
                    <div class="rounded-xl border p-3 text-sm space-y-2"
                         :class="crossesMidnight ? 'border-amber-200 bg-amber-50' : 'border-indigo-100 bg-indigo-50'">

                        {{-- Duration row --}}
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-gray-500">Shift Duration</span>
                            <span class="font-semibold text-gray-800"
                                  x-text="fmtDuration(calculatedDuration) + ' (' + calculatedDuration + ' min)'"></span>
                        </div>

                        {{-- Break row --}}
                        <div class="flex items-center justify-between" x-show="form.break_min > 0">
                            <span class="text-xs text-orange-600 flex items-center gap-1">
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                                </svg>
                                Break Time
                            </span>
                            <span class="font-semibold text-orange-600" x-text="'− ' + form.break_min + ' min'"></span>
                        </div>

                        {{-- Divider --}}
                        <div class="border-t" :class="crossesMidnight ? 'border-amber-200' : 'border-indigo-200'" x-show="form.break_min > 0"></div>

                        {{-- OEE Planned --}}
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold" :class="crossesMidnight ? 'text-amber-700' : 'text-indigo-700'">
                                OEE Planned Time
                            </span>
                            <span class="font-bold text-base" :class="crossesMidnight ? 'text-amber-800' : 'text-indigo-800'"
                                  x-text="fmtDuration(Math.max(0, calculatedDuration - (form.break_min || 0))) + ' (' + Math.max(0, calculatedDuration - (form.break_min || 0)) + ' min)'">
                            </span>
                        </div>

                        <template x-if="crossesMidnight">
                            <p class="text-xs text-amber-600 flex items-center gap-1">
                                <span>✦</span>
                                <span>This shift crosses midnight — ends the next calendar day.</span>
                            </p>
                        </template>

                        {{-- Mini timeline bar --}}
                        <div class="relative h-5 rounded bg-gray-200 overflow-hidden">
                            <div
                                class="absolute top-0 h-full rounded transition-all duration-300"
                                :class="crossesMidnight ? 'bg-amber-400' : 'bg-indigo-400'"
                                :style="previewBarStyle"
                            ></div>
                            <div class="absolute inset-0 flex items-center justify-center text-[10px] font-bold text-white mix-blend-overlay"
                                 x-text="form.start_time + ' → ' + form.end_time"></div>
                        </div>
                    </div>
                </template>

                {{-- Active toggle (edit mode) --}}
                <template x-if="editShift">
                    <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                        <div>
                            <p class="text-sm font-medium text-gray-700">Active</p>
                            <p class="text-xs text-gray-400">Inactive shifts are hidden from production planning.</p>
                        </div>
                        <button
                            type="button"
                            @click="form.is_active = !form.is_active"
                            :class="form.is_active ? 'bg-indigo-600' : 'bg-gray-300'"
                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none"
                        >
                            <span
                                :class="form.is_active ? 'translate-x-6' : 'translate-x-1'"
                                class="inline-block h-4 w-4 rounded-full bg-white shadow transition-transform"
                            ></span>
                        </button>
                    </div>
                </template>

            </div>

            {{-- Modal footer --}}
            <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4">
                <button
                    @click="closeModal()"
                    class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 transition-colors"
                >Cancel</button>
                <button
                    @click="saveShift()"
                    :disabled="saving || !form.name || !form.start_time || !form.end_time"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 px-5 py-2 text-sm font-semibold text-white shadow-sm transition-colors"
                >
                    <svg x-show="saving" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span x-text="editShift ? 'Save Changes' : 'Create Shift'"></span>
                </button>
            </div>
        </div>
    </div>

</div>{{-- end x-data --}}
@endsection

@push('scripts')
<script>
function shiftManager(initialShifts, factoryId, factories) {
    return {
        shifts:      initialShifts || [],
        factoryId:   factoryId,
        factories:   factories || [],
        showModal:   false,
        editShift:   null,
        saving:      false,
        error:       null,
        modalError:  null,

        form: {
            name:       '',
            start_time: '',
            end_time:   '',
            break_min:  0,
            is_active:  true,
        },

        // ── Computed ─────────────────────────────────────────

        get calculatedDuration() {
            if (!this.form.start_time || !this.form.end_time) return 0;
            const [sh, sm] = this.form.start_time.split(':').map(Number);
            const [eh, em] = this.form.end_time.split(':').map(Number);
            const startMin = sh * 60 + sm;
            const endMin   = eh * 60 + em;
            const diff = endMin <= startMin
                ? (1440 - startMin) + endMin  // crosses midnight
                : endMin - startMin;
            return diff;
        },

        get crossesMidnight() {
            if (!this.form.start_time || !this.form.end_time) return false;
            const [sh, sm] = this.form.start_time.split(':').map(Number);
            const [eh, em] = this.form.end_time.split(':').map(Number);
            return (eh * 60 + em) <= (sh * 60 + sm);
        },

        get previewBarStyle() {
            if (!this.form.start_time || !this.form.end_time) return '';
            const [sh, sm] = this.form.start_time.split(':').map(Number);
            const [eh, em] = this.form.end_time.split(':').map(Number);
            const startPct = (sh * 60 + sm) / 1440 * 100;
            const endPct   = this.crossesMidnight
                ? 100
                : (eh * 60 + em) / 1440 * 100;
            return `left: ${startPct}%; width: ${endPct - startPct}%`;
        },

        // ── Lifecycle ─────────────────────────────────────────

        init() {
            // Nothing async needed — shifts loaded server-side
        },

        // ── Actions ───────────────────────────────────────────

        openCreate() {
            this.editShift  = null;
            this.modalError = null;
            this.form = { name: '', start_time: '08:00', end_time: '20:00', break_min: 0, is_active: true };
            this.showModal = true;
        },

        openEdit(shift) {
            this.editShift  = shift;
            this.modalError = null;
            this.form = {
                name:       shift.name,
                start_time: shift.start_time.slice(0, 5),
                end_time:   shift.end_time.slice(0, 5),
                break_min:  shift.break_min || 0,
                is_active:  shift.is_active,
            };
            this.showModal = true;
        },

        closeModal() {
            this.showModal  = false;
            this.editShift  = null;
            this.modalError = null;
        },

        async saveShift() {
            if (!this.form.name || !this.form.start_time || !this.form.end_time) return;
            this.saving     = true;
            this.modalError = null;

            const isEdit = !!this.editShift;
            const url    = isEdit
                ? `/admin/shifts/${this.editShift.id}`
                : '/admin/shifts';
            const method = isEdit ? 'PUT' : 'POST';

            const body = { ...this.form };
            if (!isEdit && this.factories.length) {
                body.factory_id = this.factoryId;
            }

            try {
                const res = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type':  'application/json',
                        'Accept':        'application/json',
                        'X-CSRF-TOKEN':  document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(body),
                });

                const data = await res.json();
                if (!res.ok) {
                    this.modalError = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Save failed.');
                    return;
                }

                // Update local list in-place
                if (isEdit) {
                    const idx = this.shifts.findIndex(s => s.id === this.editShift.id);
                    if (idx !== -1) this.shifts[idx] = data;
                } else {
                    this.shifts.push(data);
                }

                this.closeModal();
            } catch (e) {
                this.modalError = 'Network error: ' + e.message;
            } finally {
                this.saving = false;
            }
        },

        async confirmDeactivate(shift) {
            if (!confirm(`Deactivate "${shift.name}"?\n\nThis will hide it from production planning. Existing plans are not affected.`)) return;

            this.error = null;
            try {
                const res = await fetch(`/admin/shifts/${shift.id}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept':       'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });
                const data = await res.json();
                if (!res.ok) {
                    this.error = data.message || 'Failed to deactivate.';
                    return;
                }
                // Update local list
                const idx = this.shifts.findIndex(s => s.id === shift.id);
                if (idx !== -1) this.shifts[idx] = { ...shift, is_active: false };
            } catch (e) {
                this.error = 'Network error: ' + e.message;
            }
        },

        switchFactory(id) {
            window.location.href = `/admin/shifts?factory_id=${id}`;
        },

        // ── Formatting helpers ─────────────────────────────────

        fmtDuration(min) {
            if (!min) return '—';
            const h = Math.floor(min / 60);
            const m = min % 60;
            return m > 0 ? `${h}h ${m}min` : `${h}h`;
        },

        // ── Color palette for shifts ───────────────────────────

        shiftColor(shift) {
            const palette = ['#6366f1','#f59e0b','#22c55e','#ef4444','#3b82f6','#8b5cf6','#ec4899'];
            return palette[shift.id % palette.length];
        },

        // ── Timeline bar style helpers ─────────────────────────

        shiftBarStyle(shift) {
            const [sh, sm] = shift.start_time.split(':').map(Number);
            const [eh, em] = shift.end_time.split(':').map(Number);
            const left  = (sh * 60 + sm) / 1440 * 100;
            const width = ((eh * 60 + em) - (sh * 60 + sm)) / 1440 * 100;
            return `left:${left}%; width:${width}%; background-color:${this.shiftColor(shift)};`;
        },

        shiftBarStyleNightLeft(shift) {
            const [sh, sm] = shift.start_time.split(':').map(Number);
            const left  = (sh * 60 + sm) / 1440 * 100;
            const width = (1440 - (sh * 60 + sm)) / 1440 * 100;
            return `left:${left}%; width:${width}%; background-color:${this.shiftColor(shift)};`;
        },

        shiftBarStyleNightRight(shift) {
            const [eh, em] = shift.end_time.split(':').map(Number);
            const width = (eh * 60 + em) / 1440 * 100;
            return `left:0%; width:${width}%; background-color:${this.shiftColor(shift)};`;
        },
    };
}
</script>
@endpush

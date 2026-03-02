@extends('admin.layouts.app')

@section('title', 'Production Planning')

@push('head')
<style>
    [x-cloak] { display: none !important; }

    .plan-card { transition: transform 0.12s ease, box-shadow 0.12s ease; }
    .plan-card:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,.1); }

    /* Sticky header + first column on the table */
    .cal-table { border-collapse: separate; border-spacing: 0; }
    .cal-table thead th { position: sticky; top: 0; z-index: 20; }
    .cal-table td.machine-col,
    .cal-table th.machine-col { position: sticky; left: 0; z-index: 10; }
    .cal-table thead th.machine-col { z-index: 30; }

    .status-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

    .modal-in { animation: modalIn .18s ease-out; }
    @keyframes modalIn {
        from { opacity: 0; transform: scale(.97) translateY(8px); }
        to   { opacity: 1; transform: scale(1)   translateY(0);  }
    }
</style>
@endpush

@section('content')
<div
    x-data="productionCalendar(
        '{{ $apiToken }}',
        {{ $factoryId ?? 'null' }},
        {{ $factories->toJson() }},
        {{ $machines->toJson() }},
        {{ $shifts->toJson() }},
        {{ $parts->toJson() }}
    )"
    x-init="init()"
    class="h-full flex flex-col overflow-hidden bg-gray-50"
>

{{-- ══════════════════════════════════════════════════════════
     PLAN MODAL (create / edit)
══════════════════════════════════════════════════════════ --}}
<div
    x-show="showModal"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    @keydown.escape.window="showModal = false"
>
    <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" @click="showModal = false"></div>

    <div class="modal-in relative z-10 w-full max-w-lg bg-white rounded-2xl shadow-2xl overflow-hidden" @click.stop>

        {{-- ── Header ─────────────────────────────────── --}}
        <div class="flex items-start justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h2 class="text-base font-bold text-gray-900"
                    x-text="modalMode === 'create' ? 'New Production Plan' : 'Edit Production Plan'"></h2>
                <p class="text-xs text-gray-400 mt-0.5" x-text="modalSubtitle"></p>
            </div>
            <button @click="showModal = false"
                    class="ml-4 rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- ── Error banner ────────────────────────────── --}}
        <div x-show="formError" x-cloak
             class="mx-6 mt-4 rounded-lg bg-red-50 border border-red-200 px-4 py-2.5 text-sm text-red-700"
             x-text="formError"></div>

        {{-- ── Status strip (edit only) ─────────────────── --}}
        <template x-if="modalMode === 'edit'">
            <div class="px-6 pt-4 pb-2">
                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-2">Status</p>
                <div class="flex flex-wrap gap-1.5">
                    <template x-for="s in statuses" :key="s.value">
                        <button
                            @click="form.status = s.value"
                            :class="form.status === s.value ? s.activeClass : 'bg-white border-gray-200 text-gray-500 hover:border-gray-400'"
                            class="px-3 py-1 rounded-full text-xs font-semibold border transition-all"
                            x-text="s.label"
                        ></button>
                    </template>
                </div>
            </div>
        </template>

        {{-- ── Form fields ──────────────────────────────── --}}
        <div class="px-6 py-4 space-y-4">

            <div class="grid grid-cols-2 gap-3">
                {{-- Machine --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Machine</label>
                    <select x-model="form.machine_id"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                        <option value="">Select machine…</option>
                        <template x-for="m in machines" :key="m.id">
                            <option :value="m.id" x-text="m.name + ' (' + m.code + ')'"></option>
                        </template>
                    </select>
                </div>

                {{-- Shift --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Shift</label>
                    <select x-model="form.shift_id"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                        <option value="">Select shift…</option>
                        <template x-for="s in shifts" :key="s.id">
                            <option :value="s.id" x-text="s.name + ' (' + s.start_time.slice(0,5) + '–' + s.end_time.slice(0,5) + ')'"></option>
                        </template>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                {{-- Date --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Production Date</label>
                    <input type="date" x-model="form.planned_date"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                </div>

                {{-- Qty --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Planned Qty</label>
                    <input type="number" x-model.number="form.planned_qty" min="1"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300"
                           placeholder="100">
                </div>
            </div>

            {{-- Part --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Part</label>
                <select x-model="form.part_id"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                    <option value="">Select part…</option>
                    <template x-for="p in parts" :key="p.id">
                        <option :value="p.id" x-text="p.part_number + '  —  ' + p.name"></option>
                    </template>
                </select>
                <template x-if="selectedPart">
                    <p class="mt-1 text-xs text-indigo-600">
                        Cycle time: <span class="font-semibold" x-text="selectedPart.cycle_time_std + 's/unit'"></span>
                        <span x-show="cycleTimeHint" class="text-gray-400 ml-1">≈ <span x-text="cycleTimeHint"></span></span>
                    </p>
                </template>
            </div>

            {{-- Factory (super-admin create) --}}
            <template x-if="factories.length > 0 && modalMode === 'create'">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Factory</label>
                    <select x-model="form.factory_id"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                        <option value="">Select factory…</option>
                        <template x-for="f in factories" :key="f.id">
                            <option :value="f.id" x-text="f.name"></option>
                        </template>
                    </select>
                </div>
            </template>

            {{-- Notes --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">
                    Notes <span class="text-gray-400 font-normal">(optional)</span>
                </label>
                <textarea x-model="form.notes" rows="2" placeholder="Additional notes…"
                          class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300 resize-none"></textarea>
            </div>
        </div>

        {{-- ── Footer ───────────────────────────────────── --}}
        <div class="flex items-center justify-between px-6 py-4 bg-gray-50 border-t border-gray-100">
            {{-- Delete button (edit mode only, non-immutable) --}}
            <div>
                <template x-if="modalMode === 'edit'">
                    <button @click="deletePlan()" :disabled="deleting"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 disabled:opacity-50 transition-colors">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        <span x-text="deleting ? 'Deleting…' : 'Delete Plan'"></span>
                    </button>
                </template>
                <template x-if="modalMode !== 'edit'"><span></span></template>
            </div>

            <div class="flex gap-2">
                <button @click="showModal = false"
                        class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button @click="savePlan()" :disabled="saving"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50 transition-colors flex items-center gap-1.5">
                    <svg x-show="saving" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span x-text="saving ? 'Saving…' : (modalMode === 'create' ? 'Create Plan' : 'Save Changes')"></span>
                </button>
            </div>
        </div>
    </div>
</div>


{{-- ══════════════════════════════════════════════════════════
     TOP TOOLBAR
══════════════════════════════════════════════════════════ --}}
<div class="shrink-0 flex flex-wrap items-center gap-3 bg-white border-b border-gray-200 px-5 py-2.5 shadow-sm">

    {{-- Factory selector (super-admin) --}}
    <template x-if="factories.length > 0">
        <div class="flex items-center gap-2">
            <label class="text-xs font-medium text-gray-400">Factory</label>
            <select @change="switchFactory($event.target.value)"
                    class="rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                <option value="">All Factories</option>
                <template x-for="f in factories" :key="f.id">
                    <option :value="f.id" :selected="currentFactoryId == f.id" x-text="f.name"></option>
                </template>
            </select>
        </div>
    </template>

    <div class="h-5 w-px bg-gray-200"></div>

    {{-- Week navigation --}}
    <div class="flex items-center gap-1">
        <button @click="prevWeek()"
                class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 transition-colors">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </button>
        <span class="px-2 text-sm font-bold text-gray-800 min-w-[170px] text-center tabular-nums" x-text="weekLabel"></span>
        <button @click="nextWeek()"
                class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 transition-colors">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        </button>
        <button @click="goToToday()"
                class="ml-1 rounded-lg border border-gray-200 bg-white px-3 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50 transition-colors">
            Today
        </button>
    </div>

    {{-- Loading --}}
    <div x-show="loading" class="flex items-center gap-1 text-xs text-gray-400">
        <svg class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
        </svg>
        Loading…
    </div>

    {{-- Right side --}}
    <div class="ml-auto flex items-center gap-4">
        {{-- Legend --}}
        <div class="hidden xl:flex items-center gap-3 text-xs text-gray-500">
            <span class="flex items-center gap-1.5"><span class="status-dot bg-gray-400"></span>Draft</span>
            <span class="flex items-center gap-1.5"><span class="status-dot bg-blue-500"></span>Scheduled</span>
            <span class="flex items-center gap-1.5"><span class="status-dot bg-amber-500"></span>In Progress</span>
            <span class="flex items-center gap-1.5"><span class="status-dot bg-green-500"></span>Completed</span>
            <span class="flex items-center gap-1.5"><span class="status-dot bg-red-400"></span>Cancelled</span>
        </div>
        <button @click="openCreate('', todayStr, '')"
                class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-semibold text-white hover:bg-indigo-500 transition-colors shadow-sm">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New Plan
        </button>
    </div>
</div>

{{-- Global error --}}
<div x-show="error" x-cloak
     class="mx-5 mt-3 rounded-lg bg-red-50 border border-red-200 px-4 py-2.5 text-sm text-red-700 flex items-center justify-between shrink-0">
    <span x-text="error"></span>
    <button @click="error = null" class="ml-3 text-red-400 hover:text-red-600 font-medium">✕</button>
</div>


{{-- ══════════════════════════════════════════════════════════
     CALENDAR TABLE
     Rows = Machines  |  Columns = Days (Mon–Sun)
     Each cell = shift slots stacked vertically
══════════════════════════════════════════════════════════ --}}
<div class="flex-1 overflow-auto">
    <table class="cal-table w-full text-sm">

        {{-- ── Column headers (sticky top) ─────────────── --}}
        <thead>
            <tr>
                {{-- Machine column header --}}
                <th class="machine-col bg-gray-50 border-b-2 border-r border-gray-200 px-4 py-3 text-left w-44 min-w-[176px]">
                    <span class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Machine</span>
                </th>

                {{-- Day headers --}}
                <template x-for="day in weekDays" :key="day.date">
                    <th :class="['border-b-2 border-r border-gray-200 px-3 py-3 text-center min-w-[150px]', day.isToday ? 'bg-indigo-50' : 'bg-gray-50']">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400" x-text="day.label"></p>
                        <p class="mt-0.5 text-xl font-extrabold leading-none"
                           :class="day.isToday ? 'text-indigo-600' : 'text-gray-700'"
                           x-text="day.dayNum"></p>
                        <p class="text-[10px] text-gray-400 mt-0.5" x-text="day.monthLabel"></p>
                        <div x-show="day.isToday" class="mx-auto mt-1 h-1 w-5 rounded-full bg-indigo-400"></div>
                    </th>
                </template>
            </tr>
        </thead>

        {{-- ── Machine rows ──────────────────────────────── --}}
        <tbody>
            {{-- Empty state --}}
            <template x-if="machines.length === 0">
                <tr>
                    <td colspan="8" class="py-24 text-center text-gray-400">
                        <svg class="h-12 w-12 mx-auto mb-3 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/>
                        </svg>
                        <p class="font-medium">No active machines found</p>
                        <p class="text-xs mt-1">Add machines first, then create production plans.</p>
                    </td>
                </tr>
            </template>

            {{-- One <tr> per machine --}}
            <template x-for="machine in machines" :key="machine.id">
                <tr class="group border-b border-gray-100">

                    {{-- Machine label (sticky left) --}}
                    <td class="machine-col bg-white border-r border-gray-100 px-4 py-3 align-top group-hover:bg-slate-50 transition-colors">
                        <p class="text-sm font-bold text-gray-800 leading-tight" x-text="machine.name"></p>
                        <p class="text-xs text-gray-400 mt-0.5 truncate"
                           x-text="machine.code + (machine.type ? ' · ' + machine.type : '')"></p>
                    </td>

                    {{-- One <td> per day --}}
                    <template x-for="day in weekDays" :key="day.date">
                        <td :class="['border-r border-gray-100 p-2 align-top group-hover:bg-slate-50/30 transition-colors', day.isToday ? 'bg-indigo-50/30' : '']"
                            style="min-height: 90px;">
                            <div class="space-y-1.5">

                                {{-- One slot per shift --}}
                                <template x-for="slot in getSlots(machine.id, day.date)" :key="slot.shiftId">
                                    <div>
                                        {{-- ── Plan card ── --}}
                                        <template x-if="slot.plan">
                                            <button
                                                @click="openPlan(slot.plan)"
                                                :class="['plan-card w-full text-left rounded-xl border-2 px-2.5 pt-2 pb-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-400', planCardClass(slot.plan.status)]"
                                            >
                                                {{-- Status line --}}
                                                <div class="flex items-center gap-1.5 mb-1.5">
                                                    <span :class="['status-dot', statusDotColor(slot.plan.status)]"></span>
                                                    <span class="text-[9px] font-bold uppercase tracking-widest opacity-70"
                                                          x-text="slot.plan.status.replace('_',' ')"></span>
                                                </div>
                                                {{-- Part number --}}
                                                <p class="text-xs font-bold truncate leading-tight"
                                                   x-text="slot.plan.part?.part_number || '—'"></p>
                                                {{-- Qty + shift name --}}
                                                <p class="text-[10px] mt-1 opacity-60 truncate">
                                                    <span x-text="Number(slot.plan.planned_qty).toLocaleString()"></span> pcs
                                                    &middot; <span x-text="slot.shiftName"></span>
                                                </p>
                                            </button>
                                        </template>

                                        {{-- ── Empty slot (add button) ── --}}
                                        <template x-if="!slot.plan">
                                            <button
                                                @click="openCreate(machine.id, day.date, slot.shiftId)"
                                                class="w-full rounded-xl border border-dashed border-gray-200 px-2 py-2 text-[11px] font-medium text-gray-300 hover:border-indigo-300 hover:text-indigo-400 hover:bg-indigo-50/70 transition-all text-left flex items-center gap-1.5"
                                            >
                                                <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                                                </svg>
                                                <span x-text="slot.shiftName"></span>
                                            </button>
                                        </template>
                                    </div>
                                </template>

                            </div>
                        </td>
                    </template>

                </tr>
            </template>
        </tbody>
    </table>
</div>

</div>{{-- end x-data --}}
@endsection

@push('scripts')
<script>
function productionCalendar(apiToken, factoryId, factories, machines, shifts, parts) {
    return {
        apiToken,
        currentFactoryId: factoryId,
        factories:  factories || [],
        machines:   machines  || [],
        shifts:     shifts    || [],
        parts:      parts     || [],

        plans:   [],
        loading: false,
        error:   null,
        weekStart: null,

        // Modal state
        showModal:  false,
        modalMode:  'create',
        saving:     false,
        deleting:   false,
        formError:  null,
        editPlan:   null,

        form: {
            machine_id:   '',
            part_id:      '',
            shift_id:     '',
            planned_date: '',
            planned_qty:  1,
            status:       'draft',
            factory_id:   '',
            notes:        '',
        },

        statuses: [
            { value: 'draft',       label: 'Draft',       activeClass: 'bg-gray-100 border-gray-400 text-gray-700' },
            { value: 'scheduled',   label: 'Scheduled',   activeClass: 'bg-blue-100 border-blue-400 text-blue-800' },
            { value: 'in_progress', label: 'In Progress', activeClass: 'bg-amber-100 border-amber-400 text-amber-800' },
            { value: 'completed',   label: 'Completed',   activeClass: 'bg-green-100 border-green-500 text-green-800' },
            { value: 'cancelled',   label: 'Cancelled',   activeClass: 'bg-red-100 border-red-400 text-red-700' },
        ],

        // ── Computed getters ────────────────────────────────

        get todayStr() {
            return this.fmtDate(new Date());
        },

        get weekDays() {
            if (!this.weekStart) return [];
            const LABELS  = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
            const MONTHS  = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            const today   = this.todayStr;
            const days    = [];
            for (let i = 0; i < 7; i++) {
                const d      = new Date(this.weekStart.getTime() + i * 86400000);
                const ds     = this.fmtDate(d);
                days.push({
                    date:       ds,
                    label:      LABELS[d.getDay()],
                    dayNum:     d.getDate(),
                    monthLabel: MONTHS[d.getMonth()],
                    isToday:    ds === today,
                });
            }
            return days;
        },

        get weekLabel() {
            if (!this.weekDays.length) return '';
            const f = this.weekDays[0];
            const l = this.weekDays[6];
            const y = new Date(this.weekStart).getFullYear();
            if (f.monthLabel === l.monthLabel) {
                return `${f.monthLabel} ${f.dayNum} – ${l.dayNum}, ${y}`;
            }
            return `${f.monthLabel} ${f.dayNum} – ${l.monthLabel} ${l.dayNum}, ${y}`;
        },

        // Fast O(1) lookup: "machineId:date:shiftId" → plan object
        // planned_date from Laravel API is ISO datetime ("2026-03-02T00:00:00.000000Z")
        // but weekDays uses "YYYY-MM-DD" — normalize to date-only for matching.
        get plansMap() {
            const m = {};
            for (const p of this.plans) {
                const d = p.planned_date ? String(p.planned_date).substring(0, 10) : '';
                m[`${p.machine_id}:${d}:${p.shift_id}`] = p;
            }
            return m;
        },

        get selectedPart() {
            return this.parts.find(p => p.id == this.form.part_id) || null;
        },

        get cycleTimeHint() {
            const p = this.selectedPart;
            if (!p?.cycle_time_std || !this.form.planned_qty) return '';
            const mins = Math.round(p.cycle_time_std * this.form.planned_qty / 60);
            const h = Math.floor(mins / 60);
            const m = mins % 60;
            return h > 0 ? `${h}h ${m}m total` : `${m}m total`;
        },

        get modalSubtitle() {
            const parts = [];
            const m = this.machines.find(x => x.id == this.form.machine_id);
            const s = this.shifts.find(x => x.id == this.form.shift_id);
            if (m) parts.push(m.name);
            if (s) parts.push(s.name);
            if (this.form.planned_date) parts.push(this.fmtDisplayDate(this.form.planned_date));
            return parts.join(' · ');
        },

        // ── Lifecycle ───────────────────────────────────────

        init() {
            this.weekStart = this.getMonday(new Date());
            this.loadPlans();
        },

        // ── Week navigation ─────────────────────────────────

        getMonday(date) {
            const d   = new Date(date);
            const day = d.getDay();
            d.setDate(d.getDate() - day + (day === 0 ? -6 : 1));
            d.setHours(0, 0, 0, 0);
            return d;
        },

        prevWeek() {
            this.weekStart = new Date(this.weekStart.getTime() - 7 * 86400000);
            this.loadPlans();
        },

        nextWeek() {
            this.weekStart = new Date(this.weekStart.getTime() + 7 * 86400000);
            this.loadPlans();
        },

        goToToday() {
            this.weekStart = this.getMonday(new Date());
            this.loadPlans();
        },

        switchFactory(id) {
            this.currentFactoryId = id ? parseInt(id) : null;
            this.loadPlans();
        },

        // ── Data loading ────────────────────────────────────

        async loadPlans() {
            if (!this.weekStart) return;
            this.loading = true;
            this.error   = null;
            try {
                const from   = this.fmtDate(this.weekStart);
                const to     = this.fmtDate(new Date(this.weekStart.getTime() + 6 * 86400000));
                const params = new URLSearchParams({ from_date: from, to_date: to, per_page: 500 });
                if (this.currentFactoryId) params.append('factory_id', this.currentFactoryId);

                const res = await fetch(`/api/v1/production-plans?${params}`, {
                    headers: { 'Authorization': `Bearer ${this.apiToken}`, 'Accept': 'application/json' },
                });
                if (!res.ok) throw new Error(`Server error ${res.status}`);

                const json  = await res.json();
                this.plans  = json.data || [];
            } catch (e) {
                this.error = 'Failed to load plans: ' + e.message;
            } finally {
                this.loading = false;
            }
        },

        // ── Calendar helpers ────────────────────────────────

        getPlan(machineId, date, shiftId) {
            return this.plansMap[`${machineId}:${date}:${shiftId}`] || null;
        },

        // Returns array of { shiftId, shiftName, plan|null } for one cell
        getSlots(machineId, date) {
            return this.shifts.map(s => ({
                shiftId:   s.id,
                shiftName: s.name,
                plan:      this.getPlan(machineId, date, s.id),
            }));
        },

        // ── Modal open ──────────────────────────────────────

        openCreate(machineId, date, shiftId) {
            this.modalMode = 'create';
            this.editPlan  = null;
            this.formError = null;
            this.form = {
                machine_id:   machineId || '',
                part_id:      '',
                shift_id:     shiftId   || '',
                planned_date: date      || this.todayStr,
                planned_qty:  1,
                status:       'draft',
                factory_id:   this.currentFactoryId || '',
                notes:        '',
            };
            this.showModal = true;
        },

        openPlan(plan) {
            this.modalMode = 'edit';
            this.editPlan  = plan;
            this.formError = null;
            this.form = {
                machine_id:   plan.machine_id,
                part_id:      plan.part_id,
                shift_id:     plan.shift_id,
                planned_date: plan.planned_date,
                planned_qty:  plan.planned_qty,
                status:       plan.status,
                factory_id:   plan.factory_id || '',
                notes:        plan.notes || '',
            };
            this.showModal = true;
        },

        // ── CRUD ────────────────────────────────────────────

        async savePlan() {
            if (!this.form.machine_id || !this.form.part_id ||
                !this.form.shift_id   || !this.form.planned_date || !this.form.planned_qty) {
                this.formError = 'Please fill in Machine, Part, Shift, Date and Planned Qty.';
                return;
            }
            this.saving    = true;
            this.formError = null;
            try {
                const isCreate = this.modalMode === 'create';
                const url      = isCreate
                    ? '/api/v1/production-plans'
                    : `/api/v1/production-plans/${this.editPlan.id}`;

                const body = { ...this.form };
                if (!isCreate) delete body.factory_id;

                const res = await fetch(url, {
                    method:  isCreate ? 'POST' : 'PUT',
                    headers: {
                        'Authorization': `Bearer ${this.apiToken}`,
                        'Accept':        'application/json',
                        'Content-Type':  'application/json',
                    },
                    body: JSON.stringify(body),
                });

                if (!res.ok) {
                    const err = await res.json().catch(() => ({}));
                    throw new Error(
                        err.message ||
                        (err.errors ? Object.values(err.errors).flat().join(', ') : `Error ${res.status}`)
                    );
                }

                this.showModal = false;
                await this.loadPlans();
            } catch (e) {
                this.formError = e.message;
            } finally {
                this.saving = false;
            }
        },

        async deletePlan() {
            if (!confirm('Delete this production plan? This action cannot be undone.')) return;
            this.deleting  = true;
            this.formError = null;
            try {
                const res = await fetch(`/api/v1/production-plans/${this.editPlan.id}`, {
                    method:  'DELETE',
                    headers: { 'Authorization': `Bearer ${this.apiToken}`, 'Accept': 'application/json' },
                });
                if (!res.ok) throw new Error(`Error ${res.status}`);
                this.showModal = false;
                await this.loadPlans();
            } catch (e) {
                this.formError = e.message;
            } finally {
                this.deleting = false;
            }
        },

        // ── Style helpers ───────────────────────────────────

        planCardClass(status) {
            const map = {
                draft:       'border-gray-300   bg-gray-50   text-gray-700',
                scheduled:   'border-blue-300   bg-blue-50   text-blue-800',
                in_progress: 'border-amber-300  bg-amber-50  text-amber-800',
                completed:   'border-green-300  bg-green-50  text-green-800',
                cancelled:   'border-red-200    bg-red-50    text-red-600 opacity-60',
            };
            return map[status] || map.draft;
        },

        statusDotColor(status) {
            const map = {
                draft:       'bg-gray-400',
                scheduled:   'bg-blue-500',
                in_progress: 'bg-amber-500',
                completed:   'bg-green-500',
                cancelled:   'bg-red-400',
            };
            return map[status] || 'bg-gray-400';
        },

        // ── Date helpers ────────────────────────────────────

        fmtDate(date) {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        },

        fmtDisplayDate(ds) {
            if (!ds) return '';
            return new Date(ds + 'T00:00:00').toLocaleDateString('en-US', {
                month: 'short', day: 'numeric', year: 'numeric',
            });
        },
    };
}
</script>
@endpush

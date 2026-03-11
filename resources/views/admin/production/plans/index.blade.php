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

@php
    $hasMultiFactory = $factories->isNotEmpty();
    $factoriesJson   = $hasMultiFactory ? $factories->toJson() : '[]';
@endphp

<div
    x-data="productionCalendar(
        '{{ $apiToken }}',
        {{ $factoryId ?? 'null' }},
        {{ $factoriesJson }},
        {{ $machines->toJson() }},
        {{ $shifts->toJson() }},
        {{ $parts->toJson() }},
        {{ json_encode($weekOffDays) }},
        {{ json_encode($holidays) }}
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

    {{-- Modal panel: flex-col so header+footer are fixed and content area scrolls --}}
    <div class="modal-in relative z-10 w-full max-w-lg bg-white rounded-2xl shadow-2xl flex flex-col"
         style="max-height: calc(100vh - 2.5rem)" @click.stop>

        {{-- ── Header (always visible) ─────────────────── --}}
        <div class="flex items-start justify-between px-6 py-4 border-b border-gray-100 flex-shrink-0">
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

        {{-- ── Status strip (edit only, always visible) ─── --}}
        <template x-if="modalMode === 'edit'">
            <div class="px-6 py-3 bg-slate-50 border-b border-gray-100 flex-shrink-0">
                <div class="flex items-center gap-3">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest whitespace-nowrap">Status</span>
                    <div class="flex flex-wrap gap-1.5">
                        <template x-for="s in statuses" :key="s.value">
                            <button
                                @click="form.status = s.value"
                                :class="form.status === s.value ? s.activeClass : 'bg-white border-gray-200 text-gray-500 hover:border-gray-300'"
                                class="px-3 py-1 rounded-full text-xs font-semibold border transition-all"
                                x-text="s.label"
                            ></button>
                        </template>
                    </div>
                </div>
            </div>
        </template>

        {{-- ── Scrollable content area ─────────────────── --}}
        <div class="flex-1 overflow-y-auto min-h-0">

            {{-- Error banner --}}
            <div x-show="formError" x-cloak
                 class="mx-6 mt-4 rounded-lg bg-red-50 border border-red-200 px-4 py-2.5 text-sm text-red-700 flex items-start gap-2"
                 x-text="formError"></div>

            <div class="px-6 py-4 space-y-4">

                {{-- Row 1: Machine + Shift --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Machine</label>
                        <select x-model="form.machine_id" @change="checkPlanAvailability()"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                            <option value="">Select machine…</option>
                            <template x-for="m in machines" :key="m.id">
                                <option :value="m.id" x-text="m.name + ' (' + m.code + ')'"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Shift</label>
                        <select x-model="form.shift_id" @change="checkPlanAvailability()"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                            <option value="">Select shift…</option>
                            <template x-for="s in shifts" :key="s.id">
                                <option :value="s.id" x-text="s.name + ' (' + s.start_time.slice(0,5) + '–' + s.end_time.slice(0,5) + ')'"></option>
                            </template>
                        </select>
                    </div>
                </div>

                {{-- Row 2: Date + Qty --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Production Date</label>
                        <input type="date" x-model="form.planned_date" @change="checkPlanAvailability()"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Planned Qty</label>
                        <input type="number" x-model.number="form.planned_qty" min="1"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300"
                               placeholder="100">
                    </div>
                </div>

                {{-- Machine Availability Banner (create mode only) --}}
                <template x-if="modalMode === 'create'">
                    <div>
                        {{-- Checking --}}
                        <template x-if="planAvail.checking">
                            <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-500">
                                <svg class="h-3.5 w-3.5 animate-spin text-gray-400" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                                </svg>
                                Checking machine availability…
                            </div>
                        </template>

                        {{-- Result: Full --}}
                        <template x-if="!planAvail.checking && planAvail.is_full === true">
                            <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2.5">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex items-start gap-2">
                                        <span class="mt-0.5 text-red-500">
                                            <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                            </svg>
                                        </span>
                                        <div>
                                            <p class="text-xs font-semibold text-red-700">Machine fully allocated on this date</p>
                                            <p class="text-xs text-red-500 mt-0.5" x-show="planAvail.next_date">
                                                Next available: <span class="font-semibold" x-text="planAvail.next_date"></span>
                                            </p>
                                        </div>
                                    </div>
                                    <button x-show="planAvail.next_date" type="button"
                                            @click="form.planned_date = planAvail.next_date; checkPlanAvailability()"
                                            class="shrink-0 rounded-md bg-red-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-red-700 transition-colors">
                                        Use this date
                                    </button>
                                </div>
                            </div>
                        </template>

                        {{-- Result: Available --}}
                        <template x-if="!planAvail.checking && planAvail.is_full === false">
                            <div class="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-xs text-green-700">
                                <svg class="h-3.5 w-3.5 text-green-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <span>Machine available — <span class="font-semibold" x-text="planAvail.free_min != null ? Math.round(planAvail.free_min) + ' min free' : 'capacity available'"></span></span>
                            </div>
                        </template>
                    </div>
                </template>

                {{-- Row 3: Part + Process Step (side by side) --}}
                <div class="grid grid-cols-2 gap-3">
                    {{-- Part --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Part</label>
                        <select x-model="form.part_id" @change="form.part_process_id = ''"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                            <option value="">Select part…</option>
                            <template x-for="p in parts" :key="p.id">
                                <option :value="p.id" x-text="p.part_number + ' — ' + p.name"></option>
                            </template>
                        </select>
                        <template x-if="selectedPart">
                            <p class="mt-1 text-xs text-indigo-500">
                                Std: <span class="font-semibold" x-text="selectedPart.cycle_time_std + 's'"></span>
                                <span x-show="cycleTimeHint" class="text-gray-400 ml-1">· <span x-text="cycleTimeHint"></span></span>
                            </p>
                        </template>
                    </div>

                    {{-- Process Step --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">
                            Process Step
                            <span x-show="selectedPartProcesses.length > 0" class="text-red-400 ml-0.5">*</span>
                        </label>

                        {{-- No part selected --}}
                        <template x-if="!selectedPart">
                            <div class="w-full rounded-lg border border-dashed border-gray-200 px-3 py-2 text-sm text-gray-300 bg-gray-50/60 italic">
                                Select a part first
                            </div>
                        </template>

                        {{-- Part selected — show process dropdown --}}
                        <template x-if="selectedPart && selectedPartProcesses.length > 0">
                            <select x-model="form.part_process_id"
                                    class="w-full rounded-lg border px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300 transition-colors"
                                    :class="form.part_process_id
                                        ? 'border-indigo-300 bg-indigo-50/40 text-indigo-900'
                                        : 'border-amber-300 bg-amber-50/60'">
                                <option value="">— select step —</option>
                                <template x-for="step in selectedPartProcesses" :key="step.id">
                                    <option :value="step.id"
                                            x-text="'Step ' + step.sequence_order + ': ' + (step.process_master?.name || '?') + ' (' + effectiveCycleTime(step).toFixed(1) + ' min)'">
                                    </option>
                                </template>
                            </select>
                        </template>

                        {{-- Part has no routing --}}
                        <template x-if="selectedPart && selectedPartProcesses.length === 0">
                            <div class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-400 bg-gray-50">
                                No routing defined
                            </div>
                        </template>

                        {{-- Status hint below dropdown --}}
                        <template x-if="selectedPartProcesses.length > 0">
                            <p class="mt-1 text-[11px] leading-tight"
                               :class="form.part_process_id ? 'text-indigo-500' : 'text-amber-500'">
                                <span x-show="!form.part_process_id">⚠ Select the step this machine runs</span>
                                <span x-show="form.part_process_id && selectedProcess"
                                      x-text="selectedProcess ? '✓ ' + selectedProcess.process_master?.name + '  ·  ' + effectiveCycleTime(selectedProcess).toFixed(1) + ' min/unit' : ''"></span>
                            </p>
                        </template>
                    </div>
                </div>

                {{-- Factory (only when multiple factories exist, create mode only) --}}
                @if($hasMultiFactory)
                <template x-if="modalMode === 'create'">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Factory</label>
                        <select x-model="form.factory_id"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                            <option value="">Select factory…</option>
                            <template x-for="f in factories" :key="f.id">
                                <option :value="f.id" x-text="f.name"></option>
                            </template>
                        </select>
                    </div>
                </template>
                @endif

                {{-- ── Process Flow Panel ──────────────────── --}}
                <template x-if="selectedPartProcesses.length > 0">
                    <div class="rounded-xl border border-indigo-100 bg-gradient-to-b from-slate-50 to-white overflow-hidden">

                        {{-- Panel header --}}
                        <div class="flex items-center justify-between px-4 py-2.5 bg-indigo-600 text-white">
                            <div class="flex items-center gap-2">
                                <svg class="h-3.5 w-3.5 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                                </svg>
                                <span class="text-xs font-bold tracking-wide">Manufacturing Routing</span>
                            </div>
                            <span class="text-[10px] opacity-70">Click a step to assign</span>
                        </div>

                        {{-- Step cards row --}}
                        <div class="flex items-center overflow-x-auto px-3 py-3 gap-0">
                            <template x-for="(step, idx) in selectedPartProcesses" :key="step.id">
                                <div class="flex items-center shrink-0">
                                    {{-- Step card (clickable) --}}
                                    <button type="button"
                                            @click="form.part_process_id = (form.part_process_id == step.id ? '' : step.id)"
                                            class="flex flex-col items-center rounded-xl border-2 px-2.5 py-2.5 text-center transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-indigo-400 group"
                                            style="min-width:86px"
                                            :class="form.part_process_id == step.id
                                                ? 'border-indigo-500 bg-indigo-600 text-white shadow-lg shadow-indigo-100'
                                                : 'border-gray-200 bg-white text-gray-600 hover:border-indigo-300 hover:shadow-sm'">

                                        {{-- Step number badge --}}
                                        <span class="text-[9px] font-bold mb-1 px-1.5 py-0.5 rounded-full"
                                              :class="form.part_process_id == step.id ? 'bg-indigo-500 text-indigo-100' : 'bg-gray-100 text-gray-500'"
                                              x-text="'STEP ' + step.sequence_order"></span>

                                        {{-- Process name --}}
                                        <p class="text-[11px] font-bold leading-tight w-full truncate"
                                           x-text="step.process_master?.name || '—'"></p>

                                        {{-- Cycle time --}}
                                        <p class="mt-1 text-[12px] font-extrabold"
                                           :class="form.part_process_id == step.id ? 'text-indigo-200' : 'text-indigo-600'"
                                           x-text="effectiveCycleTime(step).toFixed(1) + ' min'"></p>

                                        {{-- In/Out badge + checkmark --}}
                                        <div class="mt-1.5 flex items-center gap-1 justify-center">
                                            <span class="rounded-full px-1.5 py-0.5 text-[8px] font-bold leading-none"
                                                  :class="form.part_process_id == step.id
                                                      ? (step.process_type === 'outside' ? 'bg-orange-400/80 text-white' : 'bg-emerald-400/80 text-white')
                                                      : (step.process_type === 'outside' ? 'bg-orange-100 text-orange-600' : 'bg-emerald-100 text-emerald-600')"
                                                  x-text="step.process_type === 'outside' ? 'Outside' : 'In-house'"></span>
                                            <svg x-show="form.part_process_id == step.id"
                                                 class="h-3.5 w-3.5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                        </div>
                                    </button>

                                    {{-- Arrow between steps --}}
                                    <div class="flex items-center px-1 text-gray-300 shrink-0">
                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                </div>
                            </template>

                            {{-- Output node --}}
                            <div class="flex flex-col items-center rounded-xl border-2 border-green-300 bg-green-50 px-2.5 py-2.5 text-center shrink-0"
                                 style="min-width:76px">
                                <span class="text-[9px] font-bold text-green-500 mb-1 px-1.5 py-0.5 bg-green-100 rounded-full">OUTPUT</span>
                                <p class="text-[14px] font-extrabold text-green-700 leading-none">
                                    <span x-text="form.planned_qty || '?'"></span>
                                </p>
                                <p class="text-[10px] text-green-600 mt-0.5">pcs</p>
                                <p class="mt-1 text-[10px] text-green-500 font-medium"
                                   x-text="totalCycleTime.toFixed(1) + ' min/pc'"></p>
                            </div>
                        </div>

                        {{-- Summary bar --}}
                        <div class="grid grid-cols-3 divide-x divide-gray-100 border-t border-gray-100 bg-white/60">
                            <div class="px-3 py-2.5 text-center">
                                <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400">Total Cycle</p>
                                <p class="mt-0.5 text-sm font-extrabold text-gray-800"
                                   x-text="totalCycleTime.toFixed(2) + ' min'"></p>
                                <p class="text-[9px] text-gray-400">per unit</p>
                            </div>
                            <div class="px-3 py-2.5 text-center">
                                <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400">Shift Target</p>
                                <p class="mt-0.5 text-sm font-extrabold"
                                   :class="shiftTarget ? 'text-indigo-700' : 'text-gray-300'"
                                   x-text="shiftTarget ? shiftTarget.target + ' units' : '—'"></p>
                                <p class="text-[9px] text-gray-400"
                                   x-text="shiftTarget ? '@ 85% OEE · ' + shiftTarget.shiftName : 'select shift'"></p>
                            </div>
                            <div class="px-3 py-2.5 text-center">
                                <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400">Est. Duration</p>
                                <p class="mt-0.5 text-sm font-extrabold"
                                   :class="estimatedDuration ? 'text-gray-800' : 'text-gray-300'"
                                   x-text="estimatedDuration || '—'"></p>
                                <p class="text-[9px] text-gray-400"
                                   x-text="(form.planned_qty || '?') + ' pcs'"></p>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Notes --}}
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">
                        Notes <span class="text-gray-300 font-normal">(optional)</span>
                    </label>
                    <textarea x-model="form.notes" rows="2" placeholder="Additional notes…"
                              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300 resize-none"></textarea>
                </div>

            </div>{{-- /form fields --}}

            {{-- ══════════════════════════════════════════════
                 RECORD ACTUALS — Edit mode, in_progress/scheduled
            ══════════════════════════════════════════════ --}}
            <template x-if="modalMode === 'edit' && editPlan && ['in_progress','scheduled'].includes(editPlan.status)">
                <div class="border-t border-emerald-100 bg-emerald-50/50 px-6 py-4">
                    <h4 class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-widest text-emerald-700 mb-3">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Record Actual Output
                    </h4>

                    {{-- Running totals --}}
                    <div class="grid grid-cols-3 gap-2 mb-3">
                        <div class="rounded-lg bg-white border border-emerald-100 px-3 py-2 text-center">
                            <p class="text-sm font-bold text-emerald-700"
                               x-text="Number(editPlan.good_qty_sum || 0).toLocaleString()"></p>
                            <p class="text-[10px] text-gray-400 mt-0.5">Good Today</p>
                        </div>
                        <div class="rounded-lg bg-white border border-red-100 px-3 py-2 text-center">
                            <p class="text-sm font-bold text-red-500"
                               x-text="Number(Math.max(0,(editPlan.actual_qty_sum||0)-(editPlan.good_qty_sum||0))).toLocaleString()"></p>
                            <p class="text-[10px] text-gray-400 mt-0.5">Rejects</p>
                        </div>
                        <div class="rounded-lg bg-white border border-indigo-100 px-3 py-2 text-center">
                            <p class="text-sm font-bold text-indigo-700"
                               x-text="editPlan.planned_qty > 0
                                   ? Math.round((editPlan.good_qty_sum||0) / editPlan.planned_qty * 100) + '%'
                                   : '—'"></p>
                            <p class="text-[10px] text-gray-400 mt-0.5">Attainment</p>
                        </div>
                    </div>

                    {{-- Attainment progress bar --}}
                    <div class="mb-3">
                        <div class="h-2 w-full rounded-full bg-gray-200 overflow-hidden">
                            <div class="h-2 rounded-full transition-all bg-emerald-500"
                                 :style="`width:${Math.min(100,Math.round((editPlan.good_qty_sum||0)/(editPlan.planned_qty||1)*100))}%`"></div>
                        </div>
                    </div>

                    {{-- Input row --}}
                    <div class="grid grid-cols-2 gap-2 mb-2">
                        <div>
                            <label class="block text-[10px] font-medium text-gray-600 mb-1">
                                Good Parts <span class="text-red-400">*</span>
                            </label>
                            <input type="number" x-model.number="actualsForm.actual_qty" min="0"
                                   placeholder="0"
                                   class="w-full rounded-lg border border-gray-200 px-2.5 py-1.5 text-sm
                                          focus:border-emerald-400 focus:outline-none focus:ring-1 focus:ring-emerald-300">
                        </div>
                        <div>
                            <label class="block text-[10px] font-medium text-gray-600 mb-1">Defects / Rejects</label>
                            <input type="number" x-model.number="actualsForm.defect_qty" min="0"
                                   placeholder="0"
                                   class="w-full rounded-lg border border-gray-200 px-2.5 py-1.5 text-sm
                                          focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-200">
                        </div>
                    </div>
                    <input type="text" x-model="actualsForm.notes" placeholder="Notes (optional)"
                           class="w-full rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs mb-2
                                  focus:border-gray-400 focus:outline-none resize-none">
                    <template x-if="actualsError">
                        <p class="text-[10px] text-red-600 mb-1.5" x-text="actualsError"></p>
                    </template>
                    <button @click="saveActuals()" :disabled="savingActuals"
                            class="w-full rounded-lg bg-emerald-600 py-2 text-xs font-semibold text-white
                                   hover:bg-emerald-700 disabled:opacity-60 transition-colors flex items-center justify-center gap-1.5">
                        <svg x-show="savingActuals" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span x-text="savingActuals ? 'Saving…' : 'Save Output'"></span>
                    </button>
                </div>
            </template>

        </div>{{-- /scrollable content --}}

        {{-- ── Footer (always visible) ─────────────────── --}}
        <div class="flex items-center justify-between px-6 py-3.5 bg-gray-50/80 border-t border-gray-100 flex-shrink-0">
            <div>
                <template x-if="modalMode === 'edit'">
                    <button @click="deletePlan()" :disabled="deleting"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium text-red-500 hover:bg-red-50 hover:text-red-700 disabled:opacity-50 transition-colors">
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
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-60 transition-colors flex items-center gap-1.5">
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

    {{-- Factory selector (only when multiple factories exist) --}}
    @if($hasMultiFactory)
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
    <div class="h-5 w-px bg-gray-200"></div>
    @endif

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
    <div class="ml-auto flex items-center gap-3">
        {{-- Legend --}}
        <div class="hidden xl:flex items-center gap-3 text-xs text-gray-500">
            <span class="flex items-center gap-1.5"><span class="status-dot bg-gray-400"></span>Draft</span>
            <span class="flex items-center gap-1.5"><span class="status-dot bg-blue-500"></span>Scheduled</span>
            <span class="flex items-center gap-1.5"><span class="status-dot bg-amber-500"></span>In Progress</span>
            <span class="flex items-center gap-1.5"><span class="status-dot bg-green-500"></span>Completed</span>
        </div>

        {{-- Workload toggle --}}
        <button @click="showWorkload = !showWorkload"
                :class="showWorkload ? 'bg-violet-600 text-white border-violet-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'"
                class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-2 text-sm font-medium transition-colors">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            Workload
        </button>

        {{-- Machine Load toggle --}}
        <button @click="toggleLoadChart()"
                :class="showLoadChart ? 'bg-teal-600 text-white border-teal-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'"
                class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-2 text-sm font-medium transition-colors">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
            </svg>
            Load Chart
        </button>

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
     PROCESS WORKLOAD PANEL (collapsible)
     Shows: Process → Machines running it → daily qty totals
══════════════════════════════════════════════════════════ --}}
<div x-show="showWorkload" x-cloak
     class="shrink-0 border-b border-violet-100 bg-gradient-to-r from-violet-50 to-white overflow-x-auto">
    <div class="px-5 py-3">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-xs font-bold text-violet-700 uppercase tracking-widest flex items-center gap-1.5">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                Process Workload — <span x-text="weekLabel"></span>
            </h3>
            <span class="text-[10px] text-violet-400">Total planned pieces per process per day (excludes cancelled)</span>
        </div>

        <template x-if="processWorkload.length === 0">
            <p class="text-sm text-gray-400 py-2">No plans with assigned process steps found for this week.</p>
        </template>

        <template x-if="processWorkload.length > 0">
            <table class="w-full text-xs border-separate" style="border-spacing:0">
                <thead>
                    <tr>
                        <th class="text-left py-1.5 pr-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest whitespace-nowrap" style="min-width:160px">
                            Process / Machine
                        </th>
                        <template x-for="day in weekDays" :key="day.date">
                            <th class="text-center py-1.5 px-2 text-[10px] font-bold uppercase tracking-widest whitespace-nowrap"
                                :class="day.isToday ? 'text-indigo-600' : 'text-gray-400'"
                                style="min-width:72px">
                                <span x-text="day.label"></span><br>
                                <span class="font-extrabold text-sm" x-text="day.dayNum"></span>
                            </th>
                        </template>
                        <th class="text-center py-1.5 px-2 text-[10px] font-bold text-gray-400 uppercase tracking-widest">
                            Total
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(proc, pi) in processWorkload" :key="proc.name">
                        {{-- Process header row --}}
                        <tr class="border-t border-violet-100">
                            <td class="py-2 pr-4 font-bold text-violet-700 bg-violet-50/60" style="border-radius:6px 0 0 0">
                                <div class="flex items-center gap-1.5">
                                    <span class="inline-block h-2 w-2 rounded-full bg-violet-400 flex-shrink-0"></span>
                                    <span x-text="proc.name"></span>
                                </div>
                                <span class="ml-3.5 text-[10px] text-violet-400 font-normal"
                                      x-text="proc.machineCount + ' machine' + (proc.machineCount !== 1 ? 's' : '')"></span>
                            </td>
                            <template x-for="day in weekDays" :key="day.date">
                                <td class="py-2 px-2 text-center bg-violet-50/40 font-bold"
                                    :class="proc.byDay[day.date] ? 'text-violet-700' : 'text-gray-200'">
                                    <span x-show="proc.byDay[day.date]" x-text="proc.byDay[day.date]?.toLocaleString()"></span>
                                    <span x-show="!proc.byDay[day.date]" class="text-gray-200">—</span>
                                </td>
                            </template>
                            <td class="py-2 px-2 text-center bg-violet-100 font-extrabold text-violet-800 rounded-r"
                                x-text="proc.weekTotal.toLocaleString()"></td>
                        </tr>
                        {{-- Machine sub-rows --}}
                        <template x-for="mach in proc.machines" :key="mach.machineId">
                            <tr>
                                <td class="py-1 pr-4 pl-5 text-gray-500">
                                    <div class="flex items-center gap-1">
                                        <svg class="h-2.5 w-2.5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                        <span x-text="mach.machineName" class="font-medium text-gray-600"></span>
                                        <span class="text-gray-300 text-[9px]" x-text="mach.machineCode"></span>
                                    </div>
                                </td>
                                <template x-for="day in weekDays" :key="day.date">
                                    <td class="py-1 px-2 text-center"
                                        :class="day.isToday ? 'bg-indigo-50/30' : ''">
                                        <template x-if="mach.byDay[day.date]">
                                            <div>
                                                <span class="font-semibold text-gray-700" x-text="mach.byDay[day.date].qty.toLocaleString()"></span>
                                                <span class="text-[9px] text-gray-400 ml-0.5">pcs</span>
                                                <div class="text-[8px] mt-0.5"
                                                     :class="{
                                                        'text-gray-400': mach.byDay[day.date].status === 'draft',
                                                        'text-blue-500': mach.byDay[day.date].status === 'scheduled',
                                                        'text-amber-500': mach.byDay[day.date].status === 'in_progress',
                                                        'text-green-500': mach.byDay[day.date].status === 'completed'
                                                     }"
                                                     x-text="mach.byDay[day.date].statusLabel"></div>
                                            </div>
                                        </template>
                                        <template x-if="!mach.byDay[day.date]">
                                            <span class="text-gray-200">—</span>
                                        </template>
                                    </td>
                                </template>
                                <td class="py-1 px-2 text-center text-gray-600 font-semibold"
                                    x-text="mach.weekTotal.toLocaleString()"></td>
                            </tr>
                        </template>
                    </template>
                </tbody>
            </table>
        </template>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════
     MACHINE LOAD CHART (collapsible)
     Shows daily utilisation % per machine for the current week.
     Color coding: green <80 %, amber 80–99 %, red ≥100 %
══════════════════════════════════════════════════════════ --}}
<div x-show="showLoadChart" x-cloak
     class="shrink-0 border-b border-teal-100 bg-gradient-to-r from-teal-50 to-white overflow-x-auto">
    <div class="px-5 py-3">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-xs font-bold text-teal-700 uppercase tracking-widest flex items-center gap-1.5">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                Machine Load — <span x-text="weekLabel"></span>
            </h3>
            <div class="flex items-center gap-3 text-[10px] text-gray-400">
                <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded bg-green-100 border border-green-300"></span> &lt;80%</span>
                <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded bg-amber-100 border border-amber-300"></span> 80–99%</span>
                <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded bg-red-100 border border-red-300"></span> ≥100% overload</span>
                <span x-show="loadingChart" class="text-teal-500">Loading…</span>
            </div>
        </div>

        <template x-if="!loadingChart && machineLoad.machines.length === 0">
            <p class="text-sm text-gray-400 py-2">No planned production for this week.</p>
        </template>

        <template x-if="machineLoad.machines && machineLoad.machines.length > 0">
            <table class="w-full text-xs border-separate" style="border-spacing:0">
                <thead>
                    <tr>
                        <th class="sticky left-0 bg-teal-50 border border-gray-200 px-3 py-1.5 text-left font-semibold text-gray-500 w-40">Machine</th>
                        <template x-for="day in weekDays" :key="day.date">
                            <th class="border border-gray-200 px-2 py-1.5 text-center font-semibold text-gray-500 min-w-[110px]"
                                :class="day.isToday ? 'bg-teal-100' : 'bg-teal-50'">
                                <div x-text="day.label"></div>
                                <div class="text-[10px] font-normal text-gray-400" x-text="day.date.slice(5)"></div>
                            </th>
                        </template>
                        <th class="bg-teal-100 border border-gray-200 px-2 py-1.5 text-center font-semibold text-teal-700 min-w-[80px]">Week Avg</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="mach in machineLoad.machines" :key="mach.id">
                        <tr class="hover:bg-teal-50/30">
                            <td class="sticky left-0 bg-white border border-gray-100 px-3 py-2 font-semibold text-gray-700 text-xs align-top">
                                <div x-text="mach.name"></div>
                                <div class="text-[10px] font-normal text-gray-400 mt-0.5"
                                     x-text="'Avg: ' + Math.round(mach.week_avg) + '%'"></div>
                            </td>
                            <template x-for="day in weekDays" :key="day.date">
                                <td class="border border-gray-100 px-1.5 py-1 align-top">
                                    <template x-if="mach.days[day.date] && mach.days[day.date].total_qty > 0">
                                        <div class="space-y-0.5">
                                            <template x-for="shift in machineLoad.shifts" :key="shift.id">
                                                <template x-if="mach.days[day.date].by_shift[shift.id] && mach.days[day.date].by_shift[shift.id].qty > 0">
                                                    <div class="rounded px-1.5 py-0.5 text-[10px] leading-tight"
                                                         :class="loadCellClass(mach.days[day.date].by_shift[shift.id].pct)">
                                                        <div class="flex items-center justify-between gap-1">
                                                            <span class="truncate font-medium" x-text="shift.name"></span>
                                                            <span class="font-bold shrink-0" x-text="Math.round(mach.days[day.date].by_shift[shift.id].pct) + '%'"></span>
                                                        </div>
                                                        <div class="text-[9px] opacity-70" x-text="mach.days[day.date].by_shift[shift.id].qty + ' pcs'"></div>
                                                    </div>
                                                </template>
                                            </template>
                                            {{-- Day total if >1 shift has load --}}
                                            <template x-if="machineLoad.shifts.filter(s => mach.days[day.date].by_shift[s.id]?.qty > 0).length > 1">
                                                <div class="border-t border-gray-200 mt-0.5 pt-0.5 text-[9px] text-center font-semibold text-gray-500"
                                                     x-text="'Total: ' + Math.round(mach.days[day.date].total_pct) + '% · ' + mach.days[day.date].total_qty + ' pcs'">
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                    <template x-if="!mach.days[day.date] || mach.days[day.date].total_qty === 0">
                                        <span class="text-gray-300 text-center block">—</span>
                                    </template>
                                </td>
                            </template>
                            {{-- Week avg --}}
                            <td class="border border-gray-100 px-2 py-2 text-center font-bold text-sm align-middle"
                                :class="loadCellClass(mach.week_avg)">
                                <span x-text="Math.round(mach.week_avg) + '%'"></span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </template>
    </div>
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
                    <th :class="[
                            'border-b-2 border-r border-gray-200 px-3 py-3 text-center min-w-[150px]',
                            day.isWeekOff || day.isHoliday ? 'bg-red-50' :
                            day.isToday ? 'bg-indigo-50' : 'bg-gray-50'
                        ]">
                        <p class="text-[10px] font-bold uppercase tracking-widest"
                           :class="day.isWeekOff || day.isHoliday ? 'text-red-400' : 'text-gray-400'"
                           x-text="day.label"></p>
                        <p class="mt-0.5 text-xl font-extrabold leading-none"
                           :class="day.isWeekOff || day.isHoliday ? 'text-red-500' : day.isToday ? 'text-indigo-600' : 'text-gray-700'"
                           x-text="day.dayNum"></p>
                        <p class="text-[10px] mt-0.5"
                           :class="day.isWeekOff || day.isHoliday ? 'text-red-400' : 'text-gray-400'"
                           x-text="day.monthLabel"></p>
                        <div x-show="day.isToday && !day.isWeekOff && !day.isHoliday" class="mx-auto mt-1 h-1 w-5 rounded-full bg-indigo-400"></div>
                        {{-- Holiday name badge --}}
                        <div x-show="day.isHoliday" class="mx-auto mt-1 text-[9px] font-medium text-red-500 leading-tight truncate max-w-[100px]" x-text="day.holidayName"></div>
                        {{-- Week-off badge --}}
                        <div x-show="day.isWeekOff && !day.isHoliday" class="mx-auto mt-1 text-[9px] font-medium text-red-400">Week Off</div>
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

            {{-- One <tr> per machine (paginated) --}}
            <template x-for="machine in visibleMachines" :key="machine.id">
                <tr class="group border-b border-gray-100">

                    {{-- Machine label (sticky left) --}}
                    <td class="machine-col bg-white border-r border-gray-100 px-3 py-3 align-top group-hover:bg-slate-50 transition-colors w-44 min-w-[176px]">
                        <p class="text-sm font-bold text-gray-800 leading-tight truncate" x-text="machine.name"></p>
                        <p class="text-[11px] text-gray-400 mt-0.5 truncate"
                           x-text="machine.code + (machine.type ? ' · ' + machine.type : '')"></p>

                        {{-- Weekly load bar --}}
                        <template x-if="machineLoadMap[machine.id]">
                            <div class="mt-1.5">
                                <div class="flex items-center justify-between mb-0.5">
                                    <span class="text-[10px] text-gray-400">Week load</span>
                                    <span class="text-[10px] font-bold"
                                          :class="machineLoadMap[machine.id].text"
                                          x-text="Math.round(machineLoadMap[machine.id].week_avg) + '%'"></span>
                                </div>
                                <div class="h-1.5 w-full rounded-full bg-gray-100 overflow-hidden">
                                    <div class="h-full rounded-full transition-all duration-500"
                                         :class="machineLoadMap[machine.id].color"
                                         :style="'width:' + machineLoadMap[machine.id].bar_pct + '%'"></div>
                                </div>
                            </div>
                        </template>
                        <template x-if="!machineLoadMap[machine.id]">
                            <div class="mt-1.5">
                                <div class="flex items-center justify-between mb-0.5">
                                    <span class="text-[10px] text-gray-400">Week load</span>
                                    <span class="text-[10px] text-gray-300">0%</span>
                                </div>
                                <div class="h-1.5 w-full rounded-full bg-gray-100"></div>
                            </div>
                        </template>
                    </td>

                    {{-- One <td> per day --}}
                    <template x-for="day in weekDays" :key="day.date">
                        <td :class="[
                                'border-r border-gray-100 p-2 align-top transition-colors',
                                day.isWeekOff || day.isHoliday
                                    ? 'bg-red-50/60'
                                    : day.isToday ? 'bg-indigo-50/30' : 'group-hover:bg-slate-50/30'
                            ]"
                            style="min-height: 90px;">
                            <div class="space-y-1.5">

                                {{-- One slot per shift --}}
                                <template x-for="slot in getSlots(machine.id, day.date)" :key="slot.shiftId">
                                    <div>

                                        {{-- ── Has plans: show all cards + compact add button ── --}}
                                        <template x-if="slot.plans.length > 0">
                                            <div class="space-y-1">
                                                <template x-for="plan in slot.plans" :key="plan.id">
                                                    <button
                                                        @click="openPlan(plan)"
                                                        :class="['plan-card w-full text-left rounded-xl border-2 px-2.5 pt-2 pb-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-400', planCardClass(plan.status)]"
                                                    >
                                                        <div class="flex items-center gap-1.5 mb-1.5">
                                                            <span :class="['status-dot', statusDotColor(plan.status)]"></span>
                                                            <span class="text-[9px] font-bold uppercase tracking-widest opacity-70"
                                                                  x-text="plan.status.replace('_',' ')"></span>
                                                        </div>
                                                        <p class="text-xs font-bold truncate leading-tight"
                                                           x-text="plan.part?.part_number || '—'"></p>
                                                        <p class="text-[10px] mt-0.5 font-medium truncate opacity-80"
                                                           x-show="plan.part_process?.process_master?.name"
                                                           x-text="'↳ ' + (plan.part_process?.process_master?.name || '')"></p>
                                                        <p class="text-[10px] mt-1 opacity-60 truncate">
                                                            <span x-text="Number(plan.planned_qty).toLocaleString()"></span> pcs
                                                            &middot; <span x-text="slot.shiftName"></span>
                                                        </p>
                                                        {{-- Good Qty / Attainment bar --}}
                                                        <template x-if="(plan.actual_qty_sum || 0) > 0">
                                                            <div class="mt-1.5">
                                                                <div class="flex items-center justify-between text-[9px] font-medium opacity-80 mb-0.5">
                                                                    <span>Good: <span x-text="Number(plan.good_qty_sum || 0).toLocaleString()"></span></span>
                                                                    <span x-text="Math.round((plan.good_qty_sum||0) / plan.planned_qty * 100) + '%'"></span>
                                                                </div>
                                                                <div class="h-1 w-full rounded-full bg-black/10 overflow-hidden">
                                                                    <div class="h-1 rounded-full transition-all"
                                                                         :class="Math.round((plan.good_qty_sum||0)/plan.planned_qty*100) >= 100 ? 'bg-green-500' : 'bg-emerald-400'"
                                                                         :style="`width:${Math.min(100,Math.round((plan.good_qty_sum||0)/plan.planned_qty*100))}%`"></div>
                                                                </div>
                                                            </div>
                                                        </template>
                                                    </button>
                                                </template>
                                                {{-- Compact "add another part" button --}}
                                                <button
                                                    @click="openCreate(machine.id, day.date, slot.shiftId)"
                                                    class="w-full rounded-lg border border-dashed border-gray-200 px-2 py-1 text-[10px] font-medium text-gray-300 hover:border-indigo-300 hover:text-indigo-500 hover:bg-indigo-50/60 transition-all flex items-center gap-1"
                                                >
                                                    <svg class="h-2.5 w-2.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                                                    </svg>
                                                    Add part
                                                </button>
                                            </div>
                                        </template>

                                        {{-- ── Empty slot: big dashed add button ── --}}
                                        <template x-if="slot.plans.length === 0">
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

{{-- Load more machines --}}
<template x-if="machinePageSize < machines.length">
    <div class="mt-4 text-center">
        <button @click="machinePageSize += 15"
                class="px-4 py-2 text-sm font-medium text-indigo-600 border border-indigo-300 rounded-lg hover:bg-indigo-50 transition-colors">
            Show more machines
            (<span x-text="machines.length - machinePageSize"></span> remaining)
        </button>
    </div>
</template>

</div>{{-- end x-data --}}
@endsection

@push('scripts')
<script>
function productionCalendar(apiToken, factoryId, factories, machines, shifts, parts, weekOffDays, holidays) {
    return {
        apiToken,
        currentFactoryId: factoryId,
        factories:   factories   || [],
        machines:    machines    || [],
        shifts:      shifts      || [],
        parts:       parts       || [],
        weekOffDays: weekOffDays || [],   // [0..6] — 0=Sunday
        holidays:    holidays    || [],   // [{date:'YYYY-MM-DD', name:'...'}, ...]
        machinePageSize: 15,

        plans:   [],
        loading: false,
        error:   null,
        weekStart: null,
        showWorkload: false,
        showLoadChart: false,
        machineLoad:   { machines: [], shifts: [] },
        loadingChart:  false,

        // Modal state
        showModal:  false,
        modalMode:  'create',
        saving:     false,
        deleting:   false,
        formError:  null,
        editPlan:   null,

        // Availability check state (create mode)
        planAvail: { checking: false, is_full: null, next_date: null, free_min: null },

        // Record Actuals state
        actualsForm:   { actual_qty: 0, defect_qty: 0, notes: '' },
        savingActuals: false,
        actualsError:  null,

        form: {
            machine_id:      '',
            part_id:         '',
            part_process_id: '',
            shift_id:        '',
            planned_date:    '',
            planned_qty:     1,
            status:          'draft',
            factory_id:      '',
            notes:           '',
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
            const LABELS      = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
            const MONTHS      = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            const today       = this.todayStr;
            const holidaySet  = new Set((this.holidays || []).map(h => h.date));
            const weekOffSet  = new Set((this.weekOffDays || []).map(Number));
            const days        = [];
            for (let i = 0; i < 7; i++) {
                const d         = new Date(this.weekStart.getTime() + i * 86400000);
                const ds        = this.fmtDate(d);
                const dow       = d.getDay();
                const hol       = holidaySet.has(ds) ? (this.holidays.find(h => h.date === ds)?.name || '') : null;
                days.push({
                    date:       ds,
                    label:      LABELS[dow],
                    dayNum:     d.getDate(),
                    monthLabel: MONTHS[d.getMonth()],
                    isToday:    ds === today,
                    isWeekOff:  weekOffSet.has(dow),
                    isHoliday:  !!hol,
                    holidayName: hol,
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

        get visibleMachines() {
            return this.machines.slice(0, this.machinePageSize);
        },

        // O(1) lookup: "machineId:date:shiftId" → array of plans
        // planned_date from Laravel API is ISO datetime ("2026-03-02T00:00:00.000000Z")
        // but weekDays uses "YYYY-MM-DD" — normalize to date-only for matching.
        // machine_id → { week_avg, color_class } — used by calendar machine column
        get machineLoadMap() {
            const map = {};
            for (const m of (this.machineLoad.machines || [])) {
                const pct = m.week_avg ?? 0;
                map[m.id] = {
                    week_avg: pct,
                    bar_pct:  Math.min(100, Math.round(pct)),
                    color:    pct >= 100 ? 'bg-red-500'
                            : pct >= 80  ? 'bg-amber-400'
                            : pct > 0    ? 'bg-green-500'
                            : 'bg-gray-200',
                    text:     pct >= 100 ? 'text-red-600'
                            : pct >= 80  ? 'text-amber-600'
                            : pct > 0    ? 'text-green-600'
                            : 'text-gray-400',
                };
            }
            return map;
        },

        get plansMap() {
            const m = {};
            for (const p of this.plans) {
                const d   = p.planned_date ? String(p.planned_date).substring(0, 10) : '';
                const key = `${p.machine_id}:${d}:${p.shift_id}`;
                if (!m[key]) m[key] = [];
                m[key].push(p);
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

        // ── Process flow getters ────────────────────────────

        get selectedPartProcesses() {
            return this.selectedPart?.processes || [];
        },

        get selectedProcess() {
            if (!this.form.part_process_id || !this.selectedPartProcesses.length) return null;
            return this.selectedPartProcesses.find(s => s.id == this.form.part_process_id) || null;
        },

        // ── Process Workload (groups plans by process → machine → day) ──
        // Excludes cancelled plans. Used by the Workload panel.
        get processWorkload() {
            const excluded = ['cancelled'];
            const active = this.plans.filter(p => !excluded.includes(p.status));

            // Accumulate: processName → machineId → date → { qty, status }
            const procMap = {};

            for (const plan of active) {
                const procName = plan.part_process?.process_master?.name || null;
                if (!procName) continue; // skip plans without process assigned

                const date      = plan.planned_date ? String(plan.planned_date).substring(0, 10) : '';
                const machId    = plan.machine_id;
                const machName  = plan.machine?.name  || '—';
                const machCode  = plan.machine?.code  || '';
                const qty       = plan.planned_qty || 0;
                const status    = plan.status;

                if (!procMap[procName]) procMap[procName] = { machines: {}, byDay: {}, weekTotal: 0 };
                if (!procMap[procName].machines[machId]) {
                    procMap[procName].machines[machId] = {
                        machineId: machId, machineName: machName, machineCode: machCode,
                        byDay: {}, weekTotal: 0,
                    };
                }

                // Accumulate by day for process total
                procMap[procName].byDay[date]  = (procMap[procName].byDay[date]  || 0) + qty;
                procMap[procName].weekTotal   += qty;

                // Accumulate by day for machine row (use dominant status)
                const machDay = procMap[procName].machines[machId].byDay[date];
                if (!machDay) {
                    procMap[procName].machines[machId].byDay[date] = {
                        qty, status,
                        statusLabel: status.replace('_', ' '),
                    };
                } else {
                    machDay.qty += qty;
                    // escalate status: in_progress > scheduled > draft > completed
                    const rank = { in_progress: 4, scheduled: 3, draft: 2, completed: 1 };
                    if ((rank[status] || 0) > (rank[machDay.status] || 0)) {
                        machDay.status      = status;
                        machDay.statusLabel = status.replace('_', ' ');
                    }
                }
                procMap[procName].machines[machId].weekTotal += qty;
            }

            // Convert to sorted array
            return Object.entries(procMap)
                .sort(([a], [b]) => a.localeCompare(b))
                .map(([name, data]) => ({
                    name,
                    byDay:        data.byDay,
                    weekTotal:    data.weekTotal,
                    machineCount: Object.keys(data.machines).length,
                    machines: Object.values(data.machines)
                        .sort((a, b) => a.machineName.localeCompare(b.machineName)),
                }));
        },

        get totalCycleTime() {
            const p = this.selectedPart;
            if (!p) return 0;
            const stored = parseFloat(p.total_cycle_time);
            if (stored > 0) return stored;
            return (p.processes || []).reduce((sum, s) => sum + this.effectiveCycleTime(s), 0);
        },

        get shiftTarget() {
            const shift = this.shifts.find(s => s.id == this.form.shift_id);
            const ct    = this.totalCycleTime;
            if (!shift || ct <= 0) return null;
            const effMin = Math.floor(shift.duration_min * 0.85);
            return {
                target:    Math.floor(effMin / ct),
                shiftName: shift.name,
            };
        },

        get estimatedDuration() {
            const ct  = this.totalCycleTime;
            const qty = parseInt(this.form.planned_qty) || 0;
            if (!ct || !qty) return '';
            const totalMin = Math.round(ct * qty);
            const h = Math.floor(totalMin / 60);
            const m = totalMin % 60;
            return h > 0 ? `${h}h ${m}m` : `${m}m`;
        },

        effectiveCycleTime(step) {
            const override = parseFloat(step.standard_cycle_time);
            if (!isNaN(override) && override > 0) return override;
            return parseFloat(step.process_master?.standard_time) || 0;
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
            this.loadMachineLoad();
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
            this.loadMachineLoad();
        },

        nextWeek() {
            this.weekStart = new Date(this.weekStart.getTime() + 7 * 86400000);
            this.loadPlans();
            this.loadMachineLoad();
        },

        goToToday() {
            this.weekStart = this.getMonday(new Date());
            this.loadPlans();
            this.loadMachineLoad();
        },

        switchFactory(id) {
            this.currentFactoryId = id ? parseInt(id) : null;
            this.loadPlans();
            this.loadMachineLoad();
        },

        toggleLoadChart() {
            this.showLoadChart = !this.showLoadChart;
            if (this.showLoadChart && this.machineLoad.machines.length === 0) {
                this.loadMachineLoad();
            }
        },

        async loadMachineLoad() {
            if (!this.weekStart) return;
            this.loadingChart = true;
            try {
                const from   = this.fmtDate(this.weekStart);
                const to     = this.fmtDate(new Date(this.weekStart.getTime() + 6 * 86400000));
                const params = new URLSearchParams({ from_date: from, to_date: to });
                if (this.currentFactoryId) params.append('factory_id', this.currentFactoryId);

                const res  = await fetch(`/api/v1/machine-load?${params}`, {
                    headers: { 'Authorization': `Bearer ${this.apiToken}`, 'Accept': 'application/json' },
                });
                const data = await res.json();
                if (res.ok) {
                    this.machineLoad = {
                        machines: data.machines ?? [],
                        shifts:   data.shifts   ?? [],
                    };
                } else {
                    this.machineLoad = { machines: [], shifts: [] };
                }
            } catch (e) {
                this.machineLoad = { machines: [], shifts: [] };
            } finally {
                this.loadingChart = false;
            }
        },

        loadCellClass(pct) {
            if (!pct || pct <= 0)  return 'bg-white';
            if (pct >= 100)        return 'bg-red-50 text-red-700';
            if (pct >= 80)         return 'bg-amber-50 text-amber-700';
            return 'bg-green-50 text-green-700';
        },

        machWeekAvg(mach) {
            return mach.week_avg ?? 0;
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

        // Returns array of { shiftId, shiftName, plans[] } for one cell
        getSlots(machineId, date) {
            return this.shifts.map(s => ({
                shiftId:   s.id,
                shiftName: s.name,
                plans:     this.plansMap[`${machineId}:${date}:${s.id}`] || [],
            }));
        },

        // ── Modal open ──────────────────────────────────────

        openCreate(machineId, date, shiftId) {
            this.modalMode  = 'create';
            this.editPlan   = null;
            this.formError  = null;
            this.planAvail  = { checking: false, is_full: null, next_date: null, free_min: null };
            this.form = {
                machine_id:      machineId || '',
                part_id:         '',
                part_process_id: '',
                shift_id:        shiftId   || '',
                planned_date:    date      || this.todayStr,
                planned_qty:     1,
                status:          'draft',
                factory_id:      this.currentFactoryId || '',
                notes:           '',
            };
            this.showModal = true;
        },

        openPlan(plan) {
            this.modalMode = 'edit';
            this.editPlan  = plan;
            this.formError = null;
            this.form = {
                machine_id:      plan.machine_id,
                part_id:         plan.part_id,
                part_process_id: plan.part_process_id || '',
                shift_id:        plan.shift_id,
                planned_date:    plan.planned_date,
                planned_qty:     plan.planned_qty,
                status:          plan.status,
                factory_id:      plan.factory_id || '',
                notes:           plan.notes || '',
            };
            this.actualsForm  = { actual_qty: 0, defect_qty: 0, notes: '' };
            this.actualsError = null;
            this.showModal = true;
        },

        // ── Record Actuals ───────────────────────────────────

        async saveActuals() {
            if (!this.editPlan) return;
            const goodParts = parseInt(this.actualsForm.actual_qty) || 0;
            const defects   = parseInt(this.actualsForm.defect_qty) || 0;
            if (goodParts < 0) { this.actualsError = 'Good parts must be ≥ 0.'; return; }
            if (defects > goodParts) { this.actualsError = 'Defects cannot exceed good parts.'; return; }

            this.savingActuals = true;
            this.actualsError  = null;
            try {
                const res = await fetch('/api/v1/production-actuals', {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${this.apiToken}`,
                        'Accept':        'application/json',
                        'Content-Type':  'application/json',
                    },
                    body: JSON.stringify({
                        production_plan_id: this.editPlan.id,
                        actual_qty:  goodParts,
                        defect_qty:  defects,
                        notes:       this.actualsForm.notes || null,
                        recorded_at: new Date().toISOString().slice(0, 19).replace('T', ' '),
                    }),
                });
                if (!res.ok) {
                    const err = await res.json().catch(() => ({}));
                    throw new Error(err.message || `Error ${res.status}`);
                }

                // Update local totals on editPlan (no full reload needed)
                this.editPlan.actual_qty_sum = (this.editPlan.actual_qty_sum || 0) + goodParts;
                this.editPlan.good_qty_sum   = (this.editPlan.good_qty_sum   || 0) + (goodParts - defects);

                // Sync into plans array so calendar card updates
                const idx = this.plans.findIndex(p => p.id === this.editPlan.id);
                if (idx >= 0) {
                    this.plans[idx].actual_qty_sum = this.editPlan.actual_qty_sum;
                    this.plans[idx].good_qty_sum   = this.editPlan.good_qty_sum;
                }

                this.actualsForm = { actual_qty: 0, defect_qty: 0, notes: '' };
            } catch (e) {
                this.actualsError = e.message;
            } finally {
                this.savingActuals = false;
            }
        },

        // ── Machine Availability ─────────────────────────────

        async checkPlanAvailability() {
            if (this.modalMode !== 'create') return;
            if (!this.form.machine_id || !this.form.shift_id || !this.form.planned_date) {
                this.planAvail = { checking: false, is_full: null, next_date: null, free_min: null };
                return;
            }
            this.planAvail = { checking: true, is_full: null, next_date: null, free_min: null };
            try {
                const params = new URLSearchParams({
                    machine_id: this.form.machine_id,
                    shift_id:   this.form.shift_id,
                    date:       this.form.planned_date,
                });
                const res  = await fetch(`/api/v1/machine-availability?${params}`, {
                    headers: { 'Authorization': `Bearer ${this.apiToken}`, 'Accept': 'application/json' },
                });
                const data = await res.json();
                this.planAvail = {
                    checking:  false,
                    is_full:   data.is_full ?? null,
                    next_date: data.next_available_date ?? null,
                    free_min:  data.free_min ?? null,
                };
            } catch {
                this.planAvail = { checking: false, is_full: null, next_date: null, free_min: null };
            }
        },

        // ── CRUD ────────────────────────────────────────────

        async savePlan() {
            if (!this.form.machine_id || !this.form.part_id ||
                !this.form.shift_id   || !this.form.planned_date || !this.form.planned_qty) {
                this.formError = 'Please fill in Machine, Part, Shift, Date and Planned Qty.';
                return;
            }
            if (this.selectedPartProcesses.length > 0 && !this.form.part_process_id) {
                this.formError = 'Please select the Process Step this machine will run.';
                return;
            }
            if (this.modalMode === 'create' && this.planAvail.is_full === true) {
                const next = this.planAvail.next_date ? ` Next available: ${this.planAvail.next_date}.` : '';
                this.formError = `Machine is fully allocated on this date.${next} Please choose another date or use the suggested date above.`;
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

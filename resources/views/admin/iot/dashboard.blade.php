@extends('admin.layouts.app')

@section('title', 'IoT Dashboard')

@push('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
    @keyframes pulse-ring {
        0%   { transform: scale(0.85); opacity: 1; }
        100% { transform: scale(1.7);  opacity: 0; }
    }
    @keyframes blink {
        0%, 100% { opacity: 1; }
        50%       { opacity: 0.3; }
    }
    .alarm-blink { animation: blink 0.8s ease-in-out infinite; }

    /* Status dot pulse for running machines */
    .status-ping::before {
        content: '';
        position: absolute;
        inset: -4px;
        border-radius: 50%;
        background: currentColor;
        animation: pulse-ring 1.5s ease-out infinite;
    }

    /* Gauge wrapper — forces the semi-circle to sit flush at the bottom */
    .gauge-wrap {
        position: relative;
        width: 100%;
        height: 100px;
    }
    .gauge-wrap canvas {
        position: absolute;
        inset: 0;
    }
    .gauge-label {
        position: absolute;
        bottom: 0;
        left: 0; right: 0;
        text-align: center;
        pointer-events: none;
    }
</style>
@endpush

@section('content')
<div
    x-data="iotDashboard('{{ $apiToken }}', {{ $factoryId ?? 'null' }}, {{ $factories->toJson() }})"
    x-init="init()"
    class="flex flex-col gap-6"
>

    {{-- ════════════════════════════════════════════════════════
         FULL-SCREEN MACHINE DETAIL OVERLAY
         Opens when a machine card is clicked.
    ════════════════════════════════════════════════════════ --}}
    <div
        x-show="detailOpen"
        x-cloak
        x-transition:enter="transition ease-out duration-250"
        x-transition:enter-start="opacity-0 translate-y-3"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex flex-col bg-slate-950 overflow-hidden"
    >

        {{-- ── Dark header bar ─────────────────────────────── --}}
        <div class="shrink-0 bg-slate-900 border-b border-slate-700/60 px-6 py-3.5 flex items-center gap-4">

            {{-- Back --}}
            <button
                @click="closeDetail()"
                class="inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white transition-colors"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back
            </button>

            <div class="h-6 w-px bg-slate-700"></div>

            {{-- Machine identity --}}
            <div class="flex-1 min-w-0">
                <h1 class="text-base font-bold text-white leading-tight truncate" x-text="selectedMachine?.name"></h1>
                <p class="text-xs text-slate-400 truncate"
                   x-text="(selectedMachine?.code || '') + (selectedMachine?.type ? ' · ' + selectedMachine?.type : '')"></p>
            </div>

            {{-- Status badge --}}
            <span
                :class="['text-xs font-bold tracking-widest px-3 py-1 rounded-full', statusBadgeDarkClass(selectedMachine?.iot_status)]"
                x-text="(selectedMachine?.iot_status || 'offline').toUpperCase()"
            ></span>

            {{-- Alarm blink --}}
            <template x-if="selectedMachine?.alarm_code > 0">
                <div class="flex items-center gap-1.5 text-red-400 text-xs font-semibold alarm-blink">
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    ALARM <span x-text="selectedMachine?.alarm_code"></span>
                </div>
            </template>

            <div class="h-6 w-px bg-slate-700"></div>

            {{-- Date picker --}}
            <input
                type="date"
                x-model="chartDate"
                @change="oeeDate = chartDate; loadOee(selectedMachine?.factory_id || currentFactoryId || null); loadChart(selectedMachine.id)"
                class="rounded-lg bg-slate-800 border border-slate-600 text-slate-200 text-xs px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            >

            {{-- Shift selector --}}
            <select
                x-model="selectedShiftId"
                @change="loadChart(selectedMachine.id)"
                class="rounded-lg bg-slate-800 border border-slate-600 text-slate-200 text-xs px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500 max-w-[200px] truncate"
            >
                <option value="">All Day (24h)</option>
                <template x-for="s in shifts" :key="s.id">
                    <option
                        :value="s.id"
                        x-text="s.name + ' (' + s.start_time.slice(0,5) + '–' + s.end_time.slice(0,5) + ')'"
                    ></option>
                </template>
            </select>

            {{-- CSV export --}}
            <a
                :href="exportUrl"
                class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 px-3 py-1.5 text-xs font-semibold text-white transition-colors"
            >
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Export CSV
            </a>
        </div>

        {{-- ── KPI strip ─────────────────────────────────────── --}}
        <div class="shrink-0 bg-slate-800/80 border-b border-slate-700/60 px-6 py-3">
            <div class="grid grid-cols-6 divide-x divide-slate-700/60">

                {{-- OEE --}}
                <div class="text-center px-4">
                    <p class="text-xs uppercase tracking-widest text-slate-400 mb-0.5">OEE</p>
                    <p class="text-3xl font-extrabold leading-none"
                       :class="gaugeTextClass(machineSelectedOee?.oee_pct)"
                       x-text="machineSelectedOee?.oee_pct !== null && machineSelectedOee?.oee_pct !== undefined ? machineSelectedOee.oee_pct + '%' : '—'">
                    </p>
                </div>

                {{-- Availability --}}
                <div class="text-center px-4">
                    <p class="text-xs uppercase tracking-widest text-slate-400 mb-0.5">Availability</p>
                    <p class="text-2xl font-bold leading-none"
                       :class="gaugeTextClass(machineSelectedOee?.availability_pct)"
                       x-text="machineSelectedOee?.availability_pct !== undefined ? machineSelectedOee.availability_pct + '%' : '—'">
                    </p>
                </div>

                {{-- Performance --}}
                <div class="text-center px-4">
                    <p class="text-xs uppercase tracking-widest text-slate-400 mb-0.5">Performance</p>
                    <p class="text-2xl font-bold leading-none"
                       :class="gaugeTextClass(machineSelectedOee?.performance_pct)"
                       x-text="machineSelectedOee?.performance_pct !== null && machineSelectedOee?.performance_pct !== undefined ? machineSelectedOee.performance_pct + '%' : '—'">
                    </p>
                </div>

                {{-- Quality --}}
                <div class="text-center px-4">
                    <p class="text-xs uppercase tracking-widest text-slate-400 mb-0.5">Quality</p>
                    <p class="text-2xl font-bold leading-none"
                       :class="gaugeTextClass(machineSelectedOee?.quality_pct)"
                       x-text="machineSelectedOee?.quality_pct !== undefined ? machineSelectedOee.quality_pct + '%' : '—'">
                    </p>
                </div>

                {{-- Parts produced (chart period) --}}
                <div class="text-center px-4">
                    <p class="text-xs uppercase tracking-widest text-slate-400 mb-0.5">Parts <span class="normal-case" x-text="selectedShiftObj ? '(' + selectedShiftObj.name + ')' : '(24h)'"></span></p>
                    <p class="text-2xl font-bold leading-none text-white"
                       x-text="machinePartsTotals.total.toLocaleString()">
                    </p>
                </div>

                {{-- Rejects --}}
                <div class="text-center px-4">
                    <p class="text-xs uppercase tracking-widest text-slate-400 mb-0.5">Rejects</p>
                    <p class="text-2xl font-bold leading-none"
                       :class="machinePartsTotals.rejects > 0 ? 'text-red-400' : 'text-slate-300'"
                       x-text="machinePartsTotals.rejects.toLocaleString()">
                    </p>
                </div>
            </div>
        </div>

        {{-- ── Time Analysis Strip ──────────────────────────── --}}
        {{-- State logic: RUN=cycle_state=1,no_alarm | IDLE=stopped,no_alarm | ALARM=alarm_code>0 --}}
        <div class="shrink-0 border-b border-slate-700/60 px-6 py-3 bg-slate-900/70">
            <div style="display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:0.75rem;">

                {{-- Run Time: cycle_state=1, alarm_code=0 --}}
                <div class="flex items-center gap-3 rounded-xl bg-slate-800/60 border border-slate-700/40 px-4 py-3">
                    <div class="shrink-0 flex h-9 w-9 items-center justify-center rounded-lg bg-green-500/15">
                        <svg class="h-5 w-5 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs uppercase tracking-widest text-slate-500 mb-0.5">Run Time</p>
                        <p class="text-xl font-bold tabular-nums leading-none text-green-400"
                           x-text="machineTimeStats ? machineTimeStats.run : '—'"></p>
                        <p class="text-xs text-slate-600 mt-0.5"
                           x-text="machineTimeStats ? machineTimeStats.runLabel : 'hr : min'"></p>
                    </div>
                </div>

                {{-- Idle Time: cycle_state=0, alarm_code=0 (any auto_mode) --}}
                <div class="flex items-center gap-3 rounded-xl bg-slate-800/60 border border-slate-700/40 px-4 py-3">
                    <div class="shrink-0 flex h-9 w-9 items-center justify-center rounded-lg bg-yellow-500/15">
                        <svg class="h-5 w-5 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs uppercase tracking-widest text-slate-500 mb-0.5">Idle Time</p>
                        <p class="text-xl font-bold tabular-nums leading-none text-yellow-400"
                           x-text="machineTimeStats ? machineTimeStats.idle : '—'"></p>
                        <p class="text-xs text-slate-600 mt-0.5"
                           x-text="machineTimeStats ? machineTimeStats.idleLabel : 'hr : min'"></p>
                    </div>
                </div>

                {{-- Alarm Time: alarm_code > 0 --}}
                <div class="flex items-center gap-3 rounded-xl bg-slate-800/60 border border-slate-700/40 px-4 py-3">
                    <div class="shrink-0 flex h-9 w-9 items-center justify-center rounded-lg bg-red-500/15">
                        <svg class="h-5 w-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs uppercase tracking-widest text-slate-500 mb-0.5">Alarm Time</p>
                        <p class="text-xl font-bold tabular-nums leading-none text-red-400"
                           x-text="machineTimeStats ? machineTimeStats.alarm : '—'"></p>
                        <p class="text-xs text-slate-600 mt-0.5"
                           x-text="machineTimeStats ? machineTimeStats.alarmLabel : 'hr : min'"></p>
                    </div>
                </div>

                {{-- Availability: (Run+Idle) / Total × 100 --}}
                <div class="flex items-center gap-3 rounded-xl bg-slate-800/60 border border-slate-700/40 px-4 py-3">
                    <div class="shrink-0 flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-500/15">
                        <svg class="h-5 w-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs uppercase tracking-widest text-slate-500 mb-0.5">Availability</p>
                        <p class="text-xl font-bold tabular-nums leading-none"
                           :class="(machineTimeStats?.availPct ?? 0) >= 85 ? 'text-emerald-400' : (machineTimeStats?.availPct ?? 0) >= 60 ? 'text-yellow-400' : 'text-red-400'"
                           x-text="machineTimeStats?.availPct !== null && machineTimeStats?.availPct !== undefined ? machineTimeStats.availPct + '%' : '—'"></p>
                        <p class="text-xs text-slate-600 mt-0.5">Run+Idle ÷ Total</p>
                    </div>
                </div>

                {{-- Spindle Utilization: Run / Total × 100 (actual cutting time fraction) --}}
                <div class="flex items-center gap-3 rounded-xl bg-slate-800/60 border border-slate-700/40 px-4 py-3">
                    <div class="shrink-0 flex h-9 w-9 items-center justify-center rounded-lg bg-violet-500/15">
                        <svg class="h-5 w-5 text-violet-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs uppercase tracking-widest text-slate-500 mb-0.5">Spindle Util</p>
                        <p class="text-xl font-bold tabular-nums leading-none"
                           :class="(machineTimeStats?.spindleUtilPct ?? 0) >= 70 ? 'text-violet-400' : (machineTimeStats?.spindleUtilPct ?? 0) >= 45 ? 'text-yellow-400' : 'text-red-400'"
                           x-text="machineTimeStats?.spindleUtilPct !== null && machineTimeStats?.spindleUtilPct !== undefined ? machineTimeStats.spindleUtilPct + '%' : '—'"></p>
                        <p class="text-xs text-slate-600 mt-0.5">Run ÷ Total</p>
                    </div>
                </div>

                {{-- Parts / Run Hr: parts produced per hour of actual running time --}}
                <div class="flex items-center gap-3 rounded-xl bg-slate-800/60 border border-slate-700/40 px-4 py-3">
                    <div class="shrink-0 flex h-9 w-9 items-center justify-center rounded-lg bg-blue-500/15">
                        <svg class="h-5 w-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs uppercase tracking-widest text-slate-500 mb-0.5">Parts/Run Hr</p>
                        <p class="text-xl font-bold tabular-nums leading-none text-blue-400"
                           x-text="machineTimeStats?.partsPerRunHr !== null && machineTimeStats?.partsPerRunHr !== undefined ? machineTimeStats.partsPerRunHr : '—'"></p>
                        <p class="text-xs text-slate-600 mt-0.5">Rate while running</p>
                    </div>
                </div>

            </div>
        </div>

        {{-- ── Scrollable content ────────────────────────────── --}}
        <div class="flex-1 overflow-y-auto p-6 space-y-5">

            {{-- ── Row 0: Machine State Timeline ────────────── --}}
            <div class="bg-slate-900 rounded-2xl border border-slate-700/50 p-5">

                {{-- Header --}}
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <h3 class="text-xs font-semibold uppercase tracking-widest text-slate-400">Machine State Timeline</h3>
                        <span class="text-xs text-slate-600"
                              x-text="timelineData ? timelineData.window_from + ' – ' + timelineData.window_to : ''"></span>
                    </div>
                    <div x-show="timelineLoading" class="flex items-center gap-1.5 text-xs text-slate-500">
                        <svg class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        Loading…
                    </div>
                </div>

                {{-- Content when data is available --}}
                <template x-if="timelineData && timelineData.segments.length > 0">
                    <div>
                        {{-- Summary pills --}}
                        <div class="flex flex-wrap gap-2 mb-4 text-[11px]">
                            <span class="flex items-center gap-1.5 rounded-full bg-green-900/30 border border-green-700/30 px-2.5 py-1 text-green-400 font-medium">
                                <span class="h-2 w-2 rounded-full bg-green-500 shrink-0"></span>
                                Running
                                <span class="font-bold tabular-nums" x-text="fmtMin(timelineData.summary_min.running)"></span>
                            </span>
                            <span class="flex items-center gap-1.5 rounded-full bg-yellow-900/30 border border-yellow-700/30 px-2.5 py-1 text-yellow-400 font-medium">
                                <span class="h-2 w-2 rounded-full bg-yellow-400 shrink-0"></span>
                                Idle
                                <span class="font-bold tabular-nums" x-text="fmtMin(timelineData.summary_min.idle)"></span>
                            </span>
                            <span class="flex items-center gap-1.5 rounded-full bg-red-900/30 border border-red-700/30 px-2.5 py-1 text-red-400 font-medium">
                                <span class="h-2 w-2 rounded-full bg-red-500 shrink-0"></span>
                                Alarm
                                <span class="font-bold tabular-nums" x-text="fmtMin(timelineData.summary_min.alarm)"></span>
                            </span>
                            <template x-if="timelineData.summary_min.offline > 0">
                                <span class="flex items-center gap-1.5 rounded-full bg-slate-800/60 border border-slate-700/40 px-2.5 py-1 text-slate-500 font-medium">
                                    <span class="h-2 w-2 rounded-full bg-slate-600 shrink-0"></span>
                                    Offline
                                    <span class="font-bold tabular-nums" x-text="fmtMin(timelineData.summary_min.offline)"></span>
                                </span>
                            </template>
                        </div>

                        {{-- ── Timeline bar ── --}}
                        <div class="relative">
                            {{-- Colored segment bar --}}
                            <div class="flex h-12 w-full rounded-xl overflow-hidden border border-slate-700/30 shadow-inner">
                                <template x-for="seg in timelineData.segments" :key="seg.from_min + ':' + seg.state">
                                    <div
                                        :style="`width: ${seg.duration_min / timelineData.total_min * 100}%; background-color: ${timelineSegColor(seg.state)};`"
                                        class="h-full relative group cursor-default transition-opacity hover:opacity-80"
                                        :title="`${seg.state.toUpperCase()}\n${seg.from_label} – ${seg.to_label}\n${seg.duration_min} min`"
                                    >
                                        {{-- Label inside wide segments (> 15% of width) --}}
                                        <template x-if="seg.duration_min / timelineData.total_min > 0.15">
                                            <span class="absolute inset-0 flex items-center justify-center text-[10px] font-bold text-white/80 uppercase tracking-wider select-none pointer-events-none drop-shadow">
                                                <span x-text="seg.state === 'in_progress' ? 'RUN' : seg.state.slice(0,3).toUpperCase()"></span>
                                            </span>
                                        </template>
                                    </div>
                                </template>
                            </div>

                            {{-- Time axis tick marks --}}
                            <div class="relative mt-1.5 h-5 select-none">
                                <template x-for="tick in timelineTicks" :key="tick.min">
                                    <span
                                        class="absolute top-0 text-[10px] text-slate-500 -translate-x-1/2 tabular-nums"
                                        :style="`left: ${tick.pct}%`"
                                        x-text="tick.label"
                                    ></span>
                                </template>
                                {{-- Tick lines --}}
                                <template x-for="tick in timelineTicks" :key="'l' + tick.min">
                                    <span
                                        class="absolute -top-0.5 h-1.5 w-px bg-slate-600"
                                        :style="`left: ${tick.pct}%`"
                                    ></span>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Empty state --}}
                <template x-if="!timelineLoading && (!timelineData || timelineData.segments.length === 0)">
                    <div class="flex items-center justify-center h-16 text-slate-600 text-sm">
                        No telemetry data for this period
                    </div>
                </template>
            </div>

            {{-- Row 1: OEE Shift Breakdown + Live Telemetry --}}
            <div class="grid grid-cols-3 gap-5">

                {{-- OEE Per-Shift breakdown (2/3 width) — ISO 22400 --}}
                <div class="col-span-2 bg-slate-900 rounded-2xl border border-slate-700/50 p-5">
                    <div class="flex items-center gap-3 mb-4">
                        <h3 class="text-xs font-semibold uppercase tracking-widest text-slate-400">Shift OEE Breakdown</h3>
                        <span class="rounded-full bg-indigo-500/15 border border-indigo-500/30 px-2 py-0.5 text-xs font-medium text-indigo-400">ISO 22400</span>
                        <span class="text-xs text-slate-600" x-text="oeeDate"></span>
                    </div>

                    {{-- No data notice --}}
                    <template x-if="machineOeeShifts.length === 0">
                        <div class="flex flex-col items-center justify-center py-8 text-slate-600">
                            <svg class="h-8 w-8 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <p class="text-sm">No OEE data for <span x-text="oeeDate"></span></p>
                            <p class="text-xs mt-1 text-slate-700">Run a production plan with cycle time to enable OEE.</p>
                        </div>
                    </template>

                    {{-- Shift breakdown table --}}
                    <template x-if="machineOeeShifts.length > 0">
                        <table class="w-full text-xs text-slate-300">
                            <thead>
                                <tr class="text-slate-500 uppercase text-left border-b border-slate-700/40">
                                    <th class="pb-2 pr-3">Shift</th>
                                    <th class="pb-2 pr-3 text-right">Parts</th>
                                    <th class="pb-2 pr-3 text-right">Rejects</th>
                                    <th class="pb-2 pr-3 text-center">Avail %</th>
                                    <th class="pb-2 pr-3 text-center">Perf %</th>
                                    <th class="pb-2 pr-3 text-center">Qual %</th>
                                    <th class="pb-2 text-center">OEE %</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="s in machineOeeShifts" :key="s.shift_id">
                                    <tr class="border-t border-slate-700/30 hover:bg-slate-800/40 transition-colors">
                                        <td class="py-2.5 pr-3 text-slate-300 font-medium" x-text="s.shift_name"></td>
                                        <td class="py-2.5 pr-3 text-right font-mono font-bold text-white" x-text="s.total_parts.toLocaleString()"></td>
                                        <td class="py-2.5 pr-3 text-right font-mono"
                                            :class="s.reject_parts > 0 ? 'text-red-400 font-semibold' : 'text-slate-600'"
                                            x-text="s.reject_parts > 0 ? s.reject_parts : '—'"></td>
                                        <td class="py-2.5 pr-3 text-center">
                                            <span class="inline-block rounded-full px-2 py-0.5 text-xs font-semibold"
                                                  :class="s.availability_pct >= 85 ? 'bg-green-500/20 text-green-400' : s.availability_pct >= 60 ? 'bg-yellow-500/20 text-yellow-400' : 'bg-red-500/20 text-red-400'"
                                                  x-text="s.availability_pct + '%'"></span>
                                        </td>
                                        <td class="py-2.5 pr-3 text-center">
                                            <span x-show="s.performance_pct !== null"
                                                  class="inline-block rounded-full px-2 py-0.5 text-xs font-semibold"
                                                  :class="s.performance_pct >= 85 ? 'bg-green-500/20 text-green-400' : s.performance_pct >= 60 ? 'bg-yellow-500/20 text-yellow-400' : 'bg-red-500/20 text-red-400'"
                                                  x-text="s.performance_pct + '%'"></span>
                                            <span x-show="s.performance_pct === null" class="text-slate-700 text-xs">No plan</span>
                                        </td>
                                        <td class="py-2.5 pr-3 text-center">
                                            <span class="inline-block rounded-full px-2 py-0.5 text-xs font-semibold"
                                                  :class="s.quality_pct >= 85 ? 'bg-green-500/20 text-green-400' : s.quality_pct >= 60 ? 'bg-yellow-500/20 text-yellow-400' : 'bg-red-500/20 text-red-400'"
                                                  x-text="s.quality_pct + '%'"></span>
                                        </td>
                                        <td class="py-2.5 text-center">
                                            <span x-show="s.oee_pct !== null" :class="oeeBadgeDarkClass(s.oee_pct)" x-text="s.oee_pct + '%'"></span>
                                            <span x-show="s.oee_pct === null" class="text-slate-600 text-xs">—</span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </template>

                    {{-- ISO 22400 formula reference --}}
                    <div class="mt-4 pt-3 border-t border-slate-800 flex flex-wrap gap-x-4 gap-y-1 text-[10px] text-slate-600">
                        <span><span class="text-slate-500 font-semibold">Avail</span> = (Planned − Alarm) ÷ Planned</span>
                        <span><span class="text-slate-500 font-semibold">Perf</span> = (Parts × Cycle) ÷ Avail Time</span>
                        <span><span class="text-slate-500 font-semibold">Qual</span> = Good ÷ Total</span>
                        <span><span class="text-slate-500 font-semibold">OEE</span> = A × P × Q — ISO 22400</span>
                    </div>
                </div>

                {{-- Live Telemetry (1/3 width) --}}
                <div class="bg-slate-900 rounded-2xl border border-slate-700/50 p-5 flex flex-col gap-4">
                    <h3 class="text-xs font-semibold uppercase tracking-widest text-slate-400">Live Telemetry</h3>

                    {{-- Status (large) --}}
                    <div class="flex items-center gap-3 p-3 rounded-xl" :class="statusBgDarkClass(selectedMachine?.iot_status)">
                        <div class="relative h-3.5 w-3.5 shrink-0">
                            <span :class="['absolute inset-0 rounded-full', statusDotClass(selectedMachine?.iot_status)]"></span>
                            <template x-if="selectedMachine?.iot_status === 'running'">
                                <span :class="['absolute inset-0 rounded-full animate-ping opacity-75', statusDotClass(selectedMachine?.iot_status)]"></span>
                            </template>
                        </div>
                        <span class="font-bold text-sm tracking-wide uppercase"
                              :class="statusTextDarkClass(selectedMachine?.iot_status)"
                              x-text="selectedMachine?.iot_status || 'offline'">
                        </span>
                    </div>

                    {{-- Telemetry rows --}}
                    <dl class="space-y-2.5 text-sm">
                        <div class="flex justify-between items-center border-b border-slate-800 pb-2.5">
                            <dt class="text-slate-500">Cycle State</dt>
                            <dd class="font-semibold" :class="selectedMachine?.cycle_state ? 'text-green-400' : 'text-slate-400'"
                                x-text="selectedMachine?.cycle_state ? 'Running' : 'Stopped'"></dd>
                        </div>
                        <div class="flex justify-between items-center border-b border-slate-800 pb-2.5">
                            <dt class="text-slate-500">Auto Mode</dt>
                            <dd class="font-semibold" :class="selectedMachine?.auto_mode ? 'text-blue-400' : 'text-slate-400'"
                                x-text="selectedMachine?.auto_mode ? 'On' : 'Off'"></dd>
                        </div>
                        <div class="flex justify-between items-center border-b border-slate-800 pb-2.5">
                            <dt class="text-slate-500">Alarm Code</dt>
                            <dd class="font-semibold" :class="selectedMachine?.alarm_code > 0 ? 'text-red-400 alarm-blink' : 'text-slate-400'"
                                x-text="selectedMachine?.alarm_code > 0 ? '#' + selectedMachine.alarm_code : 'None'"></dd>
                        </div>
                        <div class="flex justify-between items-center border-b border-slate-800 pb-2.5">
                            <dt class="text-slate-500">Last Data</dt>
                            <dd class="text-slate-300 tabular-nums"
                                x-text="selectedMachine?.last_seen ? timeAgo(selectedMachine.last_seen) : 'No data'"></dd>
                        </div>
                        <div class="flex justify-between items-center border-b border-slate-800 pb-2.5">
                            <dt class="text-slate-500">Part Count</dt>
                            <dd class="font-bold text-white tabular-nums text-base"
                                x-text="(selectedMachine?.part_count || 0).toLocaleString()"></dd>
                        </div>
                        <div class="flex justify-between items-center border-b border-slate-800 pb-2.5">
                            <dt class="text-slate-500">Rejects</dt>
                            <dd class="font-bold tabular-nums text-base"
                                :class="(selectedMachine?.part_reject || 0) > 0 ? 'text-red-400' : 'text-slate-400'"
                                x-text="(selectedMachine?.part_reject || 0).toLocaleString()"></dd>
                        </div>
                        <div class="flex justify-between items-center border-b border-slate-800 pb-2.5">
                            <dt class="text-slate-500">Slave / PLC</dt>
                            <dd class="text-slate-300 text-xs font-mono"
                                x-text="selectedMachine?.slave_name || '—'"></dd>
                        </div>
                        <div class="flex justify-between items-center">
                            <dt class="text-slate-500">Machine Code</dt>
                            <dd class="text-slate-300 font-mono text-xs" x-text="selectedMachine?.code"></dd>
                        </div>
                    </dl>

                    {{-- Last refresh indicator --}}
                    <p class="mt-auto text-xs text-slate-600 text-center">
                        Auto-refresh every 5s
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-green-500 animate-pulse ml-1"></span>
                    </p>
                </div>
            </div>

            {{-- Row 2: Parts / Hour — full width --}}
            <div class="bg-slate-900 rounded-2xl border border-slate-700/50 p-5">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <h3 class="text-xs font-semibold uppercase tracking-widest text-slate-400">Production Rate — Parts / Hour</h3>
                        <span class="rounded-full bg-green-500/15 border border-green-500/30 px-2 py-0.5 text-xs font-medium text-green-400">ISO 22400</span>
                    </div>
                    <div x-show="chartLoading" class="flex items-center gap-1.5 text-xs text-slate-500">
                        <svg class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        Loading…
                    </div>
                </div>
                {{-- canvas always in DOM; x-show controls visibility --}}
                <div x-show="chartData && chartData.labels && chartData.labels.length > 0" style="height: 220px; position: relative;">
                    <canvas id="detail-parts"></canvas>
                </div>
                <div x-show="!chartLoading && (!chartData || !chartData.labels || chartData.labels.length === 0)"
                     class="flex flex-col items-center justify-center py-12 text-slate-600">
                    <svg class="h-10 w-10 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    <p class="text-sm" x-text="selectedShiftId ? 'No data for ' + (selectedShiftObj?.name || 'this shift') + ' on ' + chartDate : 'No production data for ' + chartDate"></p>
                </div>
            </div>

            {{-- Row 2b: Spindle Utilization / Hour — Industry 4.0 --}}
            <div class="bg-slate-900 rounded-2xl border border-slate-700/50 p-5">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-3">
                        <h3 class="text-xs font-semibold uppercase tracking-widest text-slate-400">Spindle Utilization / Hour</h3>
                        <span class="rounded-full bg-violet-500/15 border border-violet-500/30 px-2 py-0.5 text-xs font-medium text-violet-400">Industry 4.0</span>
                    </div>
                    <div class="flex items-center gap-4 text-[11px]">
                        <span class="flex items-center gap-1.5 text-green-400"><span class="h-2.5 w-4 rounded inline-block bg-green-500/80"></span>Spindle ON</span>
                        <span class="flex items-center gap-1.5 text-yellow-400"><span class="h-2.5 w-4 rounded inline-block bg-yellow-400/75"></span>Idle</span>
                        <span class="flex items-center gap-1.5 text-red-400"><span class="h-2.5 w-4 rounded inline-block bg-red-500/80"></span>Alarm</span>
                    </div>
                </div>
                <p class="text-xs text-slate-600 mb-4">Each bar = 1 hour. Cutting vs idle vs alarm time fraction per hour.</p>
                <div x-show="chartData && chartData.labels && chartData.labels.length > 0" style="height: 200px; position: relative;">
                    <canvas id="detail-spindle"></canvas>
                </div>
                <div x-show="!chartLoading && (!chartData || !chartData.labels || chartData.labels.length === 0)"
                     class="flex items-center justify-center h-32 text-slate-600 text-sm">No data for this period</div>
            </div>

            {{-- Row 3: Rejects + Alarms (side by side) --}}
            <div class="grid grid-cols-2 gap-5">

                {{-- Rejects / Hour --}}
                <div class="bg-slate-900 rounded-2xl border border-slate-700/50 p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xs font-semibold uppercase tracking-widest text-slate-400">Rejects / Hour</h3>
                        <span class="text-xs text-slate-500 tabular-nums"
                              x-text="'Total: ' + machinePartsTotals.rejects + ' (' + machinePartsTotals.defect_rate + '%)'"></span>
                    </div>
                    <div x-show="chartData && chartData.labels && chartData.labels.length > 0" style="height: 160px; position: relative;">
                        <canvas id="detail-rejects"></canvas>
                    </div>
                    <div x-show="!chartLoading && (!chartData || !chartData.labels || chartData.labels.length === 0)"
                         class="flex items-center justify-center h-32 text-slate-600 text-sm">No data</div>
                </div>

                {{-- Alarm Events / Hour --}}
                <div class="bg-slate-900 rounded-2xl border border-slate-700/50 p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xs font-semibold uppercase tracking-widest text-slate-400">Alarm Events / Hour</h3>
                        <span class="text-xs text-slate-500 tabular-nums"
                              x-text="'Total: ' + machinePartsTotals.alarm_events"></span>
                    </div>
                    <div x-show="chartData && chartData.labels && chartData.labels.length > 0" style="height: 160px; position: relative;">
                        <canvas id="detail-alarms"></canvas>
                    </div>
                    <div x-show="!chartLoading && (!chartData || !chartData.labels || chartData.labels.length === 0)"
                         class="flex items-center justify-center h-32 text-slate-600 text-sm">No data</div>
                </div>
            </div>

        </div>{{-- end scrollable content --}}
    </div>{{-- end full-screen overlay --}}


    {{-- ── Top bar: factory selector + refresh status ─────── --}}
    <div class="flex flex-wrap items-center gap-3">

        {{-- Factory selector (super-admin only) --}}
        <template x-if="factories.length > 0">
            <div class="flex items-center gap-2">
                <label class="text-sm font-medium text-gray-700">Factory:</label>
                <select
                    @change="switchFactory($event.target.value)"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                    <option value="">All Factories</option>
                    <template x-for="f in factories" :key="f.id">
                        <option :value="f.id" :selected="currentFactoryId == f.id" x-text="f.name"></option>
                    </template>
                </select>
            </div>
        </template>

        {{-- Status summary badges --}}
        <div class="flex flex-wrap gap-2 ml-2">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-800">
                <span class="h-2 w-2 rounded-full bg-green-500"></span>
                Running: <span x-text="statusCounts.running ?? 0"></span>
            </span>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-yellow-100 px-3 py-1 text-xs font-semibold text-yellow-800">
                <span class="h-2 w-2 rounded-full bg-yellow-400"></span>
                Idle: <span x-text="statusCounts.idle ?? 0"></span>
            </span>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-800">
                <span class="h-2 w-2 rounded-full bg-red-500"></span>
                Alarm: <span x-text="statusCounts.alarm ?? 0"></span>
            </span>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-800">
                <span class="h-2 w-2 rounded-full bg-blue-400"></span>
                Standby: <span x-text="statusCounts.standby ?? 0"></span>
            </span>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">
                <span class="h-2 w-2 rounded-full bg-gray-400"></span>
                Offline: <span x-text="statusCounts.offline ?? 0"></span>
            </span>
        </div>

        {{-- Refresh status --}}
        <div class="ml-auto flex items-center gap-3">
            <span class="text-xs text-gray-400" x-show="lastRefresh">
                Updated: <span x-text="lastRefresh"></span>
            </span>
            <span class="text-xs text-gray-400">
                Next in <span x-text="countdown"></span>s
            </span>
            <button
                @click="refresh()"
                :disabled="loading"
                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 shadow-sm hover:bg-gray-50 disabled:opacity-50"
            >
                <svg class="h-3.5 w-3.5" :class="loading ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Refresh
            </button>
        </div>
    </div>

    {{-- ── Error banner ─────────────────────────────────────── --}}
    <div x-show="error" x-cloak class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700" x-text="error"></div>

    {{-- ── Machine grid ──────────────────────────────────────── --}}
    <div>
        <div x-show="loading && machines.length === 0" class="flex items-center justify-center h-40">
            <div class="text-gray-400 text-sm">Loading machines...</div>
        </div>

        <div x-show="!loading || machines.length > 0" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
            <template x-for="m in machines" :key="m.id">
                <button
                    @click="selectMachine(m)"
                    :class="[
                        'relative flex flex-col rounded-xl border-2 p-4 text-left transition-all hover:shadow-lg focus:outline-none',
                        selectedMachine?.id === m.id && detailOpen ? 'ring-2 ring-indigo-500 ring-offset-1' : '',
                        statusBorderClass(m.iot_status)
                    ]"
                >
                    {{-- Status dot --}}
                    <div class="flex items-center justify-between mb-2">
                        <span class="relative inline-flex h-3 w-3 rounded-full" :class="statusDotClass(m.iot_status)">
                            <template x-if="m.iot_status === 'running'">
                                <span :class="['absolute inline-flex h-full w-full rounded-full opacity-75 animate-ping', statusDotClass(m.iot_status)]"></span>
                            </template>
                        </span>
                        <span
                            :class="['text-xs font-bold tracking-wider px-2 py-0.5 rounded-full', statusBadgeClass(m.iot_status)]"
                            x-text="m.iot_status.toUpperCase()"
                        ></span>
                    </div>

                    {{-- Machine name --}}
                    <p class="font-semibold text-gray-900 text-sm truncate" x-text="m.name"></p>
                    <p class="text-xs text-gray-500 truncate" x-text="m.code + (m.type ? ' · ' + m.type : '')"></p>

                    {{-- Alarm indicator --}}
                    <template x-if="m.alarm_code > 0">
                        <div class="mt-2 flex items-center gap-1 text-xs font-medium text-red-600 alarm-blink">
                            <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            Alarm <span x-text="m.alarm_code"></span>
                        </div>
                    </template>

                    {{-- Counters --}}
                    <div class="mt-3 border-t border-gray-100 pt-2 flex justify-between text-xs text-gray-600">
                        <span>Parts</span>
                        <span class="font-mono font-semibold" x-text="m.part_count.toLocaleString()"></span>
                    </div>
                    <div class="flex justify-between text-xs text-gray-500">
                        <span>Rejects</span>
                        <span class="font-mono" :class="m.part_reject > 0 ? 'text-red-600 font-semibold' : ''" x-text="m.part_reject.toLocaleString()"></span>
                    </div>

                    {{-- Last seen --}}
                    <p class="mt-1 text-xs text-gray-400" x-text="m.last_seen ? timeAgo(m.last_seen) : 'No data'"></p>

                    {{-- Click hint --}}
                    <div class="mt-2 text-xs text-indigo-500 font-medium opacity-0 group-hover:opacity-100 transition-opacity">
                        View dashboard →
                    </div>
                </button>
            </template>
        </div>
    </div>

    {{-- ── Empty state ─────────────────────────────────────── --}}
    <div x-show="!loading && machines.length === 0" class="flex flex-col items-center justify-center py-16 text-gray-400">
        <svg class="h-16 w-16 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/>
        </svg>
        <p class="text-lg font-medium">No machines found</p>
        <p class="text-sm mt-1">Add machines in the Machines section or select a different factory.</p>
    </div>

    {{-- ══════════════════════════════════════════════════════════
         OEE — Shift Production Report
    ══════════════════════════════════════════════════════════ --}}
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200 overflow-hidden">

        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3">
            <div class="flex items-center gap-3">
                <h2 class="font-semibold text-gray-900 text-sm">OEE — Shift Production Report</h2>
                <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700">Live from IoT pulses</span>
            </div>
            <div class="flex items-center gap-3">
                <input
                    type="date"
                    x-model="oeeDate"
                    @change="loadOee()"
                    class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-700 focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300"
                >
                <span class="text-xs text-gray-400" x-show="oeeLoading">
                    <svg class="inline h-3.5 w-3.5 animate-spin mr-1" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    Calculating…
                </span>
            </div>
        </div>

        <div class="flex flex-wrap gap-3 border-b border-gray-50 bg-gray-50/50 px-5 py-2 text-xs text-gray-500">
            <span><span class="font-semibold">Avail</span> = (Planned − Alarm) ÷ Planned</span>
            <span class="text-gray-300">|</span>
            <span><span class="font-semibold">Perf</span> = (Parts × Cycle Time) ÷ Available Time</span>
            <span class="text-gray-300">|</span>
            <span><span class="font-semibold">Qual</span> = Good ÷ Total</span>
            <span class="text-gray-300">|</span>
            <span><span class="font-semibold">OEE</span> = A × P × Q</span>
            <span class="text-gray-300">|</span>
            <span class="text-yellow-600">⚡ Perf/OEE require a production plan with cycle time</span>
        </div>

        <template x-if="oeeData.length > 0">
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="border-b border-gray-100 bg-gray-50">
                        <tr class="text-left font-medium uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-2">Machine</th>
                            <th class="px-4 py-2">Shift</th>
                            <th class="px-4 py-2 text-right">Planned</th>
                            <th class="px-4 py-2 text-right">Actual</th>
                            <th class="px-4 py-2 text-right">Good</th>
                            <th class="px-4 py-2 text-right">Rejects</th>
                            <th class="px-4 py-2 text-center">Attain%</th>
                            <th class="px-4 py-2 text-center">Avail%</th>
                            <th class="px-4 py-2 text-center">Perf%</th>
                            <th class="px-4 py-2 text-center">Qual%</th>
                            <th class="px-4 py-2 text-center">OEE%</th>
                            <th class="px-4 py-2 text-right text-gray-400">Alarm min</th>
                            <th class="px-4 py-2 text-right text-gray-400">Logs</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <template x-for="m in oeeData" :key="m.machine.id">
                            <template x-for="(s, si) in m.shifts" :key="m.machine.id + '-' + s.shift_id">
                                <tr
                                    @click="selectMachineById(m.machine.id)"
                                    class="hover:bg-indigo-50 transition-colors cursor-pointer"
                                >
                                    <td class="px-4 py-2.5 font-medium text-gray-900">
                                        <template x-if="si === 0">
                                            <span class="hover:text-indigo-600" x-text="m.machine.name + ' (' + m.machine.code + ')'"></span>
                                        </template>
                                        <template x-if="si > 0">
                                            <span class="text-gray-300">↳</span>
                                        </template>
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-600" x-text="s.shift_name"></td>
                                    <td class="px-4 py-2.5 text-right font-mono text-gray-600" x-text="s.planned_qty > 0 ? s.planned_qty.toLocaleString() : '—'"></td>
                                    <td class="px-4 py-2.5 text-right font-mono text-gray-900 font-semibold" x-text="s.total_parts.toLocaleString()"></td>
                                    <td class="px-4 py-2.5 text-right font-mono text-green-700 font-semibold" x-text="s.good_parts.toLocaleString()"></td>
                                    <td class="px-4 py-2.5 text-right font-mono" :class="s.reject_parts > 0 ? 'text-red-600 font-semibold' : 'text-gray-400'" x-text="s.reject_parts > 0 ? s.reject_parts.toLocaleString() : '—'"></td>
                                    <td class="px-4 py-2.5 text-center">
                                        <template x-if="s.attainment_pct !== null">
                                            <span :class="oeeAttainClass(s.attainment_pct)" x-text="s.attainment_pct + '%'"></span>
                                        </template>
                                        <template x-if="s.attainment_pct === null">
                                            <span class="text-gray-300">—</span>
                                        </template>
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        <span :class="oeePctClass(s.availability_pct)" x-text="s.availability_pct + '%'"></span>
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        <template x-if="s.performance_pct !== null">
                                            <span :class="oeePctClass(s.performance_pct)" x-text="s.performance_pct + '%'"></span>
                                        </template>
                                        <template x-if="s.performance_pct === null">
                                            <span class="text-yellow-500 text-xs" title="Add a production plan with cycle time">No plan</span>
                                        </template>
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        <span :class="oeePctClass(s.quality_pct)" x-text="s.quality_pct + '%'"></span>
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        <template x-if="s.oee_pct !== null">
                                            <span :class="oeeBadgeClass(s.oee_pct)" x-text="s.oee_pct + '%'"></span>
                                        </template>
                                        <template x-if="s.oee_pct === null">
                                            <span class="text-gray-300">—</span>
                                        </template>
                                    </td>
                                    <td class="px-4 py-2.5 text-right font-mono text-gray-400" x-text="s.alarm_minutes"></td>
                                    <td class="px-4 py-2.5 text-right font-mono text-gray-400" x-text="s.log_count.toLocaleString()"></td>
                                </tr>
                            </template>
                        </template>
                    </tbody>
                </table>
            </div>
        </template>

        <template x-if="!oeeLoading && oeeData.length === 0">
            <div class="flex flex-col items-center justify-center py-10 text-gray-400">
                <svg class="h-10 w-10 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="text-sm">No OEE data for this date.</p>
                <p class="text-xs mt-1 text-center max-w-sm">Ensure machines have IoT logs for the selected date and active shifts are configured.</p>
            </div>
        </template>
    </div>{{-- end OEE section --}}

    {{-- ══════════════════════════════════════════════════════════
         OEE TREND — Historical Line Chart
    ══════════════════════════════════════════════════════════ --}}
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200 overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3">
            <div class="flex items-center gap-3">
                <h2 class="font-semibold text-gray-900 text-sm">OEE Trend</h2>
                <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">Historical</span>
            </div>
            <div class="flex items-center gap-2">
                <select x-model="trendDays" @change="loadTrend()"
                        class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-700 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                    <option value="7">Last 7 days</option>
                    <option value="14">Last 14 days</option>
                    <option value="30" selected>Last 30 days</option>
                    <option value="90">Last 90 days</option>
                </select>
                <span class="text-xs text-gray-400" x-show="trendLoading">Loading…</span>
            </div>
        </div>

        <div class="p-5">
            <div x-show="trendData.length > 0" style="height: 240px; position: relative;">
                <canvas id="trend-chart"></canvas>
            </div>
            <div x-show="!trendLoading && trendData.length === 0"
                 class="flex items-center justify-center h-40 text-gray-400 text-sm">
                No historical OEE data yet. Run <code class="mx-1 rounded bg-gray-100 px-1 py-0.5 text-xs">php artisan iot:aggregate-oee</code> to populate.
            </div>

            {{-- Legend --}}
            <div x-show="trendData.length > 0"
                 class="mt-3 flex flex-wrap gap-4 text-xs text-gray-500">
                <span class="flex items-center gap-1.5">
                    <span class="inline-block h-2.5 w-5 rounded bg-indigo-500"></span>OEE%
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="inline-block h-2.5 w-5 rounded bg-green-500"></span>Availability%
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="inline-block h-2.5 w-5 rounded bg-amber-500"></span>Performance%
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="inline-block h-2.5 w-5 rounded bg-blue-400"></span>Quality%
                </span>
            </div>
        </div>
    </div>{{-- end Trend section --}}

</div>{{-- end x-data --}}
@endsection

@push('scripts')
<script>
function iotDashboard(apiToken, factoryId, factories) {
    return {
        apiToken,
        factories:       factories || [],
        currentFactoryId: factoryId,

        machines:     [],
        selectedMachine: null,
        detailOpen:   false,
        chartData:    null,
        chartHours:   24,

        shifts:          [],
        selectedShiftId: '',
        chartDate:       new Date().toISOString().split('T')[0],

        loading:      false,
        chartLoading: false,
        error:        null,
        lastRefresh:  null,
        countdown:    30,

        oeeData:    [],
        oeeDate:    new Date().toISOString().split('T')[0],
        oeeLoading: false,

        trendData:    [],
        trendDays:    30,
        trendLoading: false,
        _trendChart:  null,

        timelineData:    null,
        timelineLoading: false,

        _refreshTimer:   null,
        _oeeTimer:       null,
        _countdownTimer: null,
        _charts: {},

        // ── Computed ──────────────────────────────────────────

        get statusCounts() {
            return this.machines.reduce((acc, m) => {
                acc[m.iot_status] = (acc[m.iot_status] || 0) + 1;
                return acc;
            }, {});
        },

        get machineOeeData() {
            if (!this.selectedMachine || !this.oeeData.length) return null;
            return this.oeeData.find(m => m.machine.id === this.selectedMachine.id) || null;
        },

        get machineOeeShifts() {
            return this.machineOeeData?.shifts || [];
        },

        // Best shift: prefer one with OEE, fallback to first
        get machineFirstOee() {
            const shifts = this.machineOeeShifts;
            if (!shifts.length) return null;
            return shifts.find(s => s.oee_pct !== null) || shifts[0];
        },

        // OEE for the currently selected shift (or best if All Day)
        get machineSelectedOee() {
            const shifts = this.machineOeeShifts;
            if (!shifts.length) return null;
            if (this.selectedShiftId) {
                return shifts.find(s => s.shift_id == this.selectedShiftId) || shifts[0];
            }
            return shifts.find(s => s.oee_pct !== null) || shifts[0];
        },

        get machinePartsTotals() {
            return {
                total:        this.chartData?.summary?.total_parts   || 0,
                rejects:      this.chartData?.summary?.total_rejects || 0,
                defect_rate:  this.chartData?.summary?.defect_rate   || 0,
                alarm_events: this.chartData?.summary?.alarm_events  || 0,
            };
        },

        get selectedShiftObj() {
            if (!this.selectedShiftId) return null;
            return this.shifts.find(s => s.id == this.selectedShiftId) || null;
        },

        get exportUrl() {
            const base = '/admin/iot/machines/' + (this.selectedMachine?.id) + '/export';
            if (this.selectedShiftId && this.chartDate) {
                return base + '?shift_id=' + this.selectedShiftId + '&date=' + this.chartDate;
            }
            return base + '?hours=24';
        },

        get machineTimeStats() {
            const ts = this.chartData?.time_stats;
            if (!ts || ts.total_samples === 0) return null;
            const runSec   = ts.run_seconds   || 0;
            const idleSec  = ts.idle_seconds  || 0;
            const alarmSec = ts.alarm_seconds || 0;
            const upSec    = runSec + idleSec;
            const totalSec = upSec + alarmSec;
            return {
                run:           this.fmtHHMM(runSec),
                runLabel:      this.fmtHrMin(runSec),
                idle:          this.fmtHHMM(idleSec),
                idleLabel:     this.fmtHrMin(idleSec),
                alarm:         this.fmtHHMM(alarmSec),
                alarmLabel:    this.fmtHrMin(alarmSec),
                uptime:        this.fmtHHMM(upSec),
                uptimeLabel:   this.fmtHrMin(upSec),
                // IoT-based Availability = (run+idle) / total × 100
                availPct:      ts.availability_pct ?? null,
                // Spindle Utilization = run / total × 100 (actual cutting time fraction)
                spindleUtilPct: ts.spindle_util_pct ?? (totalSec > 0 ? Math.round(runSec / totalSec * 1000) / 10 : null),
                // Parts produced per hour of actual running time
                partsPerRunHr: ts.parts_per_run_hour ?? null,
            };
        },

        // Hourly (or 30-min) tick marks for the timeline x-axis
        get timelineTicks() {
            if (!this.timelineData || !this.timelineData.total_min) return [];
            const total = this.timelineData.total_min;
            // Choose a readable tick step based on window length
            const step  = total <= 90 ? 15 : total <= 240 ? 30 : 60;
            const [sh, sm] = this.timelineData.window_from.split(':').map(Number);
            const sinceMin  = sh * 60 + sm;
            const ticks     = [];
            for (let m = 0; m <= total; m += step) {
                const abs = sinceMin + m;
                const h   = Math.floor(abs / 60) % 24;
                const mm  = abs % 60;
                ticks.push({
                    min:   m,
                    pct:   (m / total * 100),
                    label: `${String(h).padStart(2,'0')}:${String(mm).padStart(2,'0')}`,
                });
            }
            return ticks;
        },

        // ── Lifecycle ─────────────────────────────────────────

        init() {
            this.refresh();
            this.loadOee();
            this.loadShifts();
            this.loadTrend();
            // Status refreshes every 30s — machine on/off state changes slowly
            this._refreshTimer   = setInterval(() => this.refresh(), 30000);
            // OEE refreshes every 5 min — matches the aggregator schedule
            this._oeeTimer       = setInterval(() => this.loadOee(), 300000);
            this._countdownTimer = setInterval(() => {
                this.countdown = Math.max(0, this.countdown - 1);
            }, 1000);
        },

        // ── Data fetching ─────────────────────────────────────

        async refresh() {
            this.countdown = 30;
            this.loading   = true;
            this.error     = null;

            try {
                const params = this.currentFactoryId ? `?factory_id=${this.currentFactoryId}` : '';
                const res    = await fetch(`/api/v1/iot/status${params}`, {
                    headers: { 'Authorization': `Bearer ${this.apiToken}`, 'Accept': 'application/json' },
                });
                if (res.status === 401) { window.location.href = '/login'; return; }
                if (!res.ok) throw new Error(`Status ${res.status}`);

                const json    = await res.json();
                this.machines = json.data || [];
                this.lastRefresh = new Date().toLocaleTimeString();

                // Keep selected machine data live
                if (this.selectedMachine) {
                    const updated = this.machines.find(m => m.id === this.selectedMachine.id);
                    if (updated) this.selectedMachine = updated;
                }
            } catch (e) {
                this.error = 'Failed to load machine status: ' + e.message;
            } finally {
                this.loading = false;
            }
        },

        async loadOee(overrideFactoryId = null) {
            const fid = overrideFactoryId ?? this.currentFactoryId ?? factoryId;
            if (!fid) return;

            this.oeeLoading = true;
            try {
                const params = new URLSearchParams({ date: this.oeeDate, factory_id: fid });
                const res    = await fetch(`/api/v1/iot/oee?${params}`, {
                    headers: { 'Authorization': `Bearer ${this.apiToken}`, 'Accept': 'application/json' },
                });
                if (!res.ok) return;
                const json   = await res.json();
                this.oeeData = json.machines || [];

                // Re-render charts if machine detail is open
                if (this.detailOpen) {
                    setTimeout(() => this.renderCharts(), 80);
                }
            } catch { /* silent */ } finally {
                this.oeeLoading = false;
            }
        },

        async loadTrend() {
            const fid = this.currentFactoryId;
            if (!fid) return;
            this.trendLoading = true;
            try {
                const params = new URLSearchParams({ factory_id: fid, days: this.trendDays });
                const res = await fetch(`/api/v1/iot/oee/trend?${params}`, {
                    headers: { 'Authorization': `Bearer ${this.apiToken}`, 'Accept': 'application/json' },
                });
                if (!res.ok) return;
                const json    = await res.json();
                this.trendData = json.trend || [];
                setTimeout(() => this.renderTrendChart(), 80);
            } catch { /* silent */ } finally {
                this.trendLoading = false;
            }
        },

        renderTrendChart() {
            const canvas = document.getElementById('trend-chart');
            if (!canvas || !this.trendData.length) return;
            if (this._trendChart) { this._trendChart.destroy(); this._trendChart = null; }
            const labels = this.trendData.map(r => {
                const d = new Date(r.oee_date);
                return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
            });
            this._trendChart = new Chart(canvas, {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        { label: 'OEE%',          data: this.trendData.map(r => r.avg_oee),          borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,0.08)', tension: 0.3, pointRadius: 3, fill: true },
                        { label: 'Availability%', data: this.trendData.map(r => r.avg_availability), borderColor: '#22c55e', backgroundColor: 'transparent', tension: 0.3, pointRadius: 3 },
                        { label: 'Performance%',  data: this.trendData.map(r => r.avg_performance),  borderColor: '#f59e0b', backgroundColor: 'transparent', tension: 0.3, pointRadius: 3 },
                        { label: 'Quality%',      data: this.trendData.map(r => r.avg_quality),      borderColor: '#60a5fa', backgroundColor: 'transparent', tension: 0.3, pointRadius: 3 },
                    ],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } },
                    scales: {
                        y: { min: 0, max: 100, ticks: { callback: v => v + '%', font: { size: 11 } }, grid: { color: '#f1f5f9' } },
                        x: { ticks: { font: { size: 11 }, maxRotation: 0 }, grid: { display: false } },
                    },
                },
            });
        },

        async loadShifts(factoryId) {
            const fid = factoryId ?? this.currentFactoryId;
            if (!fid) return;
            try {
                const res = await fetch(`/api/v1/shifts?factory_id=${fid}`, {
                    headers: { 'Authorization': `Bearer ${this.apiToken}`, 'Accept': 'application/json' },
                });
                if (!res.ok) return;
                const json  = await res.json();
                this.shifts = json.data || [];
            } catch { /* silent */ }
        },

        async loadChart(machineId) {
            this.chartLoading = true;
            this.chartData    = null;
            this.error        = null;

            try {
                let url;
                if (this.selectedShiftId && this.chartDate) {
                    url = `/api/v1/iot/machines/${machineId}/chart?shift_id=${this.selectedShiftId}&date=${this.chartDate}`;
                } else {
                    // All Day: always pass the selected date so the window is
                    // the full calendar day (00:00–24:00), not a rolling 24h window.
                    // This ensures All Day totals = Morning + Afternoon combined.
                    url = `/api/v1/iot/machines/${machineId}/chart?date=${this.chartDate}`;
                }
                const res = await fetch(url, {
                    headers: { 'Authorization': `Bearer ${this.apiToken}`, 'Accept': 'application/json' },
                });
                if (!res.ok) throw new Error(`Status ${res.status}`);
                this.chartData = await res.json();
                this.chartHours = this.chartData.period_hours || 24;
            } catch (e) {
                this.error = 'Failed to load chart data: ' + e.message;
            } finally {
                this.chartLoading = false;
            }

            // Use setTimeout so Alpine finishes x-show DOM toggles before Chart.js queries canvas IDs
            setTimeout(() => this.renderCharts(), 80);
            this.loadTimeline(machineId); // fire-and-forget; independent of chart render
        },

        async loadTimeline(machineId) {
            this.timelineLoading = true;
            this.timelineData    = null;
            try {
                let url;
                if (this.selectedShiftId && this.chartDate) {
                    url = `/api/v1/iot/machines/${machineId}/timeline?shift_id=${this.selectedShiftId}&date=${this.chartDate}`;
                } else {
                    url = `/api/v1/iot/machines/${machineId}/timeline?date=${this.chartDate}`;
                }
                const res = await fetch(url, {
                    headers: { 'Authorization': `Bearer ${this.apiToken}`, 'Accept': 'application/json' },
                });
                if (!res.ok) throw new Error(`Status ${res.status}`);
                this.timelineData = await res.json();
            } catch { /* silent — timeline is optional enhancement */ } finally {
                this.timelineLoading = false;
            }
        },

        // ── Machine selection ─────────────────────────────────

        async selectMachine(machine) {
            this.selectedMachine = machine;
            this.selectedShiftId = '';
            this.chartDate       = this.oeeDate; // sync with OEE date picker
            this.detailOpen      = true;
            // Resolve factory: machine's factory > global selector > PHP-injected
            const machineFid = machine.factory_id || this.currentFactoryId || factoryId;
            // Load shifts + OEE in parallel — OEE must be ready before renderGauges()
            await Promise.all([
                this.loadShifts(machineFid),
                this.loadOee(machineFid),
            ]);
            // Auto-select the shift that covers the current time
            this.autoSelectShift();
            await this.loadChart(machine.id);
        },

        // Find and select the shift whose window contains the current local time.
        autoSelectShift() {
            if (!this.shifts.length) return;
            const now    = new Date();
            const nowMin = now.getHours() * 60 + now.getMinutes();
            const active = this.shifts.find(s => {
                const [sh, sm] = (s.start_time || '00:00').split(':').map(Number);
                const [eh, em] = (s.end_time   || '00:00').split(':').map(Number);
                const startMin = sh * 60 + sm;
                const endMin   = eh * 60 + em;
                if (endMin > startMin) {
                    // Normal shift (e.g., 08:00 – 20:00)
                    return nowMin >= startMin && nowMin < endMin;
                } else {
                    // Overnight shift (e.g., 20:00 – 06:00)
                    return nowMin >= startMin || nowMin < endMin;
                }
            });
            if (active) {
                this.selectedShiftId = String(active.id);
            }
        },

        async selectMachineById(machineId) {
            const machine = this.machines.find(m => m.id === machineId);
            if (machine) await this.selectMachine(machine);
        },

        closeDetail() {
            this.detailOpen      = false;
            this.selectedShiftId = '';
            this.timelineData    = null;
            this.destroyCharts();
            setTimeout(() => { this.selectedMachine = null; }, 250);
        },

        switchFactory(id) {
            this.currentFactoryId = id || null;
            this.selectedMachine  = null;
            this.selectedShiftId  = '';
            this.shifts           = [];
            this.detailOpen       = false;
            this.destroyCharts();
            this.refresh();
            this.loadOee();
            this.loadTrend();
            this.loadShifts(this.currentFactoryId);
        },

        // ── Timeline helpers ──────────────────────────────────

        timelineSegColor(state) {
            const c = { running: '#22c55e', idle: '#eab308', alarm: '#ef4444', offline: '#1e293b' };
            return c[state] || c.idle;
        },

        fmtMin(minutes) {
            if (!minutes) return '0m';
            const h = Math.floor(minutes / 60);
            const m = minutes % 60;
            return h > 0 ? `${h}h ${m > 0 ? m + 'm' : ''}` : `${m}m`;
        },

        // ── Chart rendering ───────────────────────────────────

        renderCharts() {
            ['parts', 'rejects', 'alarms', 'spindle'].forEach(k => {
                this._charts[k]?.destroy();
                this._charts[k] = null;
            });

            if (!this.chartData || this.chartData.labels.length === 0) return;

            const labels = this.chartData.labels;

            const darkOpts = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        ticks: { maxTicksLimit: 8, maxRotation: 0, font: { size: 10 }, color: '#94a3b8' },
                        grid: { color: 'rgba(255,255,255,0.06)' },
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, font: { size: 10 }, color: '#94a3b8' },
                        grid: { color: 'rgba(255,255,255,0.06)' },
                    },
                },
            };

            const partsCtx = document.getElementById('detail-parts');
            if (partsCtx) {
                this._charts.parts = new Chart(partsCtx, {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [{
                            label: 'Parts/Hour',
                            data: this.chartData.parts_per_hour,
                            borderColor: '#22c55e',
                            backgroundColor: 'rgba(34,197,94,0.12)',
                            fill: true,
                            tension: 0.4,
                            pointRadius: 3,
                            pointBackgroundColor: '#22c55e',
                            borderWidth: 2,
                        }],
                    },
                    options: darkOpts,
                });
            }

            const rejectsCtx = document.getElementById('detail-rejects');
            if (rejectsCtx) {
                this._charts.rejects = new Chart(rejectsCtx, {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{
                            label: 'Rejects/Hour',
                            data: this.chartData.rejects_per_hour,
                            backgroundColor: 'rgba(239,68,68,0.75)',
                            borderRadius: 3,
                        }],
                    },
                    options: darkOpts,
                });
            }

            const alarmsCtx = document.getElementById('detail-alarms');
            if (alarmsCtx) {
                this._charts.alarms = new Chart(alarmsCtx, {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{
                            label: 'Alarms/Hour',
                            data: this.chartData.alarms_per_hour,
                            backgroundColor: 'rgba(251,146,60,0.75)',
                            borderRadius: 3,
                        }],
                    },
                    options: darkOpts,
                });
            }

            // ── Spindle Utilization / Hour — stacked 100% bar ────────
            const spindleCtx = document.getElementById('detail-spindle');
            if (spindleCtx && this.chartData.spindle_util_per_hour) {
                const spindleOpts = {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: ctx => `${ctx.dataset.label}: ${ctx.parsed.y}%`,
                            },
                        },
                    },
                    scales: {
                        x: {
                            stacked: true,
                            ticks: { maxTicksLimit: 8, maxRotation: 0, font: { size: 10 }, color: '#94a3b8' },
                            grid:  { color: 'rgba(255,255,255,0.05)' },
                        },
                        y: {
                            stacked: true,
                            min: 0, max: 100,
                            ticks: { callback: v => v + '%', font: { size: 10 }, color: '#94a3b8', stepSize: 25 },
                            grid:  { color: 'rgba(255,255,255,0.06)' },
                        },
                    },
                };
                this._charts.spindle = new Chart(spindleCtx, {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [
                            {
                                label: 'Spindle ON',
                                data: this.chartData.spindle_util_per_hour,
                                backgroundColor: 'rgba(34,197,94,0.82)',
                                stack: 'state',
                                borderRadius: { topLeft: 3, topRight: 3 },
                                borderSkipped: false,
                            },
                            {
                                label: 'Idle',
                                data: this.chartData.idle_pct_per_hour,
                                backgroundColor: 'rgba(234,179,8,0.70)',
                                stack: 'state',
                                borderSkipped: false,
                            },
                            {
                                label: 'Alarm',
                                data: this.chartData.alarm_pct_per_hour,
                                backgroundColor: 'rgba(239,68,68,0.80)',
                                stack: 'state',
                                borderSkipped: false,
                            },
                        ],
                    },
                    options: spindleOpts,
                });
            }
        },

        renderGauges() {
            ['gaugeOee', 'gaugeAvail', 'gaugePerf', 'gaugeQual'].forEach(k => {
                this._charts[k]?.destroy();
                this._charts[k] = null;
            });

            const s = this.machineSelectedOee;

            const specs = [
                { id: 'detail-gauge-oee',   key: 'gaugeOee',   val: s?.oee_pct },
                { id: 'detail-gauge-avail', key: 'gaugeAvail', val: s?.availability_pct },
                { id: 'detail-gauge-perf',  key: 'gaugePerf',  val: s?.performance_pct },
                { id: 'detail-gauge-qual',  key: 'gaugeQual',  val: s?.quality_pct },
            ];

            specs.forEach(spec => {
                const ctx = document.getElementById(spec.id);
                if (!ctx) return;

                const val = spec.val;
                const hasVal = val !== null && val !== undefined;
                const displayVal = hasVal ? Math.min(100, Math.max(0, val)) : 0;

                let arcColor = '#334155'; // slate-700 (no data)
                if (hasVal) {
                    arcColor = val >= 85 ? '#22c55e' : val >= 60 ? '#eab308' : '#ef4444';
                }

                this._charts[spec.key] = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        datasets: [{
                            data: [displayVal, 100 - displayVal],
                            backgroundColor: [arcColor, '#1e293b'],
                            borderWidth: 0,
                            borderRadius: hasVal ? [4, 0] : [0, 0],
                        }],
                    },
                    options: {
                        circumference: 180,
                        rotation: 270,
                        cutout: '72%',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: { enabled: false },
                        },
                        animation: { duration: 900, easing: 'easeInOutQuart' },
                    },
                });
            });
        },

        destroyCharts() {
            Object.values(this._charts).forEach(c => c?.destroy());
            this._charts = {};
        },

        // ── Styling helpers ───────────────────────────────────

        statusDotClass(status) {
            return { running: 'bg-green-500', idle: 'bg-yellow-400', alarm: 'bg-red-500', offline: 'bg-gray-400' }[status] || 'bg-yellow-400';
        },

        statusBadgeClass(status) {
            return { running: 'bg-green-100 text-green-800', idle: 'bg-yellow-100 text-yellow-800', alarm: 'bg-red-100 text-red-800 alarm-blink', offline: 'bg-gray-100 text-gray-600' }[status] || 'bg-yellow-100 text-yellow-800';
        },

        statusBorderClass(status) {
            return { running: 'border-green-300 bg-green-50', idle: 'border-yellow-300 bg-yellow-50', alarm: 'border-red-300 bg-red-50', offline: 'border-gray-200 bg-white' }[status] || 'border-yellow-300 bg-yellow-50';
        },

        // Dark-theme variants for the overlay
        statusBadgeDarkClass(status) {
            return {
                running: 'bg-green-500/20 text-green-400 border border-green-500/40',
                idle:    'bg-yellow-500/20 text-yellow-400 border border-yellow-500/40',
                alarm:   'bg-red-500/20 text-red-400 border border-red-500/40 alarm-blink',
                offline: 'bg-slate-500/20 text-slate-400 border border-slate-500/40',
            }[status] || 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/40';
        },

        statusBgDarkClass(status) {
            return { running: 'bg-green-500/10', idle: 'bg-yellow-500/10', alarm: 'bg-red-500/10', offline: 'bg-slate-700/30' }[status] || 'bg-yellow-500/10';
        },

        statusTextDarkClass(status) {
            return { running: 'text-green-400', idle: 'text-yellow-400', alarm: 'text-red-400 alarm-blink', offline: 'text-slate-400' }[status] || 'text-yellow-400';
        },

        // Gauge text color
        gaugeTextClass(val) {
            if (val === null || val === undefined) return 'text-slate-500';
            return val >= 85 ? 'text-green-400' : val >= 60 ? 'text-yellow-400' : 'text-red-400';
        },

        // OEE table helpers
        oeeBadgeClass(pct) {
            if (pct === null || pct === undefined) return 'inline-block rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-400';
            if (pct >= 85) return 'inline-block rounded-full px-2 py-0.5 text-xs font-semibold bg-green-100 text-green-800';
            if (pct >= 60) return 'inline-block rounded-full px-2 py-0.5 text-xs font-semibold bg-yellow-100 text-yellow-800';
            return 'inline-block rounded-full px-2 py-0.5 text-xs font-semibold bg-red-100 text-red-800';
        },

        oeeBadgeDarkClass(pct) {
            if (pct === null || pct === undefined) return 'inline-block rounded-full px-2 py-0.5 text-xs font-medium bg-slate-700 text-slate-400';
            if (pct >= 85) return 'inline-block rounded-full px-2 py-0.5 text-xs font-semibold bg-green-500/20 text-green-400';
            if (pct >= 60) return 'inline-block rounded-full px-2 py-0.5 text-xs font-semibold bg-yellow-500/20 text-yellow-400';
            return 'inline-block rounded-full px-2 py-0.5 text-xs font-semibold bg-red-500/20 text-red-400';
        },

        oeePctClass(pct) {
            if (pct >= 90) return 'text-green-700 font-semibold';
            if (pct >= 70) return 'text-yellow-700';
            return 'text-red-600 font-semibold';
        },

        oeeAttainClass(pct) {
            if (pct >= 95) return 'inline-block rounded-full px-2 py-0.5 text-xs font-semibold bg-green-100 text-green-800';
            if (pct >= 80) return 'inline-block rounded-full px-2 py-0.5 text-xs font-semibold bg-yellow-100 text-yellow-800';
            return 'inline-block rounded-full px-2 py-0.5 text-xs font-semibold bg-red-100 text-red-800';
        },

        timeAgo(isoString) {
            const diff = Math.floor((Date.now() - new Date(isoString).getTime()) / 1000);
            if (diff < 60)   return diff + 's ago';
            if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
            return Math.floor(diff / 3600) + 'h ago';
        },

        // Format total seconds as "HH:MM"
        fmtHHMM(totalSeconds) {
            const s = Math.max(0, Math.round(totalSeconds));
            const h = Math.floor(s / 3600);
            const m = Math.floor((s % 3600) / 60);
            return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
        },

        // Format total seconds as "X hr Y min" label
        fmtHrMin(totalSeconds) {
            const s = Math.max(0, Math.round(totalSeconds));
            const h = Math.floor(s / 3600);
            const m = Math.floor((s % 3600) / 60);
            return h + ' hr ' + m + ' min';
        },
    };
}
</script>
@endpush

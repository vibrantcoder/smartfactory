@extends('employee.layouts.app')
@section('title', 'My Machine')

@push('head')
<style>
    @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }
    .alarm-blink { animation: blink .8s ease-in-out infinite; }
    @keyframes ping-slow { 0%{transform:scale(1);opacity:.75} 100%{transform:scale(2);opacity:0} }
    .ping-slow { animation: ping-slow 1.5s ease-out infinite; }
</style>
@endpush

@section('content')
<div
    x-data="employeeDashboard('{{ $apiToken }}', {{ $machineId }}, {{ $factoryId ?? 'null' }}, {{ $shifts->map(fn($s) => ['id'=>$s->id,'name'=>$s->name,'start_time'=>$s->start_time,'end_time'=>$s->end_time])->values()->toJson() }})"
    x-init="init()"
    class="space-y-5"
>

    {{-- ── Machine Live Status Card ───────────────────────────────── --}}
    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 overflow-hidden">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <div class="relative flex h-4 w-4 items-center justify-center">
                    <span :class="dotClass" class="relative inline-flex h-4 w-4 rounded-full"></span>
                    <template x-if="iotStatus === 'running'">
                        <span :class="dotClass" class="absolute inline-flex h-4 w-4 rounded-full opacity-75 ping-slow"></span>
                    </template>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-gray-900">{{ $machine?->name ?? 'My Machine' }}</h2>
                    <p class="text-xs text-gray-400">{{ $machine?->code }} &middot; {{ $machine?->type }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span :class="badgeClass"
                      class="text-xs font-bold uppercase tracking-wider px-3 py-1 rounded-full"
                      x-text="iotStatus.toUpperCase()"></span>
                <span class="text-xs text-gray-400">
                    Updated <span x-text="lastSeen" class="font-mono"></span>
                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-green-400 animate-pulse ml-1"></span>
                </span>
            </div>
        </div>

        {{-- Timeline summary grid --}}
        <div class="bg-gray-50 p-4 grid grid-cols-2 sm:grid-cols-4 gap-3">

            {{-- RUN TIME --}}
            <div class="rounded-xl bg-white border border-gray-200 px-4 py-3 flex items-center gap-3">
                <div class="shrink-0 flex h-9 w-9 items-center justify-center rounded-lg bg-green-100">
                    <svg class="h-5 w-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500">Run Time</p>
                    <p class="text-lg font-bold leading-tight text-green-600 font-mono"
                       x-text="timeline ? fmtMin(timeline.summary_min.running) : '—'"></p>
                    <p class="text-[10px] text-gray-400" x-text="timeline ? (Math.floor((timeline.summary_min.running||0)/60)+'h '+(((timeline.summary_min.running||0)%60))+'m') : ''"></p>
                </div>
            </div>

            {{-- IDLE TIME --}}
            <div class="rounded-xl bg-white border border-gray-200 px-4 py-3 flex items-center gap-3">
                <div class="shrink-0 flex h-9 w-9 items-center justify-center rounded-lg bg-yellow-100">
                    <svg class="h-5 w-5 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Idle Time</p>
                    <p class="text-lg font-bold leading-tight text-yellow-600 font-mono"
                       x-text="timeline ? fmtMin(timeline.summary_min.idle) : '—'"></p>
                    <p class="text-[10px] text-gray-400" x-text="timeline ? (Math.floor((timeline.summary_min.idle||0)/60)+'h '+(((timeline.summary_min.idle||0)%60))+'m') : ''"></p>
                </div>
            </div>

            {{-- ALARM TIME --}}
            <div class="rounded-xl bg-white border border-gray-200 px-4 py-3 flex items-center gap-3" :class="timeline && timeline.summary_min.alarm > 0 ? 'alarm-blink' : ''">
                <div class="shrink-0 flex h-9 w-9 items-center justify-center rounded-lg bg-red-100">
                    <svg class="h-5 w-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Alarm Time</p>
                    <p class="text-lg font-bold leading-tight text-red-600 font-mono"
                       x-text="timeline ? fmtMin(timeline.summary_min.alarm) : '—'"></p>
                    <p class="text-[10px] text-gray-400" x-text="timeline ? (Math.floor((timeline.summary_min.alarm||0)/60)+'h '+(((timeline.summary_min.alarm||0)%60))+'m') : ''"></p>
                </div>
            </div>

            {{-- OFFLINE --}}
            <div class="rounded-xl bg-white border border-gray-200 px-4 py-3 flex items-center gap-3">
                <div class="shrink-0 flex h-9 w-9 items-center justify-center rounded-lg bg-gray-100">
                    <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636a9 9 0 010 12.728M15.536 8.464a5 5 0 010 7.072M6.343 17.657a9 9 0 010-12.728M9.172 14.828a5 5 0 010-7.072M12 12h.01"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Offline</p>
                    <p class="text-lg font-bold leading-tight text-gray-500 font-mono"
                       x-text="timeline ? fmtMin(timeline.summary_min.offline) : '—'"></p>
                    <p class="text-[10px] text-gray-400" x-text="timeline ? (Math.floor((timeline.summary_min.offline||0)/60)+'h '+(((timeline.summary_min.offline||0)%60))+'m') : ''"></p>
                </div>
            </div>

        </div>

        {{-- Shift + last seen footer --}}
        <div class="bg-gray-50 border-t border-gray-100 px-5 py-2.5 flex items-center gap-2 text-xs text-gray-500">
            <svg class="h-3.5 w-3.5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span class="font-semibold text-gray-700" x-text="currentShiftName || '—'"></span>
            <span class="text-gray-300">&middot;</span>
            <span class="font-mono text-gray-400" x-text="lastSeen"></span>
        </div>
    </div>

    {{-- ── Production Progress ────────────────────────────────────────── --}}
    @php $todayPlans = $plans->filter(fn($p) => \Carbon\Carbon::parse($p->planned_date)->isToday()); @endphp
    @if($todayPlans->isNotEmpty())
    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900">Production Progress</h3>
            <span class="text-xs text-gray-400">{{ now()->format('d M Y') }}</span>
        </div>

        {{-- Parts (current shift) KPI row --}}
        <div class="grid grid-cols-2 divide-x divide-gray-100 border-b border-gray-100">
            <div class="px-5 py-4 text-center">
                <p class="text-xs font-medium uppercase tracking-wider text-gray-400 mb-1">
                    Parts (<span x-text="currentShiftName || 'Today'"></span>)
                </p>
                <p class="text-3xl font-bold text-indigo-600" x-text="chartSummary.total"></p>
            </div>
            <div class="px-5 py-4 text-center">
                <p class="text-xs font-medium uppercase tracking-wider text-gray-400 mb-1">Rejects</p>
                <p class="text-3xl font-bold" :class="chartSummary.rejects > 0 ? 'text-red-600' : 'text-gray-400'"
                   x-text="chartSummary.rejects"></p>
            </div>
        </div>

        {{-- Plan-by-plan progress (Produced = live IoT chartSummary.total) --}}
        @foreach($todayPlans as $plan)
        @php $isActive = $plan->status === 'in_progress'; @endphp
        <div class="px-5 py-4 {{ $isActive ? 'border-l-4 border-l-indigo-500' : '' }} {{ !$loop->last ? 'border-b border-gray-50' : '' }}">
            <div class="flex items-center justify-between mb-2">
                <div>
                    <p class="text-sm font-semibold text-gray-900">{{ $plan->shift?->name }}</p>
                    <p class="text-xs text-gray-400">{{ $plan->part?->part_number }} · Target: {{ number_format($plan->planned_qty) }} pcs</p>
                </div>
                {{-- % based on live IoT parts --}}
                <span class="text-lg font-bold"
                      :class="Math.min(100, Math.round(chartSummary.total / {{ $plan->planned_qty }} * 100)) >= 100 ? 'text-green-600' : '{{ $isActive ? 'text-indigo-600' : 'text-gray-500' }}'"
                      x-text="Math.min(100, Math.round(chartSummary.total / {{ $plan->planned_qty }} * 100)) + '%'">
                    —
                </span>
            </div>
            {{-- Progress bar driven by live IoT total --}}
            <div class="h-2.5 w-full rounded-full bg-gray-200 overflow-hidden mb-2">
                <div class="h-2.5 rounded-full transition-all"
                     :class="Math.min(100, Math.round(chartSummary.total / {{ $plan->planned_qty }} * 100)) >= 100 ? 'bg-green-500' : (Math.round(chartSummary.total / {{ $plan->planned_qty }} * 100) >= 60 ? 'bg-indigo-500' : 'bg-yellow-400')"
                     :style="`width:${Math.min(100, Math.round(chartSummary.total / {{ $plan->planned_qty }} * 100))}%`"></div>
            </div>
            <div class="grid grid-cols-3 gap-2 text-xs">
                <div class="text-center">
                    <p class="text-gray-400">Planned</p>
                    <p class="font-bold text-gray-800">{{ number_format($plan->planned_qty) }}</p>
                </div>
                <div class="text-center">
                    <p class="text-gray-400">Produced</p>
                    <p class="font-bold text-green-700" x-text="chartSummary.total">—</p>
                </div>
                <div class="text-center">
                    <p class="text-gray-400">Gap</p>
                    <p class="font-bold"
                       :class="chartSummary.total >= {{ $plan->planned_qty }} ? 'text-green-600' : 'text-orange-500'"
                       x-text="(chartSummary.total - {{ $plan->planned_qty }} >= 0 ? '+' : '') + (chartSummary.total - {{ $plan->planned_qty }})">—</p>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- ── Today's Activity Timeline ────────────────────────────────── --}}
    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-sm font-semibold text-gray-900">Today's Activity Timeline</h3>
                <p class="text-xs text-gray-400 mt-0.5" x-text="timelineWindow"></p>
            </div>
            <div class="flex items-center gap-2">
                <select x-model="selectedShiftId" @change="currentShiftName = selectedShiftId ? (shifts.find(s=>s.id==selectedShiftId)?.name||'') : 'All Day'; loadTimeline(); loadChartSummary()" class="text-xs rounded-lg border border-gray-200 px-2.5 py-1.5 text-gray-700 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                    <option value="">All Day (24h)</option>
                    @foreach($shifts as $shift)
                    <option value="{{ $shift->id }}">{{ $shift->name }} ({{ substr($shift->start_time,0,5) }}–{{ substr($shift->end_time,0,5) }})</option>
                    @endforeach
                </select>
                <div x-show="timelineLoading" class="text-xs text-gray-400">
                    <svg class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                    </svg>
                </div>
            </div>
        </div>

        {{-- Summary pills --}}
        <template x-if="timeline && timeline.segments.length > 0">
            <div>
                <div class="flex flex-wrap gap-2 mb-3 text-[11px]">
                    <template x-if="timeline.summary_min.running > 0">
                        <span class="flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-1 text-green-700 font-medium">
                            <span class="h-2 w-2 rounded-full bg-green-500 shrink-0"></span>
                            Running <span class="font-bold" x-text="fmtMin(timeline.summary_min.running)"></span>
                        </span>
                    </template>
                    <template x-if="timeline.summary_min.idle > 0">
                        <span class="flex items-center gap-1 rounded-full bg-yellow-100 px-2.5 py-1 text-yellow-700 font-medium">
                            <span class="h-2 w-2 rounded-full bg-yellow-400 shrink-0"></span>
                            Idle <span class="font-bold" x-text="fmtMin(timeline.summary_min.idle)"></span>
                        </span>
                    </template>
                    <template x-if="timeline.summary_min.alarm > 0">
                        <span class="flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-1 text-red-700 font-medium">
                            <span class="h-2 w-2 rounded-full bg-red-500 shrink-0"></span>
                            Alarm <span class="font-bold" x-text="fmtMin(timeline.summary_min.alarm)"></span>
                        </span>
                    </template>
                    <template x-if="timeline.summary_min.offline > 0">
                        <span class="flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1 text-gray-500 font-medium">
                            <span class="h-2 w-2 rounded-full bg-gray-400 shrink-0"></span>
                            Offline <span class="font-bold" x-text="fmtMin(timeline.summary_min.offline)"></span>
                        </span>
                    </template>
                </div>

                {{-- Timeline bar --}}
                <div class="flex h-9 w-full rounded-xl overflow-hidden border border-gray-200 shadow-inner">
                    <template x-for="seg in timeline.segments" :key="seg.from_min + '-' + seg.state">
                        <div
                            :style="`width:${seg.duration_min/timeline.total_min*100}%;background:${segColor(seg.state)}`"
                            :title="`${seg.state.toUpperCase()} · ${seg.from_label}–${seg.to_label} (${seg.duration_min}min)`"
                            class="h-full relative group cursor-default hover:opacity-80 transition-opacity"
                        ></div>
                    </template>
                </div>

                {{-- Time axis --}}
                <div class="relative mt-1 h-4 text-[10px] text-gray-400 select-none">
                    <template x-for="tick in timelineTicks" :key="tick.min">
                        <span class="absolute -translate-x-1/2" :style="`left:${tick.pct}%`" x-text="tick.label"></span>
                    </template>
                </div>
            </div>
        </template>

        <template x-if="!timelineLoading && (!timeline || timeline.segments.length === 0)">
            <div class="flex items-center justify-center h-14 text-gray-400 text-sm">
                No data for this period
            </div>
        </template>
    </div>

    {{-- ── View All Jobs link ──────────────────────────────────────────── --}}
    <div class="text-center">
        <a href="{{ route('employee.jobs.index') }}"
           class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-indigo-700 transition-colors">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            View All My Jobs
        </a>
    </div>

</div>
@endsection

@push('scripts')
<script>
function employeeDashboard(token, machineId, factoryId, shifts) {
    return {
        token, machineId, factoryId,
        shifts: shifts || [],

        liveData:        {},
        iotStatus:       'offline',
        lastSeen:        '—',
        dotClass:        'bg-gray-400',
        badgeClass:      'bg-gray-100 text-gray-600',
        currentShiftName: '',

        timeline:        null,
        timelineLoading: false,
        timelineWindow:  '',
        selectedShiftId: '',

        chartSummary:    { total: 0, rejects: 0 },

        _pollTimer: null,

        // ── Computed ──────────────────────────────────────────

        get timelineTicks() {
            if (!this.timeline || !this.timeline.total_min) return [];
            const total = this.timeline.total_min;
            const step  = total <= 90 ? 15 : total <= 240 ? 30 : 60;
            const [sh, sm] = this.timeline.window_from.split(':').map(Number);
            const ticks = [];
            for (let m = 0; m <= total; m += step) {
                const abs = sh * 60 + sm + m;
                const h   = Math.floor(abs / 60) % 24;
                const mm  = abs % 60;
                ticks.push({
                    min:   m,
                    pct:   m / total * 100,
                    label: `${String(h).padStart(2,'0')}:${String(mm).padStart(2,'0')}`,
                });
            }
            return ticks;
        },

        // ── Lifecycle ─────────────────────────────────────────

        init() {
            this.autoSelectShift();
            this.fetchStatus();
            this.loadTimeline();
            this.loadChartSummary();
            this._pollTimer = setInterval(() => this.fetchStatus(), 10000);
        },

        autoSelectShift() {
            if (!this.shifts.length) return;
            const now    = new Date();
            const nowMin = now.getHours() * 60 + now.getMinutes();
            const active = this.shifts.find(s => {
                const [sh, sm] = (s.start_time || '00:00').split(':').map(Number);
                const [eh, em] = (s.end_time   || '00:00').split(':').map(Number);
                const startMin = sh * 60 + sm;
                const endMin   = eh * 60 + em;
                return endMin > startMin
                    ? nowMin >= startMin && nowMin < endMin
                    : nowMin >= startMin || nowMin < endMin;
            });
            if (active) {
                this.selectedShiftId  = String(active.id);
                this.currentShiftName = active.name;
            }
        },

        // ── Data fetching ─────────────────────────────────────

        async fetchStatus() {
            try {
                const params = factoryId ? `?factory_id=${factoryId}` : '';
                const res    = await fetch(`/api/v1/iot/status${params}`, {
                    headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
                });
                const json = await res.json();
                const m    = (json.data || []).find(d => d.id === machineId);
                if (m) {
                    this.liveData   = m;
                    this.iotStatus  = m.iot_status || 'offline';
                    this.lastSeen   = m.last_seen ? new Date(m.last_seen).toLocaleTimeString() : 'No data';
                    this.dotClass   = this.statusDot(m.iot_status);
                    this.badgeClass = this.statusBadge(m.iot_status);
                }
            } catch { /* silent */ }
        },

        async loadTimeline() {
            this.timelineLoading = true;
            this.timeline        = null;
            const today = new Date().toISOString().split('T')[0];
            try {
                let url;
                if (this.selectedShiftId) {
                    url = `/api/v1/iot/machines/${machineId}/timeline?shift_id=${this.selectedShiftId}&date=${today}`;
                } else {
                    url = `/api/v1/iot/machines/${machineId}/timeline?hours=24`;
                }
                const res  = await fetch(url, {
                    headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
                });
                const data = await res.json();
                this.timeline       = data;
                this.timelineWindow = data.window_from + ' – ' + data.window_to;
            } catch { /* silent */ } finally {
                this.timelineLoading = false;
            }
        },

        async loadChartSummary() {
            const today = new Date().toISOString().split('T')[0];
            try {
                const url = this.selectedShiftId
                    ? `/api/v1/iot/machines/${machineId}/chart?shift_id=${this.selectedShiftId}&date=${today}`
                    : `/api/v1/iot/machines/${machineId}/chart?hours=24`;
                const res  = await fetch(url, {
                    headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
                });
                const data = await res.json();
                this.chartSummary = {
                    total:   data.summary?.total_parts   || 0,
                    rejects: data.summary?.total_rejects || 0,
                };
            } catch { /* silent */ }
        },

        // ── Helpers ───────────────────────────────────────────

        fmtMin(m) {
            if (!m) return '0m';
            const h = Math.floor(m / 60);
            const r = m % 60;
            return h > 0 ? `${h}h ${r > 0 ? r + 'm' : ''}` : `${r}m`;
        },

        segColor(state) {
            return { running: '#22c55e', idle: '#eab308', alarm: '#ef4444', standby: '#60a5fa', offline: '#e2e8f0' }[state] || '#e2e8f0';
        },

        statusDot(s) {
            return { running: 'bg-green-500', idle: 'bg-yellow-400', alarm: 'bg-red-500', standby: 'bg-blue-400', offline: 'bg-gray-400' }[s] || 'bg-gray-400';
        },

        statusBadge(s) {
            return { running: 'bg-green-100 text-green-700', idle: 'bg-yellow-100 text-yellow-700', alarm: 'bg-red-100 text-red-700 alarm-blink', standby: 'bg-blue-100 text-blue-700', offline: 'bg-gray-100 text-gray-500' }[s] || 'bg-gray-100 text-gray-600';
        },
    };
}
</script>
@endpush

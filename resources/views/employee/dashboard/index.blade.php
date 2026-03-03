@extends('employee.layouts.app')
@section('title', 'My Machine')

@push('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
    @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }
    .alarm-blink { animation: blink .8s ease-in-out infinite; }
    @keyframes ping-slow { 0%{transform:scale(1);opacity:.75} 100%{transform:scale(2);opacity:0} }
    .ping-slow { animation: ping-slow 1.5s ease-out infinite; }
</style>
@endpush

@section('content')
<div
    x-data="employeeDashboard('{{ $apiToken }}', {{ $machineId }}, {{ $factoryId ?? 'null' }})"
    x-init="init()"
    class="space-y-5"
>

    {{-- ── Machine Live Status Card ───────────────────────────────── --}}
    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 overflow-hidden">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div class="flex items-center gap-3">
                {{-- Status dot --}}
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

        {{-- Live KPI grid --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-y sm:divide-y-0 divide-gray-100">

            {{-- Cycle State --}}
            <div class="px-5 py-4 text-center">
                <div class="flex items-center justify-center gap-1.5 mb-1">
                    <div :class="liveData.cycle_state ? 'bg-green-500' : 'bg-gray-300'"
                         class="h-2.5 w-2.5 rounded-full transition-colors"></div>
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-400">Cycle</p>
                </div>
                <p class="text-xl font-bold"
                   :class="liveData.cycle_state ? 'text-green-600' : 'text-gray-400'"
                   x-text="liveData.cycle_state ? 'Active' : 'Stopped'"></p>
            </div>

            {{-- Auto Mode --}}
            <div class="px-5 py-4 text-center">
                <div class="flex items-center justify-center gap-1.5 mb-1">
                    <div :class="liveData.auto_mode ? 'bg-blue-500' : 'bg-gray-300'"
                         class="h-2.5 w-2.5 rounded-full transition-colors"></div>
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-400">Auto</p>
                </div>
                <p class="text-xl font-bold"
                   :class="liveData.auto_mode ? 'text-blue-600' : 'text-gray-400'"
                   x-text="liveData.auto_mode ? 'On' : 'Off'"></p>
            </div>

            {{-- Alarm --}}
            <div class="px-5 py-4 text-center">
                <div class="flex items-center justify-center gap-1.5 mb-1">
                    <div :class="(liveData.alarm_code > 0) ? 'bg-red-500 alarm-blink' : 'bg-gray-300'"
                         class="h-2.5 w-2.5 rounded-full"></div>
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-400">Alarm</p>
                </div>
                <p class="text-xl font-bold"
                   :class="(liveData.alarm_code > 0) ? 'text-red-600 alarm-blink' : 'text-gray-400'"
                   x-text="(liveData.alarm_code > 0) ? '#' + liveData.alarm_code : 'None'"></p>
            </div>

            {{-- Last Signal --}}
            <div class="px-5 py-4 text-center">
                <p class="text-xs font-medium uppercase tracking-wider text-gray-400 mb-1">Last Signal</p>
                <p class="text-sm font-semibold text-gray-700 font-mono" x-text="lastSeen"></p>
            </div>
        </div>
    </div>

    {{-- ── Today's Machine Timeline ────────────────────────────────── --}}
    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-sm font-semibold text-gray-900">Today's Activity Timeline</h3>
                <p class="text-xs text-gray-400 mt-0.5" x-text="timelineWindow"></p>
            </div>
            <div class="flex items-center gap-2">
                <select x-model="selectedShiftId" @change="loadTimeline()" class="text-xs rounded-lg border border-gray-200 px-2.5 py-1.5 text-gray-700 focus:outline-none focus:ring-1 focus:ring-indigo-400">
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

    {{-- ── Today's Production Chart ─────────────────────────────────── --}}
    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-gray-900">Parts Produced / Hour</h3>
            <div class="flex items-center gap-3 text-xs text-gray-500">
                <span>Total: <span class="font-bold text-gray-900" x-text="chartSummary.total"></span></span>
                <span>Rejects: <span class="font-bold text-red-600" x-text="chartSummary.rejects"></span></span>
            </div>
        </div>
        <template x-if="chartData && chartData.labels.length > 0">
            <div style="height:180px;position:relative">
                <canvas id="emp-parts-chart"></canvas>
            </div>
        </template>
        <template x-if="!chartLoading && (!chartData || chartData.labels.length === 0)">
            <div class="flex items-center justify-center h-32 text-gray-400 text-sm">No production data for this period</div>
        </template>
    </div>

    {{-- ── Assigned Jobs (today + next 2 days) ─────────────────────── --}}
    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900">My Jobs — Today &amp; Upcoming</h3>
            <a href="{{ route('employee.jobs.index') }}" class="text-xs font-medium text-indigo-600 hover:underline">View all →</a>
        </div>

        @forelse($plans as $plan)
        @php
            $goodQty = $plan->totalGoodQty();
            $pct     = $plan->planned_qty > 0 ? min(100, round($goodQty / $plan->planned_qty * 100)) : 0;
            $statusColors = [
                'in_progress' => 'bg-indigo-100 text-indigo-700 border-indigo-200',
                'scheduled'   => 'bg-blue-100 text-blue-700 border-blue-200',
                'draft'       => 'bg-gray-100 text-gray-600 border-gray-200',
                'completed'   => 'bg-green-100 text-green-700 border-green-200',
                'cancelled'   => 'bg-red-100 text-red-600 border-red-200',
            ];
            $cardBorder = $plan->status === 'in_progress' ? 'border-l-4 border-l-indigo-500' : '';
        @endphp
        <div class="px-5 py-4 border-b border-gray-50 hover:bg-gray-50 transition-colors {{ $cardBorder }}">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="font-semibold text-gray-900 text-sm truncate">{{ $plan->part?->name ?? 'Unknown Part' }}</p>
                    <p class="text-xs text-gray-500">
                        {{ $plan->part?->part_number }}
                        &middot; {{ $plan->shift?->name }}
                        &middot; {{ $plan->planned_date->format('d M') }}
                    </p>
                </div>
                <span class="shrink-0 text-xs font-semibold px-2.5 py-1 rounded-full border
                             {{ $statusColors[$plan->status] ?? 'bg-gray-100 text-gray-600 border-gray-200' }}">
                    {{ ucfirst(str_replace('_', ' ', $plan->status)) }}
                </span>
            </div>

            <div class="mt-3 grid grid-cols-3 gap-2 text-xs text-gray-600">
                <div><span class="text-gray-400">Planned:</span> <span class="font-semibold">{{ number_format($plan->planned_qty) }}</span></div>
                <div><span class="text-gray-400">Good:</span> <span class="font-semibold text-green-700">{{ number_format($goodQty) }}</span></div>
                <div><span class="text-gray-400">Progress:</span> <span class="font-semibold">{{ $pct }}%</span></div>
            </div>

            @if($plan->planned_qty > 0)
            <div class="mt-2.5 h-1.5 w-full rounded-full bg-gray-200 overflow-hidden">
                <div class="h-1.5 rounded-full {{ $pct >= 100 ? 'bg-green-500' : ($pct >= 60 ? 'bg-indigo-500' : 'bg-yellow-400') }}"
                     style="width: {{ $pct }}%"></div>
            </div>
            @endif
        </div>
        @empty
        <div class="flex flex-col items-center justify-center py-12 text-gray-400">
            <svg class="h-10 w-10 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <p class="text-sm">No jobs scheduled for today or the next 2 days.</p>
        </div>
        @endforelse
    </div>

</div>
@endsection

@push('scripts')
<script>
function employeeDashboard(token, machineId, factoryId) {
    return {
        token, machineId, factoryId,

        liveData:        {},
        iotStatus:       'offline',
        lastSeen:        '—',
        dotClass:        'bg-gray-400',
        badgeClass:      'bg-gray-100 text-gray-600',

        timeline:        null,
        timelineLoading: false,
        timelineWindow:  '',
        selectedShiftId: '',

        chartData:       null,
        chartLoading:    false,
        chartSummary:    { total: 0, rejects: 0 },

        _chart:          null,
        _pollTimer:      null,

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
            this.fetchStatus();
            this.loadTimeline();
            this.loadChart();
            this._pollTimer = setInterval(() => this.fetchStatus(), 10000);
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
                    this.liveData  = m;
                    this.iotStatus = m.iot_status || 'offline';
                    this.lastSeen  = m.last_seen ? new Date(m.last_seen).toLocaleTimeString() : 'No data';
                    this.dotClass  = this.statusDot(m.iot_status);
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

        async loadChart() {
            this.chartLoading = true;
            const today = new Date().toISOString().split('T')[0];
            try {
                let url;
                if (this.selectedShiftId) {
                    url = `/api/v1/iot/machines/${machineId}/chart?shift_id=${this.selectedShiftId}&date=${today}`;
                } else {
                    url = `/api/v1/iot/machines/${machineId}/chart?hours=24`;
                }
                const res  = await fetch(url, {
                    headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
                });
                this.chartData = await res.json();
                this.chartSummary = {
                    total:   this.chartData.summary?.total_parts   || 0,
                    rejects: this.chartData.summary?.total_rejects || 0,
                };
            } catch { /* silent */ } finally {
                this.chartLoading = false;
            }
            await this.$nextTick();
            this.renderChart();
        },

        renderChart() {
            this._chart?.destroy();
            this._chart = null;

            if (!this.chartData || !this.chartData.labels.length) return;

            const ctx = document.getElementById('emp-parts-chart');
            if (!ctx) return;

            this._chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: this.chartData.labels,
                    datasets: [
                        {
                            label: 'Good Parts',
                            data: this.chartData.parts_per_hour.map(
                                (v, i) => Math.max(0, v - (this.chartData.rejects_per_hour[i] || 0))
                            ),
                            backgroundColor: 'rgba(99,102,241,0.75)',
                            borderRadius: 4,
                            stack: 'parts',
                        },
                        {
                            label: 'Rejects',
                            data: this.chartData.rejects_per_hour,
                            backgroundColor: 'rgba(239,68,68,0.65)',
                            borderRadius: 4,
                            stack: 'parts',
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top', labels: { font: { size: 11 }, boxWidth: 12 } },
                    },
                    scales: {
                        x: { stacked: true, ticks: { maxTicksLimit: 8, font: { size: 10 } } },
                        y: { stacked: true, beginAtZero: true, ticks: { precision: 0, font: { size: 10 } } },
                    },
                },
            });
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

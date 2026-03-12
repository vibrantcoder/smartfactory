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
                <span class="text-xs text-gray-400 font-mono" x-text="lastSeen"></span>
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
                   x-text="liveData.cycle_state ? 'Active' : 'Off'"></p>
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

            {{-- Current Shift --}}
            <div class="px-5 py-4 text-center">
                <p class="text-xs font-medium uppercase tracking-wider text-gray-400 mb-1">Shift</p>
                <p class="text-sm font-bold text-gray-700 leading-tight truncate" x-text="currentShiftName || '—'"></p>
                <p class="text-[11px] text-gray-400 mt-0.5 font-mono" x-text="lastSeen"></p>
            </div>
        </div>
    </div>

    {{-- ── Today's Jobs ─────────────────────────────────────────────── --}}
    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-900">My Jobs — Today &amp; Upcoming</h3>
            <a href="{{ route('employee.jobs.index') }}" class="text-xs font-medium text-indigo-600 hover:underline">View all →</a>
        </div>

        @forelse($plans as $plan)
        @php
            $goodQty = $plan->totalGoodQty();
            $pct     = $plan->planned_qty > 0 ? min(100, round($goodQty / $plan->planned_qty * 100)) : 0;
            $gap     = $goodQty - $plan->planned_qty;
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

            {{-- Top row: part name + status badge --}}
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="font-semibold text-gray-900 text-sm truncate">{{ $plan->part?->name ?? 'Unknown Part' }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        {{ $plan->part?->part_number }}
                        &middot; {{ $plan->shift?->name }}
                        &middot; {{ \Carbon\Carbon::parse($plan->planned_date)->format('d M') }}
                    </p>
                </div>
                <span class="shrink-0 text-xs font-semibold px-2.5 py-1 rounded-full border
                             {{ $statusColors[$plan->status] ?? 'bg-gray-100 text-gray-600 border-gray-200' }}">
                    {{ ucfirst(str_replace('_', ' ', $plan->status)) }}
                </span>
            </div>

            {{-- Progress bar --}}
            @if($plan->planned_qty > 0)
            <div class="mt-3">
                <div class="flex items-center justify-between text-xs text-gray-500 mb-1">
                    <span>Produced: <strong class="text-gray-900">{{ number_format($goodQty) }}</strong> / {{ number_format($plan->planned_qty) }} pcs</span>
                    <span class="{{ $pct >= 100 ? 'text-green-600 font-bold' : 'text-gray-500' }}">{{ $pct }}%</span>
                </div>
                <div class="h-2 w-full rounded-full bg-gray-200 overflow-hidden">
                    <div class="h-2 rounded-full transition-all {{ $pct >= 100 ? 'bg-green-500' : ($pct >= 60 ? 'bg-indigo-500' : 'bg-yellow-400') }}"
                         style="width: {{ $pct }}%"></div>
                </div>
                @if($plan->status === 'in_progress')
                <p class="text-[11px] mt-1.5 {{ $gap >= 0 ? 'text-green-600' : 'text-orange-500' }}">
                    {{ $gap >= 0 ? 'Ahead by ' . number_format($gap) . ' pcs' : number_format(abs($gap)) . ' pcs remaining' }}
                </p>
                @endif
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
function employeeDashboard(token, machineId, factoryId, shifts) {
    return {
        token, machineId, factoryId,
        shifts: shifts || [],

        liveData:         {},
        iotStatus:        'offline',
        lastSeen:         '—',
        dotClass:         'bg-gray-400',
        badgeClass:       'bg-gray-100 text-gray-600',
        currentShiftName: '',

        _pollTimer: null,

        init() {
            this.autoSelectShift();
            this.fetchStatus();
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
                this.currentShiftName = active.name;
            }
        },

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

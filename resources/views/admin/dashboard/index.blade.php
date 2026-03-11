{{--
    Real-Time Production Dashboard
    ================================
    Polls API every 15 s via Alpine.js:
      - GET /api/v1/machines?per_page=100
      - GET /api/v1/analytics/factories/{id}/daily-targets
      - GET /api/v1/downtimes?per_page=10

    Variables from DashboardController:
      $factoryId   — int|null
      $factoryName — string
      $factories   — Collection (super-admin only, for selector)
      $apiToken    — string (Sanctum plaintext token from session)
--}}
@extends('admin.layouts.app')

@section('title', 'Dashboard')

@section('header-actions')
    {{-- Super-admin: factory selector --}}
    @if($factories->count())
    <form method="GET" action="{{ route('admin.dashboard') }}" class="flex items-center gap-2">
        <label for="factory-select" class="text-sm text-gray-500">Factory:</label>
        <select id="factory-select" name="factory_id" onchange="this.form.submit()"
                class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm shadow-sm
                       focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            @foreach($factories as $f)
                <option value="{{ $f->id }}" {{ $f->id == $factoryId ? 'selected' : '' }}>
                    {{ $f->name }}
                </option>
            @endforeach
        </select>
    </form>
    @endif
@endsection

@section('content')

<div
    x-data="dashboard({
        factoryId: {{ $factoryId ?? 'null' }},
        apiToken:  {{ $apiToken ? json_encode($apiToken) : 'null' }}
    })"
    x-init="init()"
>

    {{-- ── Status Bar ──────────────────────────────────────── --}}
    <div class="mb-6 flex items-center justify-between">
        <div class="flex items-center gap-3">
            {{-- Loading indicator --}}
            <div x-show="loading"
                 class="flex items-center gap-1.5 text-sm text-gray-500">
                <svg class="h-4 w-4 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                </svg>
                Loading…
            </div>

            {{-- Last updated --}}
            <div x-show="!loading && lastUpdated"
                 class="text-sm text-gray-500">
                Updated <span x-text="timeAgo(lastUpdated)" class="font-medium text-gray-700"></span>
            </div>
        </div>

        <div class="flex items-center gap-3">
            {{-- Countdown ring --}}
            <div x-show="!loading" class="flex items-center gap-1.5 text-xs text-gray-400">
                <svg class="h-4 w-4" viewBox="0 0 36 36">
                    <circle cx="18" cy="18" r="15.9" fill="none" stroke="#e5e7eb" stroke-width="3"/>
                    <circle cx="18" cy="18" r="15.9" fill="none" stroke="#6366f1" stroke-width="3"
                            stroke-dasharray="100" :stroke-dashoffset="100 - countdown"
                            stroke-linecap="round" transform="rotate(-90 18 18)"/>
                </svg>
                <span x-text="Math.ceil((100 - countdown) * 15 / 100) + 's'"></span>
            </div>

            <button @click="refresh()"
                    :disabled="loading"
                    class="flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5
                           text-sm text-gray-600 shadow-sm hover:bg-gray-50 disabled:opacity-50 transition-colors">
                <svg class="h-4 w-4" :class="loading ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Refresh
            </button>
        </div>
    </div>

    {{-- ── Error Alert ─────────────────────────────────────── --}}
    <template x-if="error">
        <div class="mb-6 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <svg class="mt-0.5 h-4 w-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10A8 8 0 112 10a8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <span x-text="error"></span>
        </div>
    </template>

    {{-- ── Row 1: KPI Cards ────────────────────────────────── --}}
    <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">

        {{-- Machines Online --}}
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Machines Online</p>
            <p class="mt-2 text-3xl font-bold text-gray-900">
                <span x-text="onlineCount"></span>
                <span class="text-lg text-gray-400">/ <span x-text="machines.length"></span></span>
            </p>
            <div class="mt-3 flex items-center gap-1.5">
                <div class="h-1.5 flex-1 rounded-full bg-gray-100">
                    <div class="h-1.5 rounded-full bg-green-500 transition-all duration-500"
                         :style="`width: ${machines.length ? (onlineCount / machines.length * 100) : 0}%`"></div>
                </div>
            </div>
        </div>

        {{-- Today's Plans --}}
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Active Plans</p>
            <p class="mt-2 text-3xl font-bold text-gray-900" x-text="targets.summary?.total_plans ?? '—'"></p>
            <p class="mt-1 text-sm text-gray-500">
                <span x-text="targets.summary?.on_target_count ?? 0" class="text-green-600 font-medium"></span>
                on target
            </p>
        </div>

        {{-- Good Qty --}}
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Good Qty Today</p>
            <p class="mt-2 text-3xl font-bold text-gray-900" x-text="targets.summary?.total_good_qty ?? '—'"></p>
            <p class="mt-1 text-sm text-gray-500">
                of <span x-text="targets.summary?.total_planned_qty ?? 0" class="font-medium text-gray-700"></span> planned
            </p>
        </div>

        {{-- Efficiency --}}
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Overall Efficiency</p>
            <p class="mt-2 text-3xl font-bold"
               :class="efficiencyColor(targets.summary?.overall_efficiency_pct)">
                <span x-text="targets.summary?.overall_efficiency_pct != null
                    ? targets.summary.overall_efficiency_pct.toFixed(1) + '%'
                    : '—'"></span>
            </p>
            <p class="mt-1 text-sm text-gray-500">vs target 85%</p>
        </div>
    </div>

    {{-- ── Row 2: Machine Grid + Downtimes ─────────────────── --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Machine Status Grid (2/3 width) --}}
        <div class="lg:col-span-2 rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            <div class="border-b border-gray-100 px-5 py-4 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700">Machine Status</h2>
                <span class="text-xs text-gray-400" x-text="machines.length + ' machines'"></span>
            </div>

            {{-- Skeleton --}}
            <template x-if="loading && machines.length === 0">
                <div class="grid grid-cols-2 gap-3 p-5 sm:grid-cols-3">
                    <template x-for="i in 6" :key="i">
                        <div class="h-20 animate-pulse rounded-lg bg-gray-100"></div>
                    </template>
                </div>
            </template>

            <template x-if="machines.length > 0">
                <div class="grid grid-cols-2 gap-3 p-5 sm:grid-cols-3">
                    <template x-for="m in machines" :key="m.id">
                        <div class="rounded-lg border p-3 transition-colors"
                             :class="machineCardClass(m.status)">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-sm font-medium text-gray-900 truncate" x-text="m.name"></p>
                                <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                                      :class="statusBadgeClass(m.status)"
                                      x-text="m.status"></span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500 truncate" x-text="m.type ?? 'Unknown type'"></p>
                        </div>
                    </template>
                </div>
            </template>

            <template x-if="!loading && machines.length === 0">
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <p class="text-sm text-gray-400">No machines found</p>
                </div>
            </template>
        </div>

        {{-- Recent Downtimes (1/3 width) --}}
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            <div class="border-b border-gray-100 px-5 py-4 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700">Recent Downtimes</h2>
                <span class="text-xs text-gray-400" x-text="downtimes.length + ' shown'"></span>
            </div>

            {{-- Skeleton --}}
            <template x-if="loading && downtimes.length === 0">
                <div class="space-y-2 p-4">
                    <template x-for="i in 4" :key="i">
                        <div class="h-14 animate-pulse rounded-lg bg-gray-100"></div>
                    </template>
                </div>
            </template>

            <template x-if="downtimes.length > 0">
                <ul class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
                    <template x-for="d in downtimes" :key="d.id">
                        <li class="px-4 py-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-gray-900"
                                       x-text="machineName(d.machine_id)"></p>
                                    <p class="mt-0.5 truncate text-xs text-gray-500"
                                       x-text="d.reason_name ?? ('Reason #' + d.downtime_reason_id)"></p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                                          :class="downtimeCategoryClass(d.category)"
                                          x-text="d.category ?? 'unknown'"></span>
                                    <p class="mt-1 text-xs text-gray-400" x-text="timeAgo(d.started_at)"></p>
                                </div>
                            </div>
                            <template x-if="!d.ended_at">
                                <span class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-red-600">
                                    <span class="h-1.5 w-1.5 rounded-full bg-red-500 animate-pulse"></span>
                                    Active
                                </span>
                            </template>
                        </li>
                    </template>
                </ul>
            </template>

            <template x-if="!loading && downtimes.length === 0">
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <p class="text-sm text-gray-400">No recent downtimes</p>
                </div>
            </template>
        </div>
    </div>

    {{-- ── Row 3: Production Plans Table ───────────────────── --}}
    <template x-if="targets.plans && targets.plans.length > 0">
        <div class="mt-6 rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            <div class="border-b border-gray-100 px-5 py-4">
                <h2 class="text-sm font-semibold text-gray-700">
                    Today's Production Plans
                    <span class="ml-2 text-xs text-gray-400" x-text="targets.date"></span>
                </h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-gray-100 bg-gray-50">
                        <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                            <th class="px-5 py-3">Part</th>
                            <th class="px-5 py-3">Machine</th>
                            <th class="px-5 py-3">Shift</th>
                            <th class="px-5 py-3 text-right">Planned</th>
                            <th class="px-5 py-3 text-right">Good</th>
                            <th class="px-5 py-3 text-right">Efficiency</th>
                            <th class="px-5 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <template x-for="plan in targets.plans" :key="plan.plan_id">
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-3 font-medium text-gray-900" x-text="plan.part_number"></td>
                                <td class="px-5 py-3 text-gray-600" x-text="plan.machine_name ?? '—'"></td>
                                <td class="px-5 py-3 text-gray-600" x-text="plan.shift_name ?? '—'"></td>
                                <td class="px-5 py-3 text-right text-gray-700" x-text="plan.planned_qty"></td>
                                <td class="px-5 py-3 text-right font-medium text-gray-900" x-text="plan.actual_good_qty ?? 0"></td>
                                <td class="px-5 py-3 text-right">
                                    <span class="font-medium" :class="efficiencyColor(plan.efficiency_pct)"
                                          x-text="plan.efficiency_pct != null ? plan.efficiency_pct.toFixed(1) + '%' : '—'"></span>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                                          :class="planStatusClass(plan.status)"
                                          x-text="plan.status"></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </template>

</div>

@endsection

@push('scripts')
<script>
function dashboard({ factoryId, apiToken }) {
    return {
        machines:    [],
        targets:     {},
        downtimes:   [],
        loading:     false,
        error:       null,
        lastUpdated: null,
        countdown:   0,

        _timer:    null,
        _ticker:   null,

        init() {
            if (!factoryId) return;
            this.refresh();
            this._timer  = setInterval(() => this.refresh(), 15000);
            this._ticker = setInterval(() => {
                this.countdown = Math.min(100, this.countdown + (100 / 150));
            }, 100);
        },

        get onlineCount() {
            return this.machines.filter(m => m.status === 'active').length;
        },

        async refresh() {
            this.loading   = true;
            this.error     = null;
            this.countdown = 0;

            const headers = {
                'Accept':        'application/json',
                'Authorization': apiToken ? `Bearer ${apiToken}` : '',
            };

            const today = new Date().toISOString().slice(0, 10);

            try {
                const [machRes, targRes, downRes] = await Promise.all([
                    fetch('/api/v1/machines?per_page=100', { headers }),
                    fetch(`/api/v1/analytics/factories/${factoryId}/daily-targets?date=${today}`, { headers }),
                    fetch('/api/v1/downtimes?per_page=10', { headers }),
                ]);

                if (machRes.ok) {
                    const j = await machRes.json();
                    this.machines = j.data ?? j;
                }
                if (targRes.ok) {
                    const j = await targRes.json();
                    this.targets = j.data ?? j;
                }
                if (downRes.ok) {
                    const j = await downRes.json();
                    this.downtimes = j.data ?? j;
                }

                this.lastUpdated = new Date();
            } catch (e) {
                this.error = 'Failed to load dashboard data. Will retry in 15 seconds.';
            } finally {
                this.loading = false;
            }
        },

        machineName(id) {
            const m = this.machines.find(x => x.id === id);
            return m ? m.name : `Machine #${id}`;
        },

        timeAgo(dateStr) {
            if (!dateStr) return '';
            const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
            if (diff < 60)   return diff + 's ago';
            if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
            if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
            return Math.floor(diff / 86400) + 'd ago';
        },

        // ── Style helpers ─────────────────────────────────────

        machineCardClass(status) {
            const map = {
                active:      'border-green-200 bg-green-50',
                maintenance: 'border-orange-200 bg-orange-50',
                retired:     'border-gray-200 bg-gray-50',
            };
            return map[status] ?? 'border-gray-200 bg-gray-50';
        },

        statusBadgeClass(status) {
            const map = {
                active:      'bg-green-100 text-green-800',
                maintenance: 'bg-orange-100 text-orange-800',
                retired:     'bg-gray-100 text-gray-500',
            };
            return map[status] ?? 'bg-gray-100 text-gray-600';
        },

        downtimeCategoryClass(category) {
            const map = {
                planned:     'bg-blue-100 text-blue-800',
                unplanned:   'bg-red-100 text-red-800',
                maintenance: 'bg-orange-100 text-orange-800',
            };
            return map[category] ?? 'bg-gray-100 text-gray-600';
        },

        planStatusClass(status) {
            const map = {
                in_progress: 'bg-blue-100 text-blue-800',
                scheduled:   'bg-yellow-100 text-yellow-800',
                completed:   'bg-green-100 text-green-800',
                cancelled:   'bg-gray-100 text-gray-500',
                draft:       'bg-gray-100 text-gray-500',
            };
            return map[status] ?? 'bg-gray-100 text-gray-600';
        },

        efficiencyColor(pct) {
            if (pct == null) return 'text-gray-400';
            if (pct >= 85)  return 'text-green-600';
            if (pct >= 60)  return 'text-yellow-600';
            return 'text-red-600';
        },
    };
}
</script>
@endpush

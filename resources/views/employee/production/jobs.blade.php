@extends('employee.layouts.app')
@section('title', 'My Jobs')

@section('content')
<div
    x-data="jobsActuals('{{ $apiToken }}')"
    x-init="init()"
>

<div class="mb-5 flex items-center justify-between">
    <div>
        <h2 class="text-lg font-bold text-gray-900">My Production Jobs</h2>
        <p class="text-xs text-gray-500 mt-0.5">Last 7 days &amp; next 14 days</p>
    </div>
    <a href="{{ route('employee.dashboard') }}"
       class="text-xs font-medium text-indigo-600 hover:underline">
        ← Back to Dashboard
    </a>
</div>

{{-- Legend --}}
<div class="flex flex-wrap gap-2 mb-4 text-xs text-gray-500">
    <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-100 px-2.5 py-1 text-indigo-700 font-medium">
        <span class="h-2 w-2 rounded-full bg-indigo-500"></span>In Progress
    </span>
    <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-100 px-2.5 py-1 text-blue-700 font-medium">
        <span class="h-2 w-2 rounded-full bg-blue-500"></span>Scheduled
    </span>
    <span class="inline-flex items-center gap-1.5 rounded-full bg-green-100 px-2.5 py-1 text-green-700 font-medium">
        <span class="h-2 w-2 rounded-full bg-green-500"></span>Completed
    </span>
    <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-1 text-gray-600 font-medium">
        <span class="h-2 w-2 rounded-full bg-gray-400"></span>Draft / Cancelled
    </span>
</div>

{{-- Plan cards --}}
<div class="space-y-3">
@forelse($plans as $plan)
@php
    $goodQty = $plan->totalGoodQty();
    $rejects = $plan->actuals->sum('defect_qty');
    $pct     = $plan->planned_qty > 0 ? min(100, round($goodQty / $plan->planned_qty * 100)) : 0;

    $statusDot = match($plan->status) {
        'in_progress' => 'bg-indigo-500',
        'scheduled'   => 'bg-blue-500',
        'completed'   => 'bg-green-500',
        'cancelled'   => 'bg-red-400',
        default       => 'bg-gray-400',
    };
    $statusBadge = match($plan->status) {
        'in_progress' => 'bg-indigo-100 text-indigo-700',
        'scheduled'   => 'bg-blue-100 text-blue-700',
        'completed'   => 'bg-green-100 text-green-700',
        'cancelled'   => 'bg-red-100 text-red-600',
        default       => 'bg-gray-100 text-gray-600',
    };
    $leftBorder = $plan->status === 'in_progress' ? 'border-l-4 border-l-indigo-500' : '';
@endphp

<div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200 overflow-hidden {{ $leftBorder }}">
    <div class="px-5 py-4">
        {{-- Top row --}}
        <div class="flex items-start justify-between gap-3 mb-3">
            <div class="min-w-0">
                <p class="font-semibold text-gray-900 text-sm truncate">
                    {{ $plan->part?->name ?? 'Unknown Part' }}
                </p>
                <p class="text-xs text-gray-500 mt-0.5">
                    Part #{{ $plan->part?->part_number }}
                    &middot; Cycle: {{ $plan->part?->cycle_time_std ?? '—' }}s
                </p>
            </div>
            <div class="flex flex-col items-end gap-1.5 shrink-0">
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full {{ $statusBadge }}">
                    {{ ucfirst(str_replace('_', ' ', $plan->status)) }}
                </span>
                <span class="text-xs text-gray-400">{{ $plan->planned_date->format('d M Y') }}</span>
            </div>
        </div>

        {{-- Details grid --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
            <div class="rounded-lg bg-slate-50 p-2.5 text-center">
                <p class="font-bold text-base text-gray-900">{{ number_format($plan->planned_qty) }}</p>
                <p class="text-gray-400 mt-0.5">Planned</p>
            </div>
            <div class="rounded-lg bg-slate-50 p-2.5 text-center">
                <p class="font-bold text-base text-green-700">{{ number_format($goodQty) }}</p>
                <p class="text-gray-400 mt-0.5">Good</p>
            </div>
            <div class="rounded-lg bg-slate-50 p-2.5 text-center">
                <p class="font-bold text-base {{ $rejects > 0 ? 'text-red-600' : 'text-gray-400' }}">
                    {{ number_format($rejects) }}
                </p>
                <p class="text-gray-400 mt-0.5">Rejects</p>
            </div>
            <div class="rounded-lg bg-slate-50 p-2.5 text-center">
                <p class="font-bold text-base {{ $pct >= 100 ? 'text-green-700' : ($pct >= 60 ? 'text-indigo-600' : 'text-yellow-600') }}">
                    {{ $pct }}%
                </p>
                <p class="text-gray-400 mt-0.5">Attainment</p>
            </div>
        </div>

        {{-- Shift info --}}
        <div class="mt-3 flex items-center gap-4 text-xs text-gray-500">
            <span>
                <span class="font-medium">Shift:</span> {{ $plan->shift?->name ?? '—' }}
                @if($plan->shift)
                ({{ substr($plan->shift->start_time,0,5) }}–{{ substr($plan->shift->end_time,0,5) }})
                @endif
            </span>
        </div>

        {{-- Progress bar --}}
        @if($plan->planned_qty > 0 && in_array($plan->status, ['in_progress', 'scheduled', 'completed']))
        <div class="mt-3">
            <div class="h-2 w-full rounded-full bg-gray-200 overflow-hidden">
                <div class="h-2 rounded-full transition-all
                            {{ $pct >= 100 ? 'bg-green-500' : ($pct >= 60 ? 'bg-indigo-500' : 'bg-yellow-400') }}"
                     style="width: {{ $pct }}%"></div>
            </div>
        </div>
        @endif

        {{-- Record Output button (in_progress / scheduled only) --}}
        @if(in_array($plan->status, ['in_progress', 'scheduled']))
        <div class="mt-3 flex items-center justify-between">
            <button @click="openRecord({{ $plan->id }}, {{ $plan->planned_qty }}, '{{ $plan->part?->name }}')"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold text-white hover:bg-indigo-700">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Record Output
            </button>
            <span x-show="lastSaved == {{ $plan->id }}"
                  class="text-xs text-green-600 font-medium">Saved!</span>
        </div>
        @endif
    </div>
</div>
@empty
<div class="flex flex-col items-center justify-center py-16 text-gray-400">
    <svg class="h-12 w-12 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
    </svg>
    <p class="text-sm font-medium">No jobs found</p>
    <p class="text-xs mt-1">No production plans for your machine in the past 7 or next 14 days.</p>
</div>
@endforelse
</div>

{{-- Pagination --}}
@if($plans->hasPages())
<div class="mt-5">{{ $plans->links() }}</div>
@endif

{{-- ── Record Output Modal ─────────────────────────────────────── --}}
<div x-show="showModal" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
    <div @click.stop class="w-full max-w-sm rounded-2xl bg-white shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
            <div>
                <h3 class="font-semibold text-gray-900 text-sm">Record Output</h3>
                <p class="text-xs text-gray-400 mt-0.5" x-text="modalPart"></p>
            </div>
            <button @click="showModal = false" class="text-gray-400 hover:text-gray-600">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="px-5 py-4 space-y-4">
            <div x-show="saveError" class="rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700" x-text="saveError"></div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Good Parts <span class="text-red-500">*</span></label>
                    <input type="number" x-model.number="form.actual_qty" min="0" :max="modalPlanned * 2"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                    <p class="text-xs text-gray-400 mt-0.5">Target: <span x-text="modalPlanned"></span></p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Defects / Rejects</label>
                    <input type="number" x-model.number="form.defect_qty" min="0"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Notes</label>
                <textarea x-model="form.notes" rows="2" placeholder="Optional notes…"
                          class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 resize-none"></textarea>
            </div>
        </div>
        <div class="flex gap-3 border-t border-gray-100 px-5 py-4">
            <button @click="showModal = false"
                    class="flex-1 rounded-lg border border-gray-200 py-2 text-sm text-gray-600 hover:bg-gray-50">
                Cancel
            </button>
            <button @click="saveRecord()" :disabled="saving"
                    class="flex-1 rounded-lg bg-indigo-600 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                <span x-text="saving ? 'Saving…' : 'Save Output'"></span>
            </button>
        </div>
    </div>
</div>

</div>{{-- end x-data --}}

@endsection

@push('scripts')
<script>
function jobsActuals(apiToken) {
    return {
        apiToken,
        showModal:   false,
        saving:      false,
        saveError:   null,
        lastSaved:   null,
        modalPlanId: null,
        modalPlanned: 0,
        modalPart:   '',
        form: { actual_qty: 0, defect_qty: 0, notes: '' },

        init() {},

        openRecord(planId, planned, partName) {
            this.modalPlanId  = planId;
            this.modalPlanned = planned;
            this.modalPart    = partName;
            this.saveError    = null;
            this.form = { actual_qty: 0, defect_qty: 0, notes: '' };
            this.showModal = true;
        },

        async saveRecord() {
            if (!this.form.actual_qty && this.form.actual_qty !== 0) {
                this.saveError = 'Please enter good parts count.';
                return;
            }
            this.saving    = true;
            this.saveError = null;
            try {
                const res = await fetch('/api/v1/production-actuals', {
                    method:  'POST',
                    headers: {
                        'Authorization': `Bearer ${this.apiToken}`,
                        'Content-Type':  'application/json',
                        'Accept':        'application/json',
                    },
                    body: JSON.stringify({
                        production_plan_id: this.modalPlanId,
                        actual_qty:         parseInt(this.form.actual_qty) || 0,
                        defect_qty:         parseInt(this.form.defect_qty) || 0,
                        notes:              this.form.notes || null,
                        recorded_at:        new Date().toISOString().slice(0,19).replace('T',' '),
                    }),
                });
                if (!res.ok) {
                    const err = await res.json();
                    this.saveError = err.message || JSON.stringify(err.errors || err);
                    this.saving = false;
                    return;
                }
                this.lastSaved = this.modalPlanId;
                this.showModal = false;
                // Reload to show updated totals
                setTimeout(() => window.location.reload(), 600);
            } catch(e) {
                this.saveError = e.message;
            }
            this.saving = false;
        },
    };
}
</script>
@endpush

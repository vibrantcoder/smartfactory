@extends('admin.layouts.app')
@section('title', 'Downtime Management')

@section('content')
<div
    x-data="downtimeManager('{{ $apiToken }}', {{ $factoryId ?? 'null' }}, {{ $machines->toJson() }}, {{ $reasons->toJson() }}, {{ $factories->toJson() }})"
    x-init="init()"
>

{{-- ── Header ─────────────────────────────────────────────────────── --}}
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div>
        <p class="text-xs text-gray-500 mt-0.5">Log machine stoppages · manage reason codes · impacts OEE Availability</p>
    </div>
    <div class="flex items-center gap-2">
        <template x-if="factories.length > 0">
            <select @change="currentFactoryId = $event.target.value; loadData(); loadReasons()"
                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All Factories</option>
                <template x-for="f in factories" :key="f.id">
                    <option :value="f.id" :selected="currentFactoryId == f.id" x-text="f.name"></option>
                </template>
            </select>
        </template>
        <template x-if="activeTab === 'events'">
            <button @click="openCreate()"
                    class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Log Downtime
            </button>
        </template>
        <template x-if="activeTab === 'reasons'">
            <button @click="openReasonCreate()"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Reason
            </button>
        </template>
    </div>
</div>

{{-- ── Tabs ─────────────────────────────────────────────────────────── --}}
<div class="flex gap-1 mb-5 border-b border-gray-200">
    <button @click="activeTab = 'events'"
            :class="activeTab === 'events' ? 'border-b-2 border-red-600 text-red-600' : 'text-gray-500 hover:text-gray-700'"
            class="px-4 py-2.5 text-sm font-medium -mb-px transition-colors">
        Downtime Events
    </button>
    <button @click="activeTab = 'reasons'"
            :class="activeTab === 'reasons' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-gray-500 hover:text-gray-700'"
            class="px-4 py-2.5 text-sm font-medium -mb-px transition-colors">
        Reason Codes
        <span class="ml-1.5 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600"
              x-text="reasons.length"></span>
    </button>
</div>

{{-- ══════════════════════════════════════════════════════ --}}
{{-- TAB: DOWNTIME EVENTS                                  --}}
{{-- ══════════════════════════════════════════════════════ --}}
<div x-show="activeTab === 'events'">

{{-- KPI cards --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200 p-4 text-center">
        <p class="text-2xl font-bold text-red-600" x-text="kpi.open"></p>
        <p class="text-xs text-gray-500 mt-1">Open Events</p>
    </div>
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200 p-4 text-center">
        <p class="text-2xl font-bold text-gray-800" x-text="kpi.today"></p>
        <p class="text-xs text-gray-500 mt-1">Today</p>
    </div>
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200 p-4 text-center">
        <p class="text-2xl font-bold text-amber-600" x-text="kpi.totalMinutes + ' min'"></p>
        <p class="text-xs text-gray-500 mt-1">Lost This Week</p>
    </div>
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200 p-4 text-center">
        <p class="text-2xl font-bold text-gray-800" x-text="kpi.topReason || '—'"></p>
        <p class="text-xs text-gray-500 mt-1">Top Cause</p>
    </div>
</div>

{{-- Filters --}}
<div class="flex flex-wrap items-center gap-3 mb-4">
    <select x-model="filterMachine" @change="loadData()"
            class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400">
        <option value="">All Machines</option>
        <template x-for="m in machines" :key="m.id">
            <option :value="m.id" x-text="m.name + ' (' + m.code + ')'"></option>
        </template>
    </select>
    <select x-model="filterStatus" @change="loadData()"
            class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400">
        <option value="">All Status</option>
        <option value="open">Open (in progress)</option>
        <option value="closed">Closed</option>
    </select>
    <input type="date" x-model="filterDate" @change="loadData()"
           class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400">
    <button x-show="filterMachine || filterStatus || filterDate"
            @click="filterMachine=''; filterStatus=''; filterDate=''; loadData()"
            class="text-xs text-gray-400 hover:text-gray-600">Clear filters</button>
    <div class="ml-auto text-xs text-gray-400" x-show="loading">Loading…</div>
</div>

{{-- Table --}}
<div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Machine</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Started</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Duration</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Reason / Category</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Description</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <template x-if="rows.length === 0 && !loading">
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-gray-400 text-sm">
                            No downtime events found. Use "Log Downtime" to record a stoppage.
                        </td>
                    </tr>
                </template>
                <template x-for="row in rows" :key="row.id">
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-900" x-text="machineName(row.machine_id)"></p>
                            <p class="text-xs text-gray-400" x-text="machineCode(row.machine_id)"></p>
                        </td>
                        <td class="px-4 py-3 text-gray-700 whitespace-nowrap" x-text="fmtDatetime(row.started_at)"></td>
                        <td class="px-4 py-3">
                            <template x-if="row.ended_at">
                                <span class="font-semibold" :class="row.duration_minutes >= 60 ? 'text-red-600' : 'text-amber-600'"
                                      x-text="row.duration_minutes + ' min'"></span>
                            </template>
                            <template x-if="!row.ended_at">
                                <span class="text-red-600 font-semibold animate-pulse">OPEN</span>
                            </template>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-gray-700" x-text="reasonName(row.downtime_reason_id) || '—'"></span>
                            <template x-if="row.category">
                                <span class="ml-1 text-xs rounded-full px-2 py-0.5"
                                      :class="row.category === 'planned' ? 'bg-blue-100 text-blue-700' : row.category === 'maintenance' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700'"
                                      x-text="row.category"></span>
                            </template>
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-xs max-w-xs truncate" x-text="row.description || '—'"></td>
                        <td class="px-4 py-3">
                            <template x-if="!row.ended_at">
                                <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700">
                                    <span class="h-1.5 w-1.5 rounded-full bg-red-500 animate-pulse"></span>
                                    Open
                                </span>
                            </template>
                            <template x-if="row.ended_at">
                                <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700">
                                    Closed
                                </span>
                            </template>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <template x-if="!row.ended_at">
                                    <button @click="closeDowntime(row)"
                                            class="rounded-lg bg-green-100 px-3 py-1 text-xs font-medium text-green-700 hover:bg-green-200">
                                        Close
                                    </button>
                                </template>
                                <button @click="openEdit(row)"
                                        class="rounded-lg bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600 hover:bg-gray-200">
                                    Edit
                                </button>
                                <button @click="deleteRow(row)"
                                        class="rounded-lg bg-red-50 px-3 py-1 text-xs font-medium text-red-500 hover:bg-red-100">
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
    <div x-show="meta && meta.last_page > 1"
         class="flex items-center justify-between border-t border-gray-100 px-4 py-3 text-xs text-gray-500">
        <span x-text="'Page ' + (meta?.current_page ?? 1) + ' of ' + (meta?.last_page ?? 1)"></span>
        <div class="flex gap-2">
            <button @click="page--; loadData()" :disabled="page <= 1"
                    class="rounded px-3 py-1 border border-gray-200 disabled:opacity-40">Prev</button>
            <button @click="page++; loadData()" :disabled="page >= (meta?.last_page ?? 1)"
                    class="rounded px-3 py-1 border border-gray-200 disabled:opacity-40">Next</button>
        </div>
    </div>
</div>
</div>{{-- end events tab --}}

{{-- ══════════════════════════════════════════════════════ --}}
{{-- TAB: DOWNTIME REASONS                                 --}}
{{-- ══════════════════════════════════════════════════════ --}}
<div x-show="activeTab === 'reasons'">

{{-- Search + filter --}}
<div class="flex flex-wrap items-center gap-3 mb-4">
    <input type="text" x-model="reasonSearch" placeholder="Search code or name…"
           class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400 w-56">
    <select x-model="reasonCategoryFilter"
            class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400">
        <option value="">All Categories</option>
        <option value="planned">Planned</option>
        <option value="unplanned">Unplanned</option>
        <option value="maintenance">Maintenance</option>
    </select>
    <select x-model="reasonActiveFilter"
            class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400">
        <option value="">All Status</option>
        <option value="active">Active only</option>
        <option value="inactive">Inactive only</option>
    </select>
    <span class="ml-auto text-xs text-gray-400"
          x-text="filteredReasons.length + ' reason(s)'"></span>
</div>

{{-- Reasons table --}}
<div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200 overflow-hidden">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Code</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Category</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <template x-if="filteredReasons.length === 0">
                <tr>
                    <td colspan="5" class="px-4 py-10 text-center text-gray-400 text-sm">
                        No reason codes found. Add one to start categorising downtimes.
                    </td>
                </tr>
            </template>
            <template x-for="r in filteredReasons" :key="r.id">
                <tr class="hover:bg-gray-50" :class="!r.is_active ? 'opacity-60' : ''">
                    <td class="px-4 py-3">
                        <span class="font-mono font-semibold text-indigo-700 text-xs bg-indigo-50 px-2 py-0.5 rounded"
                              x-text="r.code"></span>
                    </td>
                    <td class="px-4 py-3 font-medium text-gray-900" x-text="r.name"></td>
                    <td class="px-4 py-3">
                        <span class="text-xs rounded-full px-2.5 py-0.5 font-medium"
                              :class="{
                                  'bg-blue-100 text-blue-700':   r.category === 'planned',
                                  'bg-red-100 text-red-700':     r.category === 'unplanned',
                                  'bg-amber-100 text-amber-700': r.category === 'maintenance'
                              }"
                              x-text="r.category"></span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-xs rounded-full px-2.5 py-0.5 font-medium"
                              :class="r.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                              x-text="r.is_active ? 'Active' : 'Inactive'"></span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2 justify-end">
                            <button @click="openReasonEdit(r)"
                                    class="rounded-lg bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600 hover:bg-gray-200">
                                Edit
                            </button>
                            <button @click="toggleReasonActive(r)"
                                    class="rounded-lg px-3 py-1 text-xs font-medium"
                                    :class="r.is_active ? 'bg-amber-50 text-amber-600 hover:bg-amber-100' : 'bg-green-50 text-green-600 hover:bg-green-100'"
                                    x-text="r.is_active ? 'Deactivate' : 'Activate'">
                            </button>
                            <button @click="deleteReason(r)"
                                    class="rounded-lg bg-red-50 px-3 py-1 text-xs font-medium text-red-500 hover:bg-red-100">
                                Delete
                            </button>
                        </div>
                    </td>
                </tr>
            </template>
        </tbody>
    </table>
</div>
</div>{{-- end reasons tab --}}

{{-- ── Downtime Event Modal ────────────────────────────────────────────── --}}
<div x-show="showModal" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
    <div @click.stop
         class="w-full max-w-lg rounded-2xl bg-white shadow-2xl overflow-hidden">

        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
            <h3 class="font-semibold text-gray-900"
                x-text="modalMode === 'create' ? 'Log Downtime Event' : 'Edit Downtime Event'"></h3>
            <button @click="showModal = false" class="text-gray-400 hover:text-gray-600">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="px-6 py-5 space-y-4">
            <div x-show="formError" class="rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700" x-text="formError"></div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Machine <span class="text-red-500">*</span></label>
                <select x-model="form.machine_id"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
                    <option value="">Select machine…</option>
                    <template x-for="m in machines" :key="m.id">
                        <option :value="m.id" x-text="m.name + ' (' + m.code + ')'"></option>
                    </template>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Started At <span class="text-red-500">*</span></label>
                    <input type="datetime-local" x-model="form.started_at"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Ended At</label>
                    <input type="datetime-local" x-model="form.ended_at"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
                    <p class="text-xs text-gray-400 mt-0.5">Leave blank if still ongoing</p>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Reason</label>
                <select x-model="form.downtime_reason_id"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
                    <option value="">Unknown / No reason selected</option>
                    <template x-for="r in reasons.filter(r => r.is_active)" :key="r.id">
                        <option :value="r.id" x-text="r.code + ' — ' + r.name + ' (' + r.category + ')'"></option>
                    </template>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Description</label>
                <textarea x-model="form.description" rows="2"
                          placeholder="What happened? (optional)"
                          class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 resize-none"></textarea>
            </div>
        </div>

        <div class="flex items-center justify-between border-t border-gray-100 px-6 py-4">
            <template x-if="modalMode === 'edit'">
                <button @click="deleteRow(editRow); showModal = false"
                        class="text-sm text-red-500 hover:text-red-700">Delete</button>
            </template>
            <template x-if="modalMode === 'create'"><div></div></template>
            <div class="flex gap-3">
                <button @click="showModal = false"
                        class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">Cancel</button>
                <button @click="save()" :disabled="saving"
                        class="rounded-lg bg-red-600 px-5 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">
                    <span x-text="saving ? 'Saving…' : (modalMode === 'create' ? 'Log Event' : 'Save Changes')"></span>
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ── Downtime Reason Modal ───────────────────────────────────────────── --}}
<div x-show="showReasonModal" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
    <div @click.stop
         class="w-full max-w-md rounded-2xl bg-white shadow-2xl overflow-hidden">

        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
            <h3 class="font-semibold text-gray-900"
                x-text="reasonModalMode === 'create' ? 'Add Reason Code' : 'Edit Reason Code'"></h3>
            <button @click="showReasonModal = false" class="text-gray-400 hover:text-gray-600">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="px-6 py-5 space-y-4">
            <div x-show="reasonFormError"
                 class="rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700"
                 x-text="reasonFormError"></div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Code <span class="text-red-500">*</span></label>
                    <input type="text" x-model="reasonForm.code"
                           @input="reasonForm.code = reasonForm.code.toUpperCase()"
                           placeholder="e.g. EL-001"
                           maxlength="20"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono uppercase focus:outline-none focus:ring-2 focus:ring-indigo-400">
                    <p class="text-xs text-gray-400 mt-0.5">Unique · max 20 chars</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Category <span class="text-red-500">*</span></label>
                    <select x-model="reasonForm.category"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                        <option value="unplanned">Unplanned</option>
                        <option value="planned">Planned</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                <input type="text" x-model="reasonForm.name"
                       placeholder="e.g. Electrical Fault"
                       maxlength="100"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
            </div>

            <template x-if="reasonModalMode === 'edit'">
                <div class="flex items-center gap-3">
                    <button type="button"
                            @click="reasonForm.is_active = !reasonForm.is_active"
                            :class="reasonForm.is_active ? 'bg-indigo-600' : 'bg-gray-300'"
                            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full transition-colors duration-200">
                        <span :class="reasonForm.is_active ? 'translate-x-5' : 'translate-x-0.5'"
                              class="inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 mt-0.5"></span>
                    </button>
                    <span class="text-sm text-gray-700" x-text="reasonForm.is_active ? 'Active' : 'Inactive'"></span>
                </div>
            </template>
        </div>

        <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4">
            <button @click="showReasonModal = false"
                    class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">Cancel</button>
            <button @click="saveReason()" :disabled="reasonSaving"
                    class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                <span x-text="reasonSaving ? 'Saving…' : (reasonModalMode === 'create' ? 'Add Reason' : 'Save Changes')"></span>
            </button>
        </div>
    </div>
</div>

{{-- Flash message --}}
<div x-show="flash.show" x-transition x-cloak
     class="fixed bottom-4 right-4 z-50 rounded-xl px-5 py-3 text-sm font-medium shadow-lg text-white"
     :class="flash.type === 'success' ? 'bg-green-600' : 'bg-red-600'"
     x-text="flash.msg"></div>

</div>{{-- end x-data --}}
@endsection

@push('scripts')
<script>
function downtimeManager(apiToken, factoryId, machines, reasons, factories) {
    return {
        apiToken,
        currentFactoryId: factoryId,
        factories: factories || [],
        machines:  machines  || [],
        reasons:   reasons   || [],

        // Tabs
        activeTab: 'events',

        // Events tab state
        rows:    [],
        meta:    null,
        loading: false,
        page:    1,
        filterMachine: '',
        filterStatus:  '',
        filterDate:    '',
        showModal:  false,
        modalMode:  'create',
        saving:     false,
        formError:  null,
        editRow:    null,
        form: { machine_id: '', started_at: '', ended_at: '', downtime_reason_id: '', description: '' },
        kpi: { open: 0, today: 0, totalMinutes: 0, topReason: '' },

        // Reasons tab state
        reasonSearch:        '',
        reasonCategoryFilter: '',
        reasonActiveFilter:  '',
        showReasonModal:     false,
        reasonModalMode:     'create',
        reasonSaving:        false,
        reasonFormError:     null,
        editReason:          null,
        reasonForm: { code: '', name: '', category: 'unplanned', is_active: true },

        // Flash
        flash: { show: false, type: 'success', msg: '' },

        get headers() {
            return {
                'Authorization': `Bearer ${this.apiToken}`,
                'Content-Type':  'application/json',
                'Accept':        'application/json',
            };
        },

        get filteredReasons() {
            return this.reasons.filter(r => {
                const q = this.reasonSearch.toLowerCase();
                if (q && !r.code.toLowerCase().includes(q) && !r.name.toLowerCase().includes(q)) return false;
                if (this.reasonCategoryFilter && r.category !== this.reasonCategoryFilter) return false;
                if (this.reasonActiveFilter === 'active'   && !r.is_active) return false;
                if (this.reasonActiveFilter === 'inactive' &&  r.is_active) return false;
                return true;
            });
        },

        init() { this.loadData(); },

        setFlash(type, msg) {
            this.flash = { show: true, type, msg };
            setTimeout(() => this.flash.show = false, 3000);
        },

        localNow() {
            const d = new Date();
            d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
            return d.toISOString().slice(0, 16);
        },

        // ── Downtime Events ──────────────────────────────────────────
        async loadData() {
            this.loading = true;
            const params = new URLSearchParams({ per_page: 20, page: this.page });
            if (this.currentFactoryId) params.append('factory_id', this.currentFactoryId);
            if (this.filterMachine)    params.append('machine_id', this.filterMachine);
            if (this.filterDate)       params.append('date', this.filterDate);
            if (this.filterStatus === 'open')   params.append('open_only', '1');
            if (this.filterStatus === 'closed') params.append('closed_only', '1');
            try {
                const res  = await fetch(`/api/v1/downtimes?${params}`, { headers: this.headers });
                const json = await res.json();
                this.rows = json.data || [];
                this.meta = json;
                this.computeKpi();
            } catch(e) { /* silent */ }
            this.loading = false;
        },

        computeKpi() {
            const today = new Date().toISOString().split('T')[0];
            this.kpi.open         = this.rows.filter(r => !r.ended_at).length;
            this.kpi.today        = this.rows.filter(r => r.started_at && r.started_at.startsWith(today)).length;
            this.kpi.totalMinutes = this.rows.reduce((s, r) => s + (r.duration_minutes || 0), 0);
            const counts = {};
            this.rows.forEach(r => { if (r.downtime_reason_id) counts[r.downtime_reason_id] = (counts[r.downtime_reason_id] || 0) + 1; });
            const topId = Object.entries(counts).sort((a,b) => b[1]-a[1])[0]?.[0];
            const topR  = this.reasons.find(r => r.id == topId);
            this.kpi.topReason = topR ? topR.code : '';
        },

        machineName(id) { return this.machines.find(m => m.id == id)?.name || id; },
        machineCode(id)  { return this.machines.find(m => m.id == id)?.code || ''; },
        reasonName(id)   { return id ? (this.reasons.find(r => r.id == id)?.name || '') : ''; },

        fmtDatetime(dt) {
            if (!dt) return '—';
            return new Date(dt).toLocaleString('en-GB', { day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit' });
        },

        openCreate() {
            this.modalMode = 'create';
            this.formError = null;
            this.form = { machine_id: '', started_at: this.localNow(), ended_at: '', downtime_reason_id: '', description: '' };
            this.showModal = true;
        },

        openEdit(row) {
            this.modalMode = 'edit';
            this.formError = null;
            this.editRow   = row;
            this.form = {
                machine_id:         row.machine_id,
                started_at:         row.started_at ? row.started_at.replace(' ', 'T').slice(0, 16) : '',
                ended_at:           row.ended_at   ? row.ended_at.replace(' ', 'T').slice(0, 16)   : '',
                downtime_reason_id: row.downtime_reason_id || '',
                description:        row.description || '',
            };
            this.showModal = true;
        },

        async closeDowntime(row) {
            const now = new Date().toISOString().slice(0, 16).replace('T', ' ');
            await fetch(`/api/v1/downtimes/${row.id}`, {
                method: 'PUT', headers: this.headers,
                body: JSON.stringify({ ended_at: now }),
            });
            this.loadData();
        },

        async save() {
            this.saving    = true;
            this.formError = null;
            if (!this.form.machine_id || !this.form.started_at) {
                this.formError = 'Machine and Started At are required.';
                this.saving = false;
                return;
            }
            const payload = {
                machine_id:         parseInt(this.form.machine_id),
                started_at:         this.form.started_at.replace('T', ' '),
                ended_at:           this.form.ended_at ? this.form.ended_at.replace('T', ' ') : null,
                downtime_reason_id: this.form.downtime_reason_id ? parseInt(this.form.downtime_reason_id) : null,
                description:        this.form.description || null,
            };
            const url    = this.modalMode === 'create' ? '/api/v1/downtimes' : `/api/v1/downtimes/${this.editRow.id}`;
            const method = this.modalMode === 'create' ? 'POST' : 'PUT';
            try {
                const res = await fetch(url, { method, headers: this.headers, body: JSON.stringify(payload) });
                if (!res.ok) {
                    const err = await res.json();
                    this.formError = err.message || JSON.stringify(err.errors || err);
                    this.saving = false;
                    return;
                }
                this.showModal = false;
                this.setFlash('success', this.modalMode === 'create' ? 'Downtime logged.' : 'Event updated.');
                this.loadData();
            } catch(e) { this.formError = e.message; }
            this.saving = false;
        },

        async deleteRow(row) {
            if (!confirm('Delete this downtime record?')) return;
            await fetch(`/api/v1/downtimes/${row.id}`, { method: 'DELETE', headers: this.headers });
            this.loadData();
        },

        // ── Downtime Reasons ─────────────────────────────────────────
        async loadReasons() {
            try {
                const params = new URLSearchParams();
                if (this.currentFactoryId) params.append('factory_id', this.currentFactoryId);
                const res  = await fetch(`/api/v1/downtime-reasons?${params}`, { headers: this.headers });
                this.reasons = await res.json();
            } catch(e) { /* silent */ }
        },

        openReasonCreate() {
            this.reasonModalMode = 'create';
            this.reasonFormError = null;
            this.reasonForm = { code: '', name: '', category: 'unplanned', is_active: true };
            this.showReasonModal = true;
        },

        openReasonEdit(r) {
            this.reasonModalMode = 'edit';
            this.reasonFormError = null;
            this.editReason = r;
            this.reasonForm = { code: r.code, name: r.name, category: r.category, is_active: !!r.is_active };
            this.showReasonModal = true;
        },

        async saveReason() {
            this.reasonSaving    = true;
            this.reasonFormError = null;
            if (!this.reasonForm.code.trim() || !this.reasonForm.name.trim()) {
                this.reasonFormError = 'Code and Name are required.';
                this.reasonSaving = false;
                return;
            }
            const isCreate = this.reasonModalMode === 'create';
            const url    = isCreate ? '/api/v1/downtime-reasons' : `/api/v1/downtime-reasons/${this.editReason.id}`;
            const method = isCreate ? 'POST' : 'PUT';
            const payload = {
                code:      this.reasonForm.code.trim().toUpperCase(),
                name:      this.reasonForm.name.trim(),
                category:  this.reasonForm.category,
            };
            if (!isCreate) payload.is_active = this.reasonForm.is_active;
            try {
                const res = await fetch(url, { method, headers: this.headers, body: JSON.stringify(payload) });
                if (!res.ok) {
                    const err = await res.json();
                    this.reasonFormError = err.message || JSON.stringify(err.errors || err);
                    this.reasonSaving = false;
                    return;
                }
                this.showReasonModal = false;
                this.setFlash('success', isCreate ? 'Reason code added.' : 'Reason code updated.');
                await this.loadReasons();
            } catch(e) { this.reasonFormError = e.message || 'Network error.'; }
            this.reasonSaving = false;
        },

        async toggleReasonActive(r) {
            try {
                const res = await fetch(`/api/v1/downtime-reasons/${r.id}`, {
                    method: 'PUT', headers: this.headers,
                    body: JSON.stringify({ is_active: !r.is_active }),
                });
                if (res.ok) {
                    r.is_active = !r.is_active;
                    this.setFlash('success', r.is_active ? 'Reason activated.' : 'Reason deactivated.');
                }
            } catch(e) { /* silent */ }
        },

        async deleteReason(r) {
            if (!confirm(`Delete reason "${r.code} — ${r.name}"? This cannot be undone.`)) return;
            try {
                const res = await fetch(`/api/v1/downtime-reasons/${r.id}`, { method: 'DELETE', headers: this.headers });
                if (res.ok) {
                    this.reasons = this.reasons.filter(x => x.id !== r.id);
                    this.setFlash('success', 'Reason code deleted.');
                } else {
                    const err = await res.json();
                    this.setFlash('error', err.message || 'Delete failed.');
                }
            } catch(e) { this.setFlash('error', 'Network error.'); }
        },
    };
}
</script>
@endpush

@extends('admin.layouts.app')
@section('title', 'Machines')

@section('header-actions')
<button onclick="window.machinePage && window.machinePage.openCreate()"
        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 transition-colors shadow-sm">
    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
    </svg>
    Add Machine
</button>
@endsection

@section('content')
<div
    x-data="machinesPage('{{ $apiToken }}', {{ $factoryId ?? 'null' }}, {{ $factories->toJson() }})"
    x-init="init()"
    x-ref="root"
>

{{-- ── Toast ──────────────────────────────────────────────────────── --}}
<div x-show="toast.show" x-cloak
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 translate-y-2"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     :class="toast.type === 'success' ? 'bg-green-600' : 'bg-red-600'"
     class="fixed top-5 right-5 z-50 flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-white shadow-lg">
    <template x-if="toast.type === 'success'">
        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
        </svg>
    </template>
    <template x-if="toast.type === 'error'">
        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
    </template>
    <span x-text="toast.message"></span>
</div>

{{-- ── CREATE MODAL ────────────────────────────────────────────────── --}}
<div x-show="showCreate" x-cloak
     class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4"
     @keydown.escape.window="showCreate = false">
    <div @click.stop class="w-full max-w-lg rounded-2xl bg-white shadow-2xl overflow-hidden">

        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
            <div>
                <h2 class="text-base font-bold text-gray-900">Add Machine</h2>
                <p class="text-xs text-gray-400 mt-0.5">New machine in your factory</p>
            </div>
            <button @click="showCreate = false" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 transition-colors">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div x-show="formError" x-cloak
             class="mx-6 mt-4 rounded-lg bg-red-50 border border-red-200 px-4 py-2.5 text-sm text-red-700"
             x-text="formError"></div>

        <div class="px-6 py-5 space-y-4">

            {{-- Super-admin: factory selector --}}
            <template x-if="factories.length > 0">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Factory <span class="text-red-500">*</span>
                    </label>
                    <select x-model="form.factory_id"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                        <option value="">Select factory…</option>
                        <template x-for="f in factories" :key="f.id">
                            <option :value="f.id" x-text="f.name"></option>
                        </template>
                    </select>
                </div>
            </template>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Machine Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" x-model="form.name" placeholder="CNC Lathe A"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Machine Code <span class="text-red-500">*</span>
                    </label>
                    <input type="text" x-model="form.code" placeholder="CNC-001"
                           @input="form.code = form.code.toUpperCase()"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                    <p class="text-[10px] text-gray-400 mt-0.5">Letters, numbers, - and _ only</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Type <span class="text-red-500">*</span>
                    </label>
                    <input type="text" x-model="form.type" placeholder="CNC / Lathe / Welder…"
                           list="type-suggestions-create"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                    <datalist id="type-suggestions-create">
                        <option value="CNC">
                        <option value="Lathe">
                        <option value="Milling">
                        <option value="Welding">
                        <option value="Pressing">
                        <option value="Assembly">
                        <option value="Grinding">
                        <option value="Robot">
                        <option value="Inspection">
                    </datalist>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Install Date</label>
                    <input type="date" x-model="form.installed_at"
                           :max="todayStr"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Model</label>
                    <input type="text" x-model="form.model" placeholder="e.g. Haas ST-20"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Manufacturer</label>
                    <input type="text" x-model="form.manufacturer" placeholder="e.g. Haas Automation"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                </div>
            </div>

        </div>

        <div class="flex gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4">
            <button @click="showCreate = false"
                    class="flex-1 rounded-lg border border-gray-200 bg-white py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                Cancel
            </button>
            <button @click="submitCreate()" :disabled="saving"
                    class="flex-1 rounded-lg bg-indigo-600 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50 transition-colors flex items-center justify-center gap-2">
                <svg x-show="saving" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                <span x-text="saving ? 'Saving…' : 'Add Machine'"></span>
            </button>
        </div>
    </div>
</div>

{{-- ── EDIT MODAL ──────────────────────────────────────────────────── --}}
<div x-show="showEdit" x-cloak
     class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4"
     @keydown.escape.window="showEdit = false">
    <div @click.stop class="w-full max-w-lg rounded-2xl bg-white shadow-2xl overflow-hidden">

        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
            <div>
                <h2 class="text-base font-bold text-gray-900">Edit Machine</h2>
                <p class="text-xs text-gray-400 mt-0.5" x-text="editTarget?.name"></p>
            </div>
            <button @click="showEdit = false" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 transition-colors">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div x-show="formError" x-cloak
             class="mx-6 mt-4 rounded-lg bg-red-50 border border-red-200 px-4 py-2.5 text-sm text-red-700"
             x-text="formError"></div>

        <div class="px-6 py-5 space-y-4">

            {{-- Status strip --}}
            <div>
                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest mb-2">Status</p>
                <div class="flex gap-2">
                    <template x-for="s in statuses" :key="s.value">
                        <button @click="form.status = s.value"
                                :class="form.status === s.value ? s.activeClass : 'bg-white border-gray-200 text-gray-500 hover:border-gray-400'"
                                class="px-3 py-1.5 rounded-full text-xs font-semibold border transition-all"
                                x-text="s.label">
                        </button>
                    </template>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Machine Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" x-model="form.name"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Machine Code <span class="text-red-500">*</span>
                    </label>
                    <input type="text" x-model="form.code"
                           @input="form.code = form.code.toUpperCase()"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Type <span class="text-red-500">*</span>
                    </label>
                    <input type="text" x-model="form.type"
                           list="type-suggestions-edit"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                    <datalist id="type-suggestions-edit">
                        <option value="CNC">
                        <option value="Lathe">
                        <option value="Milling">
                        <option value="Welding">
                        <option value="Pressing">
                        <option value="Assembly">
                        <option value="Grinding">
                        <option value="Robot">
                        <option value="Inspection">
                    </datalist>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Install Date</label>
                    <input type="date" x-model="form.installed_at"
                           :max="todayStr"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Model</label>
                    <input type="text" x-model="form.model"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Manufacturer</label>
                    <input type="text" x-model="form.manufacturer"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                </div>
            </div>

        </div>

        <div class="flex gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4">
            <button @click="showEdit = false"
                    class="flex-1 rounded-lg border border-gray-200 bg-white py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                Cancel
            </button>
            <button @click="submitEdit()" :disabled="saving"
                    class="flex-1 rounded-lg bg-indigo-600 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50 transition-colors flex items-center justify-center gap-2">
                <svg x-show="saving" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                <span x-text="saving ? 'Saving…' : 'Save Changes'"></span>
            </button>
        </div>
    </div>
</div>

{{-- ── DELETE CONFIRM ──────────────────────────────────────────────── --}}
<div x-show="showDelete" x-cloak
     class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4"
     @keydown.escape.window="showDelete = false">
    <div @click.stop class="w-full max-w-sm rounded-2xl bg-white shadow-2xl overflow-hidden">
        <div class="px-6 pt-6 pb-4">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-100">
                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <h3 class="text-center text-base font-bold text-gray-900">Delete Machine?</h3>
            <p class="mt-1 text-center text-sm text-gray-500">
                <strong x-text="deleteTarget?.name"></strong> (<span x-text="deleteTarget?.code"></span>)
                will be permanently deleted. This cannot be undone.
            </p>
            <p class="mt-2 text-center text-xs text-red-500 font-medium">
                All IoT logs and OEE history for this machine will also be removed.
            </p>
        </div>
        <div class="flex gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4">
            <button @click="showDelete = false"
                    class="flex-1 rounded-lg border border-gray-200 bg-white py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                Cancel
            </button>
            <button @click="submitDelete()" :disabled="saving"
                    class="flex-1 rounded-lg bg-red-600 py-2 text-sm font-semibold text-white hover:bg-red-500 disabled:opacity-50 transition-colors flex items-center justify-center gap-2">
                <svg x-show="saving" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                <span x-text="saving ? 'Deleting…' : 'Delete Machine'"></span>
            </button>
        </div>
    </div>
</div>

{{-- ── FILTER BAR ──────────────────────────────────────────────────── --}}
<div class="mb-5 flex flex-wrap items-center gap-3">

    {{-- Search --}}
    <div class="relative">
        <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400"
             fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
        </svg>
        <input type="text" x-model="search"
               @input.debounce.350ms="load(1)"
               placeholder="Search name or code…"
               class="w-64 rounded-lg border border-gray-300 pl-9 pr-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
    </div>

    {{-- Status filter --}}
    <select x-model="filterStatus" @change="load(1)"
            class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
        <option value="">All Statuses</option>
        <option value="active">Active</option>
        <option value="maintenance">Maintenance</option>
        <option value="retired">Retired</option>
    </select>

    {{-- Factory filter (super-admin) --}}
    <template x-if="factories.length > 0">
        <select x-model="filterFactory" @change="load(1)"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
            <option value="">All Factories</option>
            <template x-for="f in factories" :key="f.id">
                <option :value="f.id" x-text="f.name"></option>
            </template>
        </select>
    </template>

    {{-- Count badge --}}
    <span class="ml-auto text-sm text-gray-500">
        <span class="font-semibold text-gray-800" x-text="total"></span> machine<span x-show="total !== 1">s</span>
    </span>
</div>

{{-- ── TABLE ───────────────────────────────────────────────────────── --}}
<div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                <th class="px-5 py-3">Code</th>
                <th class="px-5 py-3">Name</th>
                <th class="px-5 py-3">Type</th>
                <th class="px-5 py-3">Model / Manufacturer</th>
                <th class="px-5 py-3">Installed</th>
                <th class="px-5 py-3">Status</th>
                <th class="px-5 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">

            {{-- Loading skeleton --}}
            <template x-if="loading && machines.length === 0">
                <template x-for="i in 5" :key="i">
                    <tr class="animate-pulse">
                        <td class="px-5 py-3"><div class="h-3.5 w-20 rounded bg-gray-200"></div></td>
                        <td class="px-5 py-3"><div class="h-3.5 w-36 rounded bg-gray-200"></div></td>
                        <td class="px-5 py-3"><div class="h-3.5 w-16 rounded bg-gray-200"></div></td>
                        <td class="px-5 py-3"><div class="h-3.5 w-28 rounded bg-gray-200"></div></td>
                        <td class="px-5 py-3"><div class="h-3.5 w-20 rounded bg-gray-200"></div></td>
                        <td class="px-5 py-3"><div class="h-5 w-16 rounded-full bg-gray-200"></div></td>
                        <td class="px-5 py-3 text-right"><div class="ml-auto h-7 w-28 rounded bg-gray-200"></div></td>
                    </tr>
                </template>
            </template>

            {{-- Empty state --}}
            <template x-if="!loading && machines.length === 0">
                <tr>
                    <td colspan="7" class="py-20 text-center text-gray-400">
                        <svg class="h-12 w-12 mx-auto mb-3 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/>
                        </svg>
                        <p class="font-medium text-sm">No machines found</p>
                        <p class="text-xs mt-1" x-show="search || filterStatus">Try clearing the filters.</p>
                        <p class="text-xs mt-1" x-show="!search && !filterStatus">Add your first machine to get started.</p>
                    </td>
                </tr>
            </template>

            {{-- Rows --}}
            <template x-for="m in machines" :key="m.id">
                <tr class="hover:bg-slate-50 transition-colors">

                    {{-- Code --}}
                    <td class="px-5 py-3">
                        <span class="font-mono text-xs font-bold text-gray-700 bg-gray-100 px-2 py-0.5 rounded"
                              x-text="m.code"></span>
                    </td>

                    {{-- Name --}}
                    <td class="px-5 py-3 font-semibold text-gray-900" x-text="m.name"></td>

                    {{-- Type --}}
                    <td class="px-5 py-3 text-gray-600" x-text="m.type || '—'"></td>

                    {{-- Model / Manufacturer --}}
                    <td class="px-5 py-3">
                        <p class="text-gray-800" x-text="m.model || '—'"></p>
                        <p class="text-xs text-gray-400" x-show="m.manufacturer" x-text="m.manufacturer"></p>
                    </td>

                    {{-- Installed --}}
                    <td class="px-5 py-3 text-gray-500 text-xs" x-text="m.installed_at || '—'"></td>

                    {{-- Status badge --}}
                    <td class="px-5 py-3">
                        <span :class="{
                            'bg-green-100 text-green-700':  m.status === 'active',
                            'bg-amber-100 text-amber-700':  m.status === 'maintenance',
                            'bg-gray-100  text-gray-500':   m.status === 'retired',
                        }"
                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold">
                            <span class="h-1.5 w-1.5 rounded-full"
                                  :class="{
                                      'bg-green-500': m.status === 'active',
                                      'bg-amber-500': m.status === 'maintenance',
                                      'bg-gray-400':  m.status === 'retired',
                                  }"></span>
                            <span x-text="m.status === 'active' ? 'Active' : (m.status === 'maintenance' ? 'Maintenance' : 'Retired')"></span>
                        </span>
                    </td>

                    {{-- Actions --}}
                    <td class="px-5 py-3 text-right">
                        <div class="inline-flex items-center gap-1">
                            <button @click="openEdit(m)"
                                    class="rounded-lg px-3 py-1.5 text-xs font-medium text-indigo-600 hover:bg-indigo-50 transition-colors">
                                Edit
                            </button>
                            <button @click="confirmDelete(m)"
                                    class="rounded-lg px-3 py-1.5 text-xs font-medium text-red-500 hover:bg-red-50 transition-colors">
                                Delete
                            </button>
                        </div>
                    </td>

                </tr>
            </template>

        </tbody>
    </table>
</div>

{{-- ── PAGINATION ──────────────────────────────────────────────────── --}}
<template x-if="lastPage > 1">
    <div class="mt-4 flex items-center justify-between text-sm text-gray-500">
        <span>
            Page <span class="font-semibold text-gray-800" x-text="currentPage"></span>
            of <span x-text="lastPage"></span>
        </span>
        <div class="flex gap-2">
            <button @click="load(currentPage - 1)" :disabled="currentPage <= 1"
                    class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium hover:bg-gray-50 disabled:opacity-40 transition-colors">
                ← Prev
            </button>
            <button @click="load(currentPage + 1)" :disabled="currentPage >= lastPage"
                    class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium hover:bg-gray-50 disabled:opacity-40 transition-colors">
                Next →
            </button>
        </div>
    </div>
</template>

</div>{{-- end x-data --}}
@endsection

@push('scripts')
<script>
function machinesPage(apiToken, factoryId, factories) {

    const component = {
        apiToken,
        currentFactoryId: factoryId,
        factories: factories || [],

        machines:      [],
        loading:       false,
        error:         null,
        search:        '',
        filterStatus:  '',
        filterFactory: factoryId ? String(factoryId) : '',
        total:         0,
        currentPage:   1,
        lastPage:      1,

        showCreate:   false,
        showEdit:     false,
        showDelete:   false,
        editTarget:   null,
        deleteTarget: null,
        saving:       false,
        formError:    null,

        toast: { show: false, message: '', type: 'success' },

        form: {
            name: '', code: '', type: '', model: '',
            manufacturer: '', installed_at: '', status: 'active', factory_id: '',
        },

        statuses: [
            { value: 'active',      label: 'Active',      activeClass: 'bg-green-100 border-green-400 text-green-800' },
            { value: 'maintenance', label: 'Maintenance',  activeClass: 'bg-amber-100 border-amber-400 text-amber-800' },
            { value: 'retired',     label: 'Retired',      activeClass: 'bg-gray-100 border-gray-400 text-gray-700' },
        ],

        get todayStr() {
            const d = new Date();
            return d.getFullYear() + '-'
                + String(d.getMonth()+1).padStart(2,'0') + '-'
                + String(d.getDate()).padStart(2,'0');
        },

        get headers() {
            return {
                'Accept':        'application/json',
                'Content-Type':  'application/json',
                'Authorization': `Bearer ${apiToken}`,
            };
        },

        init() {
            window.machinePage = this;
            this.load(1);
        },

        async load(page) {
            this.loading = true;
            this.error   = null;
            try {
                const params = new URLSearchParams({ page, per_page: 25 });
                if (this.search)        params.append('search', this.search);
                if (this.filterStatus)  params.append('status', this.filterStatus);
                if (this.filterFactory) params.append('factory_id', this.filterFactory);

                const res  = await fetch(`/api/v1/machines?${params}`, { headers: this.headers });
                if (!res.ok) throw new Error(`Server error ${res.status}`);
                const json = await res.json();

                this.machines    = json.data      || [];
                this.total       = json.meta?.total || json.total || this.machines.length;
                this.currentPage = json.meta?.current_page || json.current_page || page;
                this.lastPage    = json.meta?.last_page    || json.last_page    || 1;
            } catch (e) {
                this.error = e.message;
            } finally {
                this.loading = false;
            }
        },

        // ── Create ────────────────────────────────────────────────────
        openCreate() {
            this.formError = null;
            this.form = {
                name: '', code: '', type: '', model: '',
                manufacturer: '', installed_at: '', status: 'active',
                factory_id: this.filterFactory || '',
            };
            this.showCreate = true;
        },

        async submitCreate() {
            if (!this.form.name || !this.form.code || !this.form.type) {
                this.formError = 'Name, Code and Type are required.';
                return;
            }
            this.saving    = true;
            this.formError = null;
            try {
                const body = { ...this.form };
                if (!body.factory_id) delete body.factory_id;
                if (!body.installed_at) delete body.installed_at;
                if (!body.model) delete body.model;
                if (!body.manufacturer) delete body.manufacturer;

                const res = await fetch('/api/v1/machines', {
                    method: 'POST',
                    headers: this.headers,
                    body: JSON.stringify(body),
                });
                const data = await res.json();
                if (!res.ok) { this.formError = this.extractError(data); return; }

                this.showCreate = false;
                this.showToast('Machine added successfully.');
                await this.load(this.currentPage);
            } catch (e) {
                this.formError = e.message;
            } finally {
                this.saving = false;
            }
        },

        // ── Edit ──────────────────────────────────────────────────────
        openEdit(machine) {
            this.editTarget = machine;
            this.formError  = null;
            this.form = {
                name:         machine.name,
                code:         machine.code,
                type:         machine.type         || '',
                model:        machine.model        || '',
                manufacturer: machine.manufacturer || '',
                installed_at: machine.installed_at || '',
                status:       machine.status,
                factory_id:   machine.factory_id   || '',
            };
            this.showEdit = true;
        },

        async submitEdit() {
            if (!this.form.name || !this.form.code || !this.form.type) {
                this.formError = 'Name, Code and Type are required.';
                return;
            }
            this.saving    = true;
            this.formError = null;
            try {
                const body = { ...this.form };
                delete body.factory_id;
                if (!body.installed_at) delete body.installed_at;

                const res = await fetch(`/api/v1/machines/${this.editTarget.id}`, {
                    method: 'PUT',
                    headers: this.headers,
                    body: JSON.stringify(body),
                });
                const data = await res.json();
                if (!res.ok) { this.formError = this.extractError(data); return; }

                this.showEdit = false;
                this.showToast('Machine updated successfully.');
                await this.load(this.currentPage);
            } catch (e) {
                this.formError = e.message;
            } finally {
                this.saving = false;
            }
        },

        // ── Delete ────────────────────────────────────────────────────
        confirmDelete(machine) {
            this.deleteTarget = machine;
            this.showDelete   = true;
        },

        async submitDelete() {
            this.saving = true;
            try {
                const res = await fetch(`/api/v1/machines/${this.deleteTarget.id}`, {
                    method: 'DELETE',
                    headers: this.headers,
                });
                if (!res.ok) {
                    const data = await res.json().catch(() => ({}));
                    this.showToast(this.extractError(data), 'error');
                    return;
                }
                this.showDelete = false;
                this.showToast('Machine deleted.', 'success');
                await this.load(this.currentPage);
            } catch (e) {
                this.showToast(e.message, 'error');
            } finally {
                this.saving = false;
            }
        },

        // ── Helpers ───────────────────────────────────────────────────
        extractError(data) {
            if (data?.message) return data.message;
            if (data?.errors) {
                const first = Object.values(data.errors)[0];
                return Array.isArray(first) ? first[0] : first;
            }
            return 'An unexpected error occurred.';
        },

        showToast(message, type = 'success') {
            this.toast = { show: true, message, type };
            setTimeout(() => { this.toast.show = false; }, 3500);
        },
    };

    return component;
}
</script>
@endpush

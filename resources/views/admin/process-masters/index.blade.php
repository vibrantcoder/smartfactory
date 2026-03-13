@extends('admin.layouts.app')

@section('title', 'Process Masters')

@section('content')
<div
    x-data="processMasters('{{ $apiToken }}')"
    x-init="init()"
    class="space-y-6">

    {{-- ── Header ──────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Process Masters</h1>
            <p class="mt-1 text-sm text-gray-500">Global reference table for manufacturing process types</p>
        </div>
        @can('create', \App\Domain\Production\Models\ProcessMaster::class)
        <button @click="openCreate()"
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 transition-colors">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New Process
        </button>
        @endcan
    </div>

    {{-- ── Filters ─────────────────────────────────────────────── --}}
    <div class="flex flex-wrap gap-3">
        <div class="relative flex-1 min-w-48">
            <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400"
                 fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input x-model.debounce.400ms="filters.search"
                   @input="currentPage = 1; load()"
                   type="text" placeholder="Search name or code…"
                   class="w-full rounded-lg border border-gray-300 py-2 pl-9 pr-3 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"/>
        </div>

        <select x-model="filters.is_active" @change="currentPage = 1; load()"
                class="rounded-lg border border-gray-300 py-2 pl-3 pr-8 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
            <option value="">All Statuses</option>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
        </select>

        <select x-model="filters.machine_type_default" @change="currentPage = 1; load()"
                class="rounded-lg border border-gray-300 py-2 pl-3 pr-8 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
            <option value="">All Machine Types</option>
            <template x-for="mt in machineTypes" :key="mt">
                <option :value="mt" x-text="mt"></option>
            </template>
        </select>
    </div>

    {{-- ── Error banner ────────────────────────────────────────── --}}
    <div x-show="error" x-transition
         class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
         x-text="error"></div>

    {{-- ── Table ───────────────────────────────────────────────── --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wider text-xs">Code</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wider text-xs">Name</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wider text-xs">Machine Type</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600 uppercase tracking-wider text-xs">Process Type</th>
                    <th class="px-4 py-3 text-center font-semibold text-gray-600 uppercase tracking-wider text-xs">Parts Using</th>
                    <th class="px-4 py-3 text-center font-semibold text-gray-600 uppercase tracking-wider text-xs">Status</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600 uppercase tracking-wider text-xs">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">

                {{-- Loading skeleton --}}
                <template x-if="loading && items.length === 0">
                    <template x-for="i in 6" :key="i">
                        <tr class="animate-pulse">
                            <td class="px-4 py-3"><div class="h-4 w-20 rounded bg-gray-200"></div></td>
                            <td class="px-4 py-3"><div class="h-4 w-40 rounded bg-gray-200"></div></td>
                            <td class="px-4 py-3"><div class="h-4 w-28 rounded bg-gray-200"></div></td>
                            <td class="px-4 py-3"><div class="h-4 w-16 rounded bg-gray-200"></div></td>
                            <td class="px-4 py-3"><div class="h-4 w-8 rounded bg-gray-200 mx-auto"></div></td>
                            <td class="px-4 py-3"><div class="h-5 w-16 rounded bg-gray-200 mx-auto"></div></td>
                            <td class="px-4 py-3"><div class="h-4 w-20 rounded bg-gray-200 ml-auto"></div></td>
                        </tr>
                    </template>
                </template>

                {{-- Empty state --}}
                <template x-if="!loading && items.length === 0">
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-gray-400">
                            <svg class="mx-auto h-10 w-10 text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <p class="font-medium">No process masters found</p>
                            <p class="text-sm mt-1">Try adjusting your filters or create a new process.</p>
                        </td>
                    </tr>
                </template>

                {{-- Data rows --}}
                <template x-for="item in items" :key="item.id">
                    <tr class="hover:bg-gray-50 transition-colors">
                        {{-- Code --}}
                        <td class="px-4 py-3">
                            <span class="font-mono text-xs font-semibold text-indigo-700 bg-indigo-50 border border-indigo-200 rounded px-2 py-0.5"
                                  x-text="item.code"></span>
                        </td>

                        {{-- Name --}}
                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-900" x-text="item.name"></p>
                            <p x-show="item.description" class="text-xs text-gray-400 mt-0.5 line-clamp-1" x-text="item.description"></p>
                        </td>

                        {{-- Machine Type --}}
                        <td class="px-4 py-3">
                            <span x-show="item.machine_type_default"
                                  class="inline-block text-xs text-gray-600 bg-gray-100 rounded px-2 py-0.5"
                                  x-text="item.machine_type_default"></span>
                            <span x-show="!item.machine_type_default" class="text-gray-300 text-xs">—</span>
                        </td>

                        {{-- Process Type --}}
                        <td class="px-4 py-3">
                            <span :class="item.process_type === 'outside'
                                    ? 'bg-amber-50 text-amber-700 border-amber-200'
                                    : 'bg-blue-50 text-blue-700 border-blue-200'"
                                  class="inline-block rounded border px-2 py-0.5 text-xs font-medium capitalize"
                                  x-text="item.process_type === 'outside' ? 'Outside' : 'In-house'">
                            </span>
                        </td>

                        {{-- Parts Using --}}
                        <td class="px-4 py-3 text-center">
                            <span class="inline-block text-xs font-semibold text-gray-600"
                                  x-text="item.part_processes_count ?? '—'"></span>
                        </td>

                        {{-- Status badge --}}
                        <td class="px-4 py-3 text-center">
                            <span :class="item.is_active
                                ? 'bg-green-50 text-green-700 border-green-200'
                                : 'bg-gray-100 text-gray-500 border-gray-200'"
                                class="inline-block rounded-full border px-2.5 py-0.5 text-xs font-medium"
                                x-text="item.is_active ? 'Active' : 'Inactive'">
                            </span>
                        </td>

                        {{-- Actions --}}
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                @can('update', new \App\Domain\Production\Models\ProcessMaster)
                                <button @click="openEdit(item)"
                                        class="rounded px-2.5 py-1 text-xs font-medium text-indigo-600 hover:bg-indigo-50 transition-colors">
                                    Edit
                                </button>
                                @endcan

                                @can('delete', new \App\Domain\Production\Models\ProcessMaster)
                                <template x-if="item.is_active">
                                    <button @click="confirmDeactivate(item)"
                                            class="rounded px-2.5 py-1 text-xs font-medium text-red-600 hover:bg-red-50 transition-colors">
                                        Deactivate
                                    </button>
                                </template>
                                <template x-if="!item.is_active">
                                    <button @click="reactivate(item)"
                                            class="rounded px-2.5 py-1 text-xs font-medium text-green-600 hover:bg-green-50 transition-colors">
                                        Reactivate
                                    </button>
                                </template>
                                @endcan
                            </div>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    {{-- ── Pagination ──────────────────────────────────────────── --}}
    <div x-show="meta.last_page > 1" class="flex items-center justify-between text-sm text-gray-600">
        <p>Showing <span class="font-medium" x-text="meta.from"></span>–<span class="font-medium" x-text="meta.to"></span>
           of <span class="font-medium" x-text="meta.total"></span> processes</p>
        <div class="flex gap-1">
            <button @click="goPage(currentPage - 1)" :disabled="currentPage <= 1"
                    class="rounded-lg border border-gray-300 px-3 py-1.5 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                &laquo; Prev
            </button>
            <button @click="goPage(currentPage + 1)" :disabled="currentPage >= meta.last_page"
                    class="rounded-lg border border-gray-300 px-3 py-1.5 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                Next &raquo;
            </button>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════
         Create / Edit Modal
    ══════════════════════════════════════════════════════════ --}}
    <div x-show="showModal"
         x-transition:enter="transition duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
         @keydown.escape.window="closeModal()"
         style="display:none">

        <div x-show="showModal"
             x-transition:enter="transition duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             @click.stop
             class="w-full max-w-lg rounded-2xl bg-white shadow-2xl flex flex-col max-h-[90vh]">

            {{-- Modal header --}}
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 shrink-0">
                <h2 class="text-lg font-semibold text-gray-900"
                    x-text="modalMode === 'create' ? 'New Process Master' : 'Edit Process Master'"></h2>
                <button @click="closeModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Modal body --}}
            <div class="overflow-y-auto flex-1 px-6 py-5 space-y-4">

                {{-- Error --}}
                <div x-show="formError" x-transition
                     class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                     x-text="formError"></div>

                {{-- Name --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Process Name <span class="text-red-500">*</span>
                    </label>
                    <input x-model="form.name"
                           type="text" placeholder="e.g. CNC Turning"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"/>
                </div>

                {{-- Code --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Code <span class="text-red-500">*</span>
                        <span class="text-xs text-gray-400 font-normal">(auto-uppercased, globally unique)</span>
                    </label>
                    <input x-model="form.code"
                           @input="form.code = form.code.toUpperCase()"
                           type="text" placeholder="e.g. CNC-TURN"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"/>
                </div>

                {{-- Process Type --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Process Type</label>
                    <div class="flex rounded-lg border border-gray-300 overflow-hidden w-fit">
                        <button type="button"
                                @click="form.process_type = 'inhouse'"
                                :class="form.process_type === 'inhouse'
                                    ? 'bg-blue-600 text-white border-blue-600'
                                    : 'bg-white text-gray-600 hover:bg-gray-50'"
                                class="px-5 py-2 text-sm font-medium transition-colors border-r border-gray-300 focus:outline-none">
                            In-house
                        </button>
                        <button type="button"
                                @click="form.process_type = 'outside'"
                                :class="form.process_type === 'outside'
                                    ? 'bg-amber-500 text-white border-amber-500'
                                    : 'bg-white text-gray-600 hover:bg-gray-50'"
                                class="px-5 py-2 text-sm font-medium transition-colors focus:outline-none">
                            Outside
                        </button>
                    </div>
                    <p class="mt-1.5 text-xs text-gray-400">
                        <span x-show="form.process_type === 'inhouse'">Performed internally using own machines and staff.</span>
                        <span x-show="form.process_type === 'outside'">Outsourced to an external vendor or sub-contractor.</span>
                    </p>
                </div>

                {{-- Machine Type Default --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Machine Type Default
                        <span class="text-xs text-gray-400 font-normal">optional</span>
                    </label>
                    <input x-model="form.machine_type_default"
                           type="text" placeholder="e.g. CNC Lathe, Welding Robot"
                           list="machine-type-list"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"/>
                    <datalist id="machine-type-list">
                        <template x-for="mt in machineTypes" :key="mt">
                            <option :value="mt"></option>
                        </template>
                    </datalist>
                </div>

                {{-- Description --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Description
                        <span class="text-xs text-gray-400 font-normal">optional</span>
                    </label>
                    <textarea x-model="form.description"
                              rows="2" placeholder="Brief description of this process…"
                              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 resize-none"></textarea>
                </div>

                {{-- Is Active (edit mode only) --}}
                <div x-show="modalMode === 'edit'" class="flex items-center gap-3">
                    <button @click="form.is_active = !form.is_active"
                            :class="form.is_active ? 'bg-indigo-600' : 'bg-gray-300'"
                            class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition-colors duration-200 focus:outline-none">
                        <span :class="form.is_active ? 'translate-x-5' : 'translate-x-0.5'"
                              class="inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white shadow transition-transform duration-200"></span>
                    </button>
                    <span class="text-sm font-medium text-gray-700"
                          x-text="form.is_active ? 'Active' : 'Inactive'"></span>
                </div>
            </div>

            {{-- Modal footer --}}
            <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4 shrink-0">
                <button @click="closeModal()"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button @click="save()"
                        :disabled="saving"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60 transition-colors">
                    <svg x-show="saving" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                    </svg>
                    <span x-text="saving ? 'Saving…' : (modalMode === 'create' ? 'Create Process' : 'Save Changes')"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════
         Deactivate Confirmation Modal
    ══════════════════════════════════════════════════════════ --}}
    <div x-show="showDeactivate"
         x-transition:enter="transition duration-150"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition duration-100"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
         style="display:none">

        <div x-show="showDeactivate"
             x-transition:enter="transition duration-150"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             @click.stop
             class="w-full max-w-sm rounded-2xl bg-white shadow-2xl p-6">

            <div class="flex items-start gap-4">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100">
                    <svg class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <h3 class="font-semibold text-gray-900">Deactivate Process?</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        <span class="font-mono font-bold text-gray-700" x-text="deactivateTarget?.code"></span>
                        will be hidden from routing builder and cannot be used for new parts.
                        Existing routing steps are preserved.
                    </p>
                </div>
            </div>

            <div x-show="formError" x-transition
                 class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                 x-text="formError"></div>

            <div class="mt-5 flex justify-end gap-3">
                <button @click="showDeactivate = false; deactivateTarget = null; formError = null"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button @click="doDeactivate()"
                        :disabled="saving"
                        class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500 disabled:opacity-60 transition-colors">
                    <svg x-show="saving" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                    </svg>
                    <span x-text="saving ? 'Deactivating…' : 'Deactivate'"></span>
                </button>
            </div>
        </div>
    </div>

</div>{{-- /x-data --}}
@endsection

@push('scripts')
<script>
function processMasters(apiToken) {
    return {
        // ── State ────────────────────────────────────────────────
        apiToken,
        items:       [],
        meta:        { total: 0, from: 0, to: 0, last_page: 1 },
        loading:     false,
        error:       null,

        filters: {
            search:               '',
            is_active:            '',
            machine_type_default: '',
        },
        currentPage: 1,

        // Known machine types (populated from loaded data)
        machineTypes: [
            'CNC Lathe', 'Milling Center', 'Welding Robot',
            'Assembly Station', 'Quality Inspection',
            'Surface Grinder', 'Press Machine', 'Drilling Machine',
        ],

        // ── Modal (create / edit) ────────────────────────────────
        showModal:   false,
        modalMode:   'create',   // 'create' | 'edit'
        editId:      null,
        saving:      false,
        formError:   null,

        form: {
            name:                 '',
            code:                 '',
            process_type:         'inhouse',
            machine_type_default: '',
            description:          '',
            is_active:            true,
        },

        // ── Deactivate confirm ───────────────────────────────────
        showDeactivate:   false,
        deactivateTarget: null,

        // ── Lifecycle ────────────────────────────────────────────
        init() {
            this.load();
        },

        // ── API helpers ──────────────────────────────────────────
        async apiFetch(method, path, body = null) {
            const opts = {
                method,
                headers: {
                    'Accept':        'application/json',
                    'Content-Type':  'application/json',
                    'Authorization': 'Bearer ' + this.apiToken,
                },
            };
            if (body) opts.body = JSON.stringify(body);
            const res = await fetch('/api/v1' + path, opts);
            const json = await res.json().catch(() => ({}));
            if (!res.ok) {
                const msg = json.message
                    || (json.errors ? Object.values(json.errors).flat().join(' ') : null)
                    || 'Request failed (' + res.status + ')';
                throw new Error(msg);
            }
            return json;
        },

        // ── Load list ────────────────────────────────────────────
        async load() {
            this.loading = true;
            this.error   = null;
            try {
                const params = new URLSearchParams({ per_page: 25, page: this.currentPage });
                if (this.filters.search)               params.set('search',               this.filters.search);
                if (this.filters.is_active !== '')     params.set('is_active',            this.filters.is_active);
                if (this.filters.machine_type_default) params.set('machine_type_default', this.filters.machine_type_default);

                const data = await this.apiFetch('GET', '/process-masters?' + params);
                this.items = data.data ?? [];
                const m    = data.meta ?? {};
                this.meta  = {
                    total:     m.total     ?? this.items.length,
                    from:      m.from      ?? 1,
                    to:        m.to        ?? this.items.length,
                    last_page: m.last_page ?? 1,
                };

                // Collect unique machine types from results for the filter
                this.items.forEach(i => {
                    if (i.machine_type_default && !this.machineTypes.includes(i.machine_type_default)) {
                        this.machineTypes.push(i.machine_type_default);
                    }
                });
            } catch (e) {
                this.error = e.message;
            } finally {
                this.loading = false;
            }
        },

        goPage(page) {
            if (page < 1 || page > this.meta.last_page) return;
            this.currentPage = page;
            this.load();
        },

        // ── Modal helpers ────────────────────────────────────────
        blankForm() {
            return { name: '', code: '', process_type: 'inhouse', machine_type_default: '', description: '', is_active: true };
        },

        openCreate() {
            this.form      = this.blankForm();
            this.modalMode = 'create';
            this.editId    = null;
            this.formError = null;
            this.showModal = true;
        },

        openEdit(item) {
            this.form = {
                name:                 item.name,
                code:                 item.code,
                process_type:         item.process_type ?? 'inhouse',
                machine_type_default: item.machine_type_default ?? '',
                description:          item.description ?? '',
                is_active:            item.is_active,
            };
            this.modalMode = 'edit';
            this.editId    = item.id;
            this.formError = null;
            this.showModal = true;
        },

        closeModal() {
            this.showModal = false;
            this.formError = null;
        },

        // ── Save (create / update) ───────────────────────────────
        async save() {
            if (!this.form.name.trim()) { this.formError = 'Name is required.'; return; }
            if (!this.form.code.trim()) { this.formError = 'Code is required.'; return; }

            this.saving    = true;
            this.formError = null;

            const payload = {
                name:                 this.form.name.trim(),
                code:                 this.form.code.trim().toUpperCase(),
                process_type:         this.form.process_type,
                machine_type_default: this.form.machine_type_default.trim() || null,
                description:          this.form.description.trim() || null,
            };
            if (this.modalMode === 'edit') payload.is_active = this.form.is_active;

            try {
                if (this.modalMode === 'create') {
                    await this.apiFetch('POST', '/process-masters', payload);
                } else {
                    await this.apiFetch('PUT', '/process-masters/' + this.editId, payload);
                }
                this.closeModal();
                await this.load();
            } catch (e) {
                this.formError = e.message;
            } finally {
                this.saving = false;
            }
        },

        // ── Deactivate ───────────────────────────────────────────
        confirmDeactivate(item) {
            this.deactivateTarget = item;
            this.formError        = null;
            this.showDeactivate   = true;
        },

        async doDeactivate() {
            if (!this.deactivateTarget) return;
            this.saving    = true;
            this.formError = null;
            try {
                await this.apiFetch('DELETE', '/process-masters/' + this.deactivateTarget.id);
                this.showDeactivate   = false;
                this.deactivateTarget = null;
                await this.load();
            } catch (e) {
                this.formError = e.message;
            } finally {
                this.saving = false;
            }
        },

        // ── Reactivate (PUT is_active=true) ──────────────────────
        async reactivate(item) {
            this.error = null;
            try {
                await this.apiFetch('PUT', '/process-masters/' + item.id, { is_active: true });
                await this.load();
            } catch (e) {
                this.error = e.message;
            }
        },
    };
}
</script>
@endpush

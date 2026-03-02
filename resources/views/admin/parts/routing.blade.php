{{--
    Part Routing Builder
    =====================
    Route:  GET /admin/parts/{part}/routing
    Auth:   factory-admin | super-admin

    Depends on:
      - SortableJS  1.15  (CDN or bundled)
      - Alpine.js   3.x   (CDN or bundled)
      - Tailwind CSS 3.x  (assumed in layout)

    Data pre-loaded from controller:
      $part       — Part model with processes.processMaster loaded
      $palette    — Collection<ProcessMaster> (all active, from ProcessMasterService::palette())
      $apiToken   — Sanctum token string OR null (uses cookie auth if null)
--}}
@extends('admin.layouts.app')

@section('title', "Routing — {$part->part_number}")

@push('head')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
@endpush

@section('content')

{{-- ── Page Header ──────────────────────────────────────── --}}
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">
            Process Routing
        </h1>
        <p class="mt-1 text-sm text-gray-500">
            {{ $part->part_number }} — {{ $part->name }}
            <span class="ml-2 text-xs text-gray-400">({{ $part->customer->name ?? '—' }})</span>
        </p>
    </div>
    <a href="{{ route('admin.parts.show', $part) }}"
       class="text-sm text-indigo-600 hover:underline">
        ← Back to Part
    </a>
</div>

{{-- ── Routing Builder ──────────────────────────────────── --}}
<div
    x-data="routingBuilder({
        partId:  {{ $part->id }},
        token:   {{ $apiToken ? json_encode($apiToken) : 'null' }},
        initial: {{ Js::from($initialSteps) }},
        palette: {{ Js::from($paletteData) }}
    })"
    class="flex gap-6"
>

    {{-- ── Left: Process Palette ──────────────────────────── --}}
    <aside class="w-72 shrink-0">
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm">

            <div class="border-b border-gray-100 px-4 py-3">
                <h2 class="text-sm font-semibold text-gray-700">Process Library</h2>
                <p class="mt-0.5 text-xs text-gray-400">Double-click to add to routing</p>
            </div>

            {{-- Search --}}
            <div class="px-3 pt-3">
                <input
                    x-model="paletteSearch"
                    type="text"
                    placeholder="Search processes…"
                    class="w-full rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300"
                >
            </div>

            {{-- Loading skeleton --}}
            <template x-if="loading">
                <div class="space-y-2 p-3">
                    <template x-for="i in 5" :key="i">
                        <div class="h-12 animate-pulse rounded-lg bg-gray-100"></div>
                    </template>
                </div>
            </template>

            {{-- Process list --}}
            <ul class="max-h-[calc(100vh-240px)] overflow-y-auto p-3 space-y-1">
                <template x-for="pm in filteredPalette" :key="pm.id">
                    <li
                        @dblclick="addStep(pm)"
                        class="group cursor-pointer rounded-lg border border-transparent px-3 py-2 hover:border-indigo-200 hover:bg-indigo-50 transition-colors"
                        :title="`Double-click to add · ${pm.description ?? ''}`"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-800" x-text="pm.name"></span>
                            <button
                                @click="addStep(pm)"
                                class="invisible text-indigo-500 group-hover:visible text-lg leading-none"
                                title="Add step"
                            >＋</button>
                        </div>
                        <div class="mt-0.5 flex items-center gap-2 text-xs text-gray-400">
                            <span x-text="pm.code" class="font-mono"></span>
                            <template x-if="pm.machineType">
                                <span class="rounded bg-gray-100 px-1" x-text="pm.machineType"></span>
                            </template>
                            <template x-if="pm.standardTime > 0">
                                <span x-text="`${pm.standardTime} min`"></span>
                            </template>
                            <template x-if="pm.standardTime === 0">
                                <span class="italic">no default time</span>
                            </template>
                        </div>
                    </li>
                </template>

                <template x-if="filteredPalette.length === 0 && !loading">
                    <li class="py-4 text-center text-sm text-gray-400">
                        No processes found
                    </li>
                </template>
            </ul>

        </div>
    </aside>

    {{-- ── Right: Routing Canvas ───────────────────────────── --}}
    <main class="flex-1 min-w-0">
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm">

            {{-- Toolbar --}}
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                <div class="flex items-center gap-4">
                    <h2 class="text-sm font-semibold text-gray-700">
                        Routing Sequence
                        <span
                            class="ml-1.5 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500"
                            x-text="steps.length"
                        ></span>
                    </h2>

                    {{-- Total cycle time badge --}}
                    <div class="flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>Total: <strong x-text="totalCycleTimeFormatted"></strong></span>
                        <button
                            @click="previewCycleTime"
                            :disabled="previewing || steps.length === 0"
                            class="ml-1 text-blue-500 hover:text-blue-700 disabled:opacity-40"
                            title="Confirm with server"
                        >
                            <svg x-show="!previewing" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            <svg x-show="previewing" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                            </svg>
                        </button>
                    </div>

                    {{-- Server-confirmed preview result --}}
                    <template x-if="previewResult">
                        <div class="rounded-full bg-green-50 px-3 py-1 text-xs font-medium text-green-700">
                            ✓ Server: <strong x-text="`${previewResult.total_cycle_time} min`"></strong>
                        </div>
                    </template>
                </div>

                <div class="flex items-center gap-2">
                    {{-- Saved indicator --}}
                    <transition
                        enter-active-class="transition ease-out duration-200"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="transition ease-in duration-100"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                    >
                        <span x-show="saved" class="text-xs font-medium text-green-600">
                            ✓ Saved
                        </span>
                    </transition>

                    {{-- Save button --}}
                    <button
                        @click="save"
                        :disabled="saving || steps.length === 0"
                        class="flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50 transition-colors"
                    >
                        <svg x-show="saving" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                        </svg>
                        <span x-text="saving ? 'Saving…' : 'Save Routing'"></span>
                    </button>
                </div>
            </div>

            {{-- Error alert --}}
            <template x-if="errorMessage">
                <div class="mx-5 mt-4 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <svg class="mt-0.5 h-4 w-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10A8 8 0 112 10a8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <span x-text="errorMessage"></span>
                        <button @click="errorMessage = null" class="ml-2 underline">Dismiss</button>
                    </div>
                </div>
            </template>

            {{-- Empty state --}}
            <template x-if="steps.length === 0">
                <div class="flex flex-col items-center justify-center py-16 text-center">
                    <svg class="h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    <p class="mt-3 text-sm font-medium text-gray-500">No process steps yet</p>
                    <p class="mt-1 text-xs text-gray-400">Double-click a process from the library to add it</p>
                </div>
            </template>

            {{-- Step list (sortable) --}}
            <ul x-ref="stepList" class="p-5 space-y-2">
                <template x-for="(step, index) in steps" :key="step._key">
                    <li class="group flex items-start gap-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 hover:border-indigo-200 hover:bg-white transition-colors">

                        {{-- Drag handle --}}
                        <span class="drag-handle mt-1 cursor-grab text-gray-300 hover:text-gray-500 active:cursor-grabbing select-none text-xl leading-none">
                            ⠿
                        </span>

                        {{-- Sequence badge --}}
                        <span
                            class="mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-700"
                            x-text="step.sequenceOrder"
                        ></span>

                        {{-- Step info --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-medium text-gray-900" x-text="step.processMasterName"></span>
                                <span class="font-mono text-xs text-gray-400 bg-gray-100 rounded px-1.5 py-0.5" x-text="step.processMasterCode"></span>
                                <template x-if="step.machineTypeRequired">
                                    <span class="rounded bg-amber-50 border border-amber-200 px-1.5 py-0.5 text-xs text-amber-700" x-text="step.machineTypeRequired"></span>
                                </template>
                            </div>

                            {{-- Cycle time row --}}
                            <div class="mt-2 flex items-center gap-4 flex-wrap">

                                {{-- Default time (read-only) --}}
                                <div class="text-xs text-gray-500">
                                    Default:
                                    <span
                                        class="font-mono"
                                        x-text="step.defaultCycleTime > 0 ? `${step.defaultCycleTime} min` : 'not set'"
                                    ></span>
                                </div>

                                {{-- Override field --}}
                                <div class="flex items-center gap-1.5">
                                    <label class="text-xs text-gray-500" :for="`override-${index}`">Override (min):</label>
                                    <input
                                        :id="`override-${index}`"
                                        x-model="step.overrideCycleTime"
                                        @input="previewResult = null"
                                        type="number"
                                        min="0.01"
                                        step="0.5"
                                        placeholder="Use default"
                                        class="w-28 rounded border border-gray-200 px-2 py-1 text-xs focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300"
                                        :class="{ 'border-amber-400 bg-amber-50': hasOverride(step) }"
                                    >
                                    <button
                                        x-show="hasOverride(step)"
                                        @click="clearOverride(step)"
                                        class="text-xs text-gray-400 hover:text-red-500"
                                        title="Clear override"
                                    >✕</button>
                                </div>

                                {{-- Effective time badge --}}
                                <div class="text-xs font-medium">
                                    <span class="text-gray-400">Effective:</span>
                                    <span
                                        class="ml-1 font-mono"
                                        :class="hasOverride(step) ? 'text-amber-600' : 'text-gray-700'"
                                        x-text="`${effectiveCycleTime(step)} min`"
                                    ></span>
                                    <span x-show="hasOverride(step)" class="ml-1 text-amber-500 text-xs">↑ overridden</span>
                                </div>
                            </div>

                            {{-- Notes --}}
                            <div class="mt-2">
                                <input
                                    x-model="step.notes"
                                    type="text"
                                    placeholder="Notes (optional)"
                                    maxlength="500"
                                    class="w-full rounded border border-transparent bg-transparent px-0 py-0.5 text-xs text-gray-500 placeholder-gray-300 hover:border-gray-200 focus:border-gray-300 focus:bg-white focus:outline-none focus:px-2 transition-all"
                                >
                            </div>
                        </div>

                        {{-- Remove button --}}
                        <button
                            @click="removeStep(index)"
                            class="mt-1 shrink-0 text-gray-300 hover:text-red-400 transition-colors"
                            title="Remove step"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </li>
                </template>
            </ul>

            {{-- Footer summary --}}
            <template x-if="steps.length > 0">
                <div class="border-t border-gray-100 px-5 py-3 flex items-center justify-between text-xs text-gray-500">
                    <span x-text="`${steps.length} step${steps.length !== 1 ? 's' : ''}`"></span>
                    <div class="flex items-center gap-1.5">
                        <span>Total cycle time:</span>
                        <span class="font-semibold text-sm text-gray-800" x-text="totalCycleTimeFormatted"></span>
                        <template x-if="previewResult">
                            <span class="text-green-600">(server confirmed: <span x-text="`${previewResult.total_cycle_time} min`"></span>)</span>
                        </template>
                    </div>
                </div>
            </template>

        </div>
    </main>

</div>

@endSection

@push('scripts')
<script>
function routingBuilder(config) {
    config = config || {};
    return {
        partId:    config.partId,
        apiToken:  config.token ?? null,
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.content ?? '',

        steps: (config.initial ?? []).map(function(s, i) {
            return {
                _key:                s.id ? ('srv-' + s.id) : ('new-' + i),
                processMasterId:     s.process_master_id,
                processMasterName:   s.process_master_name ?? '',
                processMasterCode:   s.process_master_code ?? '',
                machineTypeDefault:  s.machine_type_default ?? null,
                defaultCycleTime:    parseFloat(s.default_cycle_time ?? 0),
                overrideCycleTime:   s.standard_cycle_time != null ? String(s.standard_cycle_time) : '',
                machineTypeRequired: s.machine_type_required ?? null,
                notes:               s.notes ?? null,
                sequenceOrder:       i + 1,
            };
        }),

        palette: (config.palette ?? []).map(function(pm) {
            return {
                id:          pm.id,
                name:        pm.name,
                code:        pm.code,
                standardTime: parseFloat(pm.standard_time ?? 0),
                machineType: pm.machine_type_default ?? null,
                description: pm.description ?? null,
            };
        }),

        paletteSearch: '',
        loading:       false,
        saving:        false,
        previewing:    false,
        saved:         false,
        errorMessage:  null,
        previewResult: null,

        get totalCycleTime() {
            return this.steps.reduce(function(sum, step) {
                var override = parseFloat(step.overrideCycleTime);
                var minutes  = (!isNaN(override) && step.overrideCycleTime !== '')
                    ? override : step.defaultCycleTime;
                return sum + (isNaN(minutes) ? 0 : minutes);
            }, 0);
        },

        get totalCycleTimeFormatted() {
            var t = this.totalCycleTime;
            var h = Math.floor(t / 60);
            var m = (t % 60).toFixed(1);
            return h > 0 ? (h + 'h ' + m + 'min') : (m + ' min');
        },

        get filteredPalette() {
            if (!this.paletteSearch) return this.palette;
            var q = this.paletteSearch.toLowerCase();
            return this.palette.filter(function(pm) {
                return pm.name.toLowerCase().includes(q)
                    || pm.code.toLowerCase().includes(q)
                    || (pm.machineType ?? '').toLowerCase().includes(q);
            });
        },

        hasOverride(step) {
            var v = parseFloat(step.overrideCycleTime);
            return !isNaN(v) && step.overrideCycleTime !== '' && v !== step.defaultCycleTime;
        },

        effectiveCycleTime(step) {
            var v = parseFloat(step.overrideCycleTime);
            return (!isNaN(v) && step.overrideCycleTime !== '') ? v : step.defaultCycleTime;
        },

        init() {
            this.$nextTick(() => this._initSortable());
            if (this.palette.length === 0) {
                this.loadPalette();
            }
        },

        _initSortable() {
            var listEl = this.$refs.stepList;
            if (!listEl || typeof Sortable === 'undefined') return;
            var self = this;
            Sortable.create(listEl, {
                animation:  150,
                handle:     '.drag-handle',
                ghostClass: 'opacity-40',
                dragClass:  'shadow-xl',
                onEnd: function(evt) {
                    if (evt.oldIndex === evt.newIndex) return;
                    var moved = self.steps.splice(evt.oldIndex, 1)[0];
                    self.steps.splice(evt.newIndex, 0, moved);
                    self._renumber();
                },
            });
        },

        _renumber() {
            this.steps.forEach(function(step, i) { step.sequenceOrder = i + 1; });
        },

        _uniqueKey() {
            return 'new-' + Date.now() + '-' + Math.random().toString(36).slice(2, 7);
        },

        async loadPalette() {
            this.loading = true;
            try {
                var res = await this._fetch('GET', '/api/v1/process-masters/palette');
                this.palette = (res.data ?? []).map(function(pm) {
                    return {
                        id:          pm.id,
                        name:        pm.name,
                        code:        pm.code,
                        standardTime: parseFloat(pm.standard_time ?? 0),
                        machineType: pm.machine_type_default ?? null,
                        description: pm.description ?? null,
                    };
                });
            } catch(e) {
                this.errorMessage = 'Could not load process library.';
            } finally {
                this.loading = false;
            }
        },

        addStep(pm) {
            this.steps.push({
                _key:                this._uniqueKey(),
                processMasterId:     pm.id,
                processMasterName:   pm.name,
                processMasterCode:   pm.code,
                machineTypeDefault:  pm.machineType,
                defaultCycleTime:    pm.standardTime,
                overrideCycleTime:   '',
                machineTypeRequired: pm.machineType,
                notes:               null,
                sequenceOrder:       this.steps.length + 1,
            });
        },

        removeStep(index) {
            this.steps.splice(index, 1);
            this._renumber();
        },

        clearOverride(step) {
            step.overrideCycleTime = '';
        },

        async previewCycleTime() {
            if (this.steps.length === 0) return;
            this.previewing    = true;
            this.errorMessage  = null;
            this.previewResult = null;
            try {
                var payload = {
                    steps: this.steps.map(function(s) {
                        return {
                            process_master_id:   s.processMasterId,
                            standard_cycle_time: s.overrideCycleTime !== ''
                                ? parseFloat(s.overrideCycleTime) : null,
                        };
                    }),
                };
                this.previewResult = await this._fetch('POST', '/api/v1/process-masters/preview-cycle-time', payload);
            } catch(e) {
                this.errorMessage = e.message ?? 'Preview failed.';
            } finally {
                this.previewing = false;
            }
        },

        async save() {
            if (this.steps.length === 0) {
                this.errorMessage = 'Add at least one process step before saving.';
                return;
            }
            this.saving       = true;
            this.errorMessage = null;
            this.saved        = false;
            try {
                var payload = {
                    processes: this.steps.map(function(s) {
                        return {
                            process_master_id:     s.processMasterId,
                            machine_type_required: s.machineTypeRequired ?? null,
                            standard_cycle_time:   s.overrideCycleTime !== ''
                                ? parseFloat(s.overrideCycleTime) : null,
                            notes:                 s.notes ?? null,
                        };
                    }),
                };
                var res = await this._fetch('PUT', '/api/v1/parts/' + this.partId + '/processes', payload);
                var serverPart = res.data;
                if (serverPart?.processes) {
                    this.steps = serverPart.processes.map(function(p, i) {
                        return {
                            _key:                'srv-' + (p.id ?? i),
                            processMasterId:     p.process_master?.id ?? p.process_master_id,
                            processMasterName:   p.process_master?.name ?? '',
                            processMasterCode:   p.process_master?.code ?? '',
                            machineTypeDefault:  p.process_master?.machine_type_default ?? null,
                            defaultCycleTime:    parseFloat(p.process_master?.standard_time ?? 0),
                            overrideCycleTime:   p.standard_cycle_time != null ? String(p.standard_cycle_time) : '',
                            machineTypeRequired: p.machine_type_required ?? null,
                            notes:               p.notes ?? null,
                            sequenceOrder:       p.sequence_order,
                        };
                    });
                }
                this.saved = true;
                setTimeout(() => { this.saved = false; }, 3000);
                this.$dispatch('routing-saved', { part: serverPart });
            } catch(e) {
                this.errorMessage = e.message ?? 'Save failed. Please try again.';
            } finally {
                this.saving = false;
            }
        },

        async _fetch(method, url, body) {
            var headers = {
                'Content-Type': 'application/json',
                'Accept':       'application/json',
                'X-CSRF-TOKEN': this.csrfToken,
            };
            if (this.apiToken) {
                headers['Authorization'] = 'Bearer ' + this.apiToken;
            }
            var res = await fetch(url, {
                method:  method,
                headers: headers,
                body:    body ? JSON.stringify(body) : undefined,
            });
            var json = await res.json();
            if (!res.ok) {
                if (res.status === 422 && json.errors) {
                    var firstError = Object.values(json.errors)[0]?.[0];
                    throw new Error(firstError ?? json.message ?? 'Validation error.');
                }
                throw new Error(json.message ?? ('Request failed (' + res.status + ').'));
            }
            return json;
        },
    };
}
</script>
@endpush

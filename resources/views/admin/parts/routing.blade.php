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

                    {{-- Cycle time / part badge --}}
                    <div class="flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>Cycle/part: <strong x-text="totalCycleTimeFormatted"></strong></span>
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

                    {{-- Setup time badge --}}
                    <div x-show="totalSetupTime > 0"
                         class="flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <span>Setup: <strong x-text="totalSetupTimeFormatted"></strong></span>
                    </div>

                    {{-- Ideal production badge --}}
                    <template x-if="steps.length > 0 && totalCycleTime > 0">
                        <div class="flex items-center gap-1.5 rounded-full bg-green-50 px-3 py-1 text-xs font-medium text-green-700">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            <span>Ideal:&nbsp;<strong x-text="idealPerShift + ' pcs'"></strong></span>
                            <span class="text-green-500">/</span>
                            <input
                                x-model.number="shiftMinutes"
                                type="number"
                                min="60"
                                max="1440"
                                step="30"
                                class="w-14 rounded border border-green-200 bg-white px-1.5 py-0.5 text-xs text-green-700 focus:border-green-400 focus:outline-none"
                                title="Shift duration in minutes"
                            >
                            <span class="text-green-500">min shift</span>
                        </div>
                    </template>

                    {{-- Server-confirmed preview result --}}
                    <template x-if="previewResult">
                        <div class="rounded-full bg-green-50 px-3 py-1 text-xs font-medium text-green-700">
                            ✓ Server: <strong x-text="toMMSS(previewResult.total_cycle_time)"></strong>
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

                                {{-- Override field (MM:SS) --}}
                                <div class="flex items-center gap-1.5">
                                    <label class="text-xs text-gray-500" :for="`override-${index}`">Cycle (MM:SS):</label>
                                    <input
                                        :id="`override-${index}`"
                                        :value="step.overrideCycleTime !== '' ? toMMSS(step.overrideCycleTime) : ''"
                                        @change="step.overrideCycleTime = parseMMSS($event.target.value); previewResult = null; $event.target.value = step.overrideCycleTime !== '' ? toMMSS(step.overrideCycleTime) : ''"
                                        type="text"
                                        placeholder="MM:SS"
                                        class="w-24 rounded border border-gray-200 px-2 py-1 text-xs font-mono focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300"
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
                                        x-text="toMMSS(effectiveCycleTime(step))"
                                    ></span>
                                    <span x-show="hasOverride(step)" class="ml-1 text-amber-500 text-xs">↑ overridden</span>
                                </div>
                            </div>

                            {{-- Setup time + Load/Unload + Process type row --}}
                            <div class="mt-2 flex items-center gap-4 flex-wrap">

                                {{-- Setup time (MM:SS) --}}
                                <div class="flex items-center gap-1.5">
                                    <label class="text-xs text-gray-500" :for="`setup-${index}`">Setup (MM:SS):</label>
                                    <input
                                        :id="`setup-${index}`"
                                        :value="step.setupTime !== '' && step.setupTime != null ? toMMSS(step.setupTime) : ''"
                                        @change="step.setupTime = parseMMSS($event.target.value); $event.target.value = step.setupTime !== '' ? toMMSS(step.setupTime) : ''"
                                        type="text"
                                        placeholder="MM:SS"
                                        class="w-20 rounded border border-gray-200 px-2 py-1 text-xs font-mono focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300"
                                    >
                                </div>

                                {{-- Load/Unload time (MM:SS) --}}
                                <div class="flex items-center gap-1.5">
                                    <label class="text-xs text-gray-500" :for="`loadunload-${index}`">Load/Unload (MM:SS):</label>
                                    <input
                                        :id="`loadunload-${index}`"
                                        :value="step.loadUnloadTime !== '' && step.loadUnloadTime != null ? toMMSS(step.loadUnloadTime) : ''"
                                        @change="step.loadUnloadTime = parseMMSS($event.target.value); $event.target.value = step.loadUnloadTime !== '' ? toMMSS(step.loadUnloadTime) : ''"
                                        type="text"
                                        placeholder="MM:SS"
                                        class="w-20 rounded border border-gray-200 px-2 py-1 text-xs font-mono focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300"
                                    >
                                </div>

                                {{-- Combined time per part badge --}}
                                <template x-if="(effectiveCycleTime(step) > 0 || (parseFloat(step.loadUnloadTime) > 0))">
                                    <div class="text-xs font-medium">
                                        <span class="text-gray-400">Per part:</span>
                                        <span class="ml-1 font-mono text-indigo-600"
                                              x-text="toMMSS(effectiveCycleTime(step) + (parseFloat(step.loadUnloadTime) || 0))"></span>
                                    </div>
                                </template>

                                {{-- Process type toggle --}}
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs text-gray-500">Type:</span>
                                    <div class="flex rounded-lg border border-gray-200 overflow-hidden text-xs font-medium">
                                        <button
                                            type="button"
                                            @click="step.processType = 'inhouse'"
                                            :class="step.processType === 'inhouse'
                                                ? 'bg-indigo-600 text-white px-2.5 py-1'
                                                : 'bg-white text-gray-500 hover:bg-gray-50 px-2.5 py-1'"
                                        >In-house</button>
                                        <button
                                            type="button"
                                            @click="step.processType = 'outside'"
                                            :class="step.processType === 'outside'
                                                ? 'bg-amber-500 text-white px-2.5 py-1'
                                                : 'bg-white text-gray-500 hover:bg-gray-50 px-2.5 py-1'"
                                        >Outside</button>
                                    </div>
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
                <div class="border-t border-gray-100 px-5 py-3 space-y-1.5">
                    {{-- Steps count + cycle time --}}
                    <div class="flex items-center justify-between text-xs text-gray-500">
                        <span x-text="`${steps.length} step${steps.length !== 1 ? 's' : ''}`"></span>
                        <div class="flex items-center gap-1.5">
                            <span>Cycle time/part:</span>
                            <span class="font-semibold text-sm text-gray-800" x-text="totalCycleTimeFormatted"></span>
                            <template x-if="previewResult">
                                <span class="text-green-600">(server: <span x-text="toMMSS(previewResult.total_cycle_time)"></span>)</span>
                            </template>
                        </div>
                    </div>

                    {{-- Production formula --}}
                    <template x-if="totalCycleTime > 0 || totalLoadUnloadTime > 0">
                        <div class="rounded-lg bg-gray-50 border border-gray-100 px-4 py-2 text-xs text-gray-600">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-medium text-gray-700">Production formula:</span>

                                {{-- Setup block --}}
                                <span class="rounded bg-amber-100 px-2 py-0.5 text-amber-800 font-mono">
                                    Setup <span x-text="toMMSS(totalSetupTime)"></span>
                                </span>
                                <span class="text-gray-400">+</span>

                                {{-- Qty × (load/unload + cycle) --}}
                                <span class="rounded bg-blue-100 px-2 py-0.5 text-blue-800 font-mono">
                                    Qty ×
                                    <template x-if="totalLoadUnloadTime > 0">
                                        <span>(<span x-text="toMMSS(totalLoadUnloadTime)"></span> L/U + <span x-text="toMMSS(totalCycleTime)"></span> cycle = <span x-text="toMMSS(timePerPart)"></span>)</span>
                                    </template>
                                    <template x-if="totalLoadUnloadTime <= 0">
                                        <span x-text="toMMSS(totalCycleTime)"></span>
                                    </template>
                                </span>
                                <span class="text-gray-400">=</span>

                                {{-- Total shift time --}}
                                <span class="text-gray-500">
                                    ≤ <span x-text="shiftMinutes"></span> min shift
                                </span>

                                <span class="mx-1 text-gray-300">|</span>

                                {{-- Ideal result --}}
                                <span class="font-semibold text-green-700">
                                    Ideal: <span x-text="idealPerShift"></span> pcs / shift
                                </span>
                                <span class="text-gray-400 text-xs">
                                    (<span x-text="toMMSS(firstPartTime)"></span> to first part)
                                </span>
                            </div>
                        </div>
                    </template>
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
                overrideCycleTime:   s.standard_cycle_time != null ? String(s.standard_cycle_time) : '',
                setupTime:           s.setup_time != null ? String(s.setup_time) : '',
                loadUnloadTime:      s.load_unload_time != null ? String(s.load_unload_time) : '',
                processType:         s.process_type ?? 'inhouse',
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
        shiftMinutes:  480,   // default 8-hour shift

        // ── Totals ────────────────────────────────────────────

        // Sum of effective cycle times (per-part production time)
        get totalCycleTime() {
            return this.steps.reduce(function(sum, step) {
                var override = parseFloat(step.overrideCycleTime);
                var minutes  = (!isNaN(override) && step.overrideCycleTime !== '') ? override : 0;
                return sum + minutes;
            }, 0);
        },

        get totalCycleTimeFormatted() {
            return this.toMMSS(this.totalCycleTime);
        },

        // Sum of one-time setup times across all steps
        get totalSetupTime() {
            return this.steps.reduce(function(sum, step) {
                var t = parseFloat(step.setupTime);
                return sum + (isNaN(t) || step.setupTime === '' ? 0 : t);
            }, 0);
        },

        get totalSetupTimeFormatted() {
            return this.toMMSS(this.totalSetupTime);
        },

        // Sum of per-part load/unload times across all steps
        get totalLoadUnloadTime() {
            return this.steps.reduce(function(sum, step) {
                var t = parseFloat(step.loadUnloadTime);
                return sum + (isNaN(t) || step.loadUnloadTime === '' ? 0 : t);
            }, 0);
        },

        // Total time consumed per part = cycle + load/unload
        get timePerPart() {
            return this.totalCycleTime + this.totalLoadUnloadTime;
        },

        // First-part lead time = setup (once) + one cycle + one load/unload
        get firstPartTime() {
            return this.totalSetupTime + this.timePerPart;
        },

        // Ideal qty per shift:
        //   available  = shiftMinutes - totalSetupTime
        //   ideal      = floor(available / timePerPart)   (cycle + load/unload)
        get idealPerShift() {
            var perPart = this.timePerPart;
            if (perPart <= 0) return 0;
            var available = (parseFloat(this.shiftMinutes) || 480) - this.totalSetupTime;
            if (available <= 0) return 0;
            return Math.floor(available / perPart);
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

        // ── MM:SS helpers ─────────────────────────────────────

        // Convert decimal minutes → "MM:SS" string  (e.g. 1.5 → "1:30")
        toMMSS(decimalMinutes) {
            var v = parseFloat(decimalMinutes);
            if (isNaN(v) || v <= 0) return '0:00';
            var mm = Math.floor(v);
            var ss = Math.round((v - mm) * 60);
            if (ss === 60) { mm++; ss = 0; }
            return mm + ':' + (ss < 10 ? '0' + ss : String(ss));
        },

        // Parse "MM:SS" or plain decimal string → decimal minutes (or '' if empty)
        parseMMSS(str) {
            if (!str || String(str).trim() === '') return '';
            str = String(str).trim();
            if (str.indexOf(':') !== -1) {
                var parts = str.split(':');
                var mm = parseInt(parts[0] || '0', 10);
                var ss = parseInt(parts[1] || '0', 10);
                if (isNaN(mm) || isNaN(ss)) return '';
                return mm + ss / 60;
            }
            var v = parseFloat(str);
            return isNaN(v) ? '' : v;
        },

        hasOverride(step) {
            var v = parseFloat(step.overrideCycleTime);
            return !isNaN(v) && step.overrideCycleTime !== '';
        },

        effectiveCycleTime(step) {
            var v = parseFloat(step.overrideCycleTime);
            return (!isNaN(v) && step.overrideCycleTime !== '') ? v : 0;
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
                overrideCycleTime:   '',
                setupTime:           '',
                loadUnloadTime:      '',
                processType:         'inhouse',
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
                            setup_time:            s.setupTime !== '' && s.setupTime != null
                                ? parseFloat(s.setupTime) : null,
                            load_unload_time:      s.loadUnloadTime !== '' && s.loadUnloadTime != null
                                ? parseFloat(s.loadUnloadTime) : null,
                            process_type:          s.processType ?? 'inhouse',
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
                            overrideCycleTime:   p.standard_cycle_time != null ? String(p.standard_cycle_time) : '',
                            setupTime:           p.setup_time != null ? String(p.setup_time) : '',
                            loadUnloadTime:      p.load_unload_time != null ? String(p.load_unload_time) : '',
                            processType:         p.process_type ?? 'inhouse',
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

{{--
    Parts — Full CRUD + Routing Builder
    =====================================
    Create, edit, discontinue parts, and navigate to the routing builder.
    All data operations via Alpine.js → /api/v1/parts.
    Customer dropdown loaded from /api/v1/customers.
--}}
@extends('admin.layouts.app')

@section('title', 'Parts')

@section('header-actions')
<button onclick="window.dispatchEvent(new CustomEvent('open-create-part'))"
        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white
               hover:bg-indigo-700 transition-colors">
    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
    </svg>
    New Part
</button>
@endsection

@section('content')

<div x-data="partsPage(
        {{ json_encode($apiToken) }},
        {{ json_encode($factoryId ?? null) }},
        {{ json_encode($factories->map(fn($f) => ['id' => $f->id, 'name' => $f->name])->values()->all()) }}
    )" x-init="init()"
     @open-create-part.window="openCreate()">

    {{-- ── Toast ────────────────────────────────────────────── --}}
    <template x-if="toast.show">
        <div class="fixed top-5 right-5 z-50 flex items-center gap-3 rounded-xl px-5 py-3 text-sm font-medium shadow-lg"
             :class="toast.type === 'success' ? 'bg-green-600 text-white' : 'bg-red-600 text-white'">
            <span x-text="toast.message"></span>
            <button @click="toast.show = false" class="ml-1 opacity-70 hover:opacity-100">&times;</button>
        </div>
    </template>

    {{-- ── Filter Bar ───────────────────────────────────────── --}}
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <input x-model.debounce.300ms="search" @input="load(1)" type="text"
               placeholder="Search part number or name…"
               class="w-full max-w-xs rounded-lg border border-gray-200 px-3 py-2 text-sm
                      focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">

        <select x-model="filterCustomer" @change="load(1)"
                class="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700
                       focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
            <option value="">All Customers</option>
            <template x-for="c in customerDropdown" :key="c.id">
                <option :value="c.id" x-text="c.name + ' (' + c.code + ')'"></option>
            </template>
        </select>

        <select x-model="filterStatus" @change="load(1)"
                class="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700
                       focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
            <option value="">All Statuses</option>
            <option value="active">Active</option>
            <option value="discontinued">Discontinued</option>
        </select>

        <span class="ml-auto text-sm text-gray-400">
            <span x-text="total"></span> parts
        </span>
    </div>

    {{-- ── Table Card ───────────────────────────────────────── --}}
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200">

        {{-- Skeleton --}}
        <template x-if="loading && parts.length === 0">
            <div class="space-y-px p-4">
                <template x-for="i in 5" :key="i">
                    <div class="flex items-center gap-4 py-3">
                        <div class="h-4 w-24 animate-pulse rounded bg-gray-100"></div>
                        <div class="h-4 w-40 animate-pulse rounded bg-gray-100"></div>
                        <div class="ml-auto flex gap-2">
                            <div class="h-7 w-16 animate-pulse rounded bg-gray-100"></div>
                            <div class="h-7 w-24 animate-pulse rounded bg-gray-100"></div>
                        </div>
                    </div>
                </template>
            </div>
        </template>

        {{-- Error --}}
        <template x-if="error">
            <div class="px-5 py-4 text-sm text-red-600" x-text="error"></div>
        </template>

        {{-- Table --}}
        <template x-if="parts.length > 0">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-gray-100 bg-gray-50">
                        <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                            <th class="px-5 py-3">Part Number</th>
                            <th class="px-5 py-3">Name</th>
                            <th class="px-5 py-3">Customer</th>
                            <th class="px-5 py-3">Unit</th>
                            <th class="px-5 py-3 text-center">Cycle Time</th>
                            <th class="px-5 py-3 text-center">Routing</th>
                            <th class="px-5 py-3 text-center">Files</th>
                            <th class="px-5 py-3 text-center">Status</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <template x-for="p in parts" :key="p.id">
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-3 font-mono text-sm font-medium text-gray-900"
                                    x-text="p.part_number"></td>
                                <td class="px-5 py-3 text-gray-700" x-text="p.name"></td>
                                <td class="px-5 py-3 text-xs text-gray-500"
                                    x-text="p.customer?.name ?? '—'"></td>
                                <td class="px-5 py-3 text-xs text-gray-500 uppercase"
                                    x-text="p.unit ?? '—'"></td>
                                <td class="px-5 py-3 text-center text-xs text-gray-500"
                                    x-text="p.total_cycle_time ? p.total_cycle_time + ' min' : '—'"></td>
                                <td class="px-5 py-3 text-center">
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                                          :class="(p.processes_count ?? 0) > 0
                                              ? 'bg-green-100 text-green-800'
                                              : 'bg-gray-100 text-gray-500'"
                                          x-text="(p.processes_count ?? 0) + ' steps'"></span>
                                </td>
                                {{-- Files / Drawings --}}
                                <td class="px-5 py-3 text-center">
                                    <button @click="openDrawings(p)" type="button"
                                            class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium transition-colors"
                                            :class="(p.drawings_count ?? 0) > 0
                                                ? 'bg-amber-50 text-amber-700 hover:bg-amber-100'
                                                : 'text-gray-300 hover:text-gray-500 hover:bg-gray-50'"
                                            :title="(p.drawings_count ?? 0) > 0 ? (p.drawings_count + ' drawing(s)') : 'No drawings'">
                                        {{-- paperclip icon --}}
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                        </svg>
                                        <span x-show="(p.drawings_count ?? 0) > 0" x-text="p.drawings_count"></span>
                                    </button>
                                </td>

                                <td class="px-5 py-3 text-center">
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                                          :class="p.status === 'active'
                                              ? 'bg-green-100 text-green-700'
                                              : 'bg-orange-100 text-orange-700'"
                                          x-text="p.status ?? 'unknown'"></span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button @click="openEdit(p)"
                                                class="rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700
                                                       hover:bg-indigo-100 transition-colors">
                                            Edit
                                        </button>
                                        <a :href="`/admin/parts/${p.id}/routing`"
                                           class="rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700
                                                  hover:bg-blue-100 transition-colors">
                                            Routing
                                        </a>
                                        <button @click="openSchedule(p)"
                                                class="rounded-lg bg-violet-50 px-3 py-1.5 text-xs font-medium text-violet-700
                                                       hover:bg-violet-100 transition-colors">
                                            Schedule
                                        </button>
                                        <button @click="confirmDiscontinue(p)"
                                                x-show="p.status === 'active'"
                                                class="rounded-lg bg-red-50 px-3 py-1.5 text-xs font-medium text-red-600
                                                       hover:bg-red-100 transition-colors">
                                            Discontinue
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </template>

        {{-- Empty state --}}
        <template x-if="!loading && parts.length === 0 && !error">
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <svg class="h-12 w-12 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
                <p class="mt-3 text-sm font-medium text-gray-500">No parts found</p>
                <p class="mt-1 text-xs text-gray-400">
                    <template x-if="search || filterCustomer || filterStatus">
                        <span>Try adjusting your search or filters.</span>
                    </template>
                    <template x-if="!search && !filterCustomer && !filterStatus">
                        <span>Add your first part using the button above.</span>
                    </template>
                </p>
            </div>
        </template>

        {{-- Pagination --}}
        <template x-if="lastPage > 1">
            <div class="flex items-center justify-between border-t border-gray-100 px-5 py-3 text-sm">
                <button @click="load(currentPage - 1)" :disabled="currentPage <= 1"
                        class="rounded-lg px-3 py-1.5 text-gray-600 hover:bg-gray-100 disabled:opacity-40 transition-colors">
                    ← Prev
                </button>
                <span class="text-gray-500">
                    Page <strong x-text="currentPage"></strong> of <strong x-text="lastPage"></strong>
                </span>
                <button @click="load(currentPage + 1)" :disabled="currentPage >= lastPage"
                        class="rounded-lg px-3 py-1.5 text-gray-600 hover:bg-gray-100 disabled:opacity-40 transition-colors">
                    Next →
                </button>
            </div>
        </template>
    </div>

    {{-- ════════════════════════════════════════════════════════
         CREATE MODAL
    ════════════════════════════════════════════════════════ --}}
    <div x-show="showCreate" style="display:none"
         class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4"
         @click.self="showCreate = false">
        <div class="w-full max-w-lg rounded-2xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <h3 class="text-base font-semibold text-gray-900">New Part</h3>
                    <button @click="showCreate = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
                </div>
                <form @submit.prevent="submitCreate()">
                    <div class="space-y-4 px-6 py-5">
                        <div class="grid grid-cols-2 gap-4">
                            {{-- Factory selector — only shown for super-admin --}}
                            <template x-if="factories.length > 0">
                                <div class="col-span-2">
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Factory <span class="text-red-500">*</span></label>
                                    <select x-model="form.factory_id" required
                                            @change="loadCustomersForFactory(form.factory_id)"
                                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm
                                                   focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                                        <option value="">— Select factory —</option>
                                        <template x-for="f in factories" :key="f.id">
                                            <option :value="f.id" x-text="f.name"></option>
                                        </template>
                                    </select>
                                </div>
                            </template>
                            <div class="col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Customer <span class="text-red-500">*</span></label>
                                <select x-model="form.customer_id" required
                                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm
                                               focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                                    <option value="">— Select customer —</option>
                                    <template x-for="c in customerDropdown" :key="c.id">
                                        <option :value="c.id" x-text="c.name + ' (' + c.code + ')'"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Part Number <span class="text-red-500">*</span></label>
                                <input x-model="form.part_number" type="text" required maxlength="50"
                                       placeholder="e.g. WLD-001"
                                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm uppercase
                                              focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Unit</label>
                                <select x-model="form.unit"
                                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm
                                               focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                                    <option value="">— Select unit —</option>
                                    <option value="pcs">pcs</option>
                                    <option value="kg">kg</option>
                                    <option value="m">m</option>
                                    <option value="mm">mm</option>
                                    <option value="set">set</option>
                                    <option value="lot">lot</option>
                                </select>
                            </div>
                            <div class="col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Name <span class="text-sm text-red-500">*</span></label>
                                <input x-model="form.name" type="text" required maxlength="150"
                                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm
                                              focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                            </div>
                            <div class="col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Description</label>
                                <textarea x-model="form.description" rows="2" maxlength="500"
                                          class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm
                                                 focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300"></textarea>
                            </div>
                            {{-- Cycle time shown only after routing is defined (edit flow) --}}
                        </div>
                        {{-- Drawings upload (optional at create time) --}}
                        <div class="border-t border-gray-100 pt-4">
                            <label class="block text-xs font-medium text-gray-700 mb-1">
                                Attach Drawing Files
                                <span class="font-normal text-gray-400 ml-1">(PDF, DWG, DXF, image — max 20 MB each)</span>
                            </label>
                            <label class="flex flex-col items-center justify-center gap-1.5 cursor-pointer
                                          rounded-lg border-2 border-dashed border-gray-200 py-4 text-center
                                          hover:border-indigo-300 hover:bg-indigo-50/30 transition-colors">
                                <svg class="h-6 w-6 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                </svg>
                                <span class="text-xs text-gray-400">Click to select files</span>
                                <input type="file" multiple accept=".pdf,.dwg,.dxf,.png,.jpg,.jpeg,.svg,.step,.stp,.iges,.igs"
                                       class="hidden"
                                       @change="createFiles = $event.target.files">
                            </label>
                            <template x-if="createFiles && createFiles.length > 0">
                                <ul class="mt-2 space-y-1">
                                    <template x-for="(f, i) in Array.from(createFiles)" :key="i">
                                        <li class="flex items-center gap-2 text-xs text-gray-600">
                                            <svg class="h-3.5 w-3.5 text-indigo-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                            <span class="truncate" x-text="f.name"></span>
                                            <span class="text-gray-400 shrink-0" x-text="f.size < 1048576 ? Math.round(f.size/1024) + ' KB' : (f.size/1048576).toFixed(1) + ' MB'"></span>
                                        </li>
                                    </template>
                                </ul>
                            </template>
                        </div>

                        <template x-if="formError">
                            <p class="text-xs text-red-600" x-text="formError"></p>
                        </template>
                    </div>
                    <div class="flex justify-end gap-3 border-t border-gray-100 px-6 py-4">
                        <button type="button" @click="showCreate = false"
                                class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" :disabled="saving"
                                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white
                                       hover:bg-indigo-700 disabled:opacity-60 transition-colors">
                            <span x-text="saving ? 'Saving…' : 'Create Part'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

    {{-- ════════════════════════════════════════════════════════
         EDIT MODAL
    ════════════════════════════════════════════════════════ --}}
    <div x-show="showEdit" style="display:none"
         class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4"
         @click.self="showEdit = false">
        <div class="w-full max-w-lg rounded-2xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <h3 class="text-base font-semibold text-gray-900">Edit Part — <span x-text="editTarget?.part_number"></span></h3>
                    <button @click="showEdit = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
                </div>
                <form @submit.prevent="submitEdit()">
                    <div class="space-y-4 px-6 py-5">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Customer <span class="text-red-500">*</span></label>
                                <select x-model="form.customer_id" required
                                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm
                                               focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                                    <option value="">— Select customer —</option>
                                    <template x-for="c in customerDropdown" :key="c.id">
                                        <option :value="c.id" x-text="c.name + ' (' + c.code + ')'"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Part Number <span class="text-red-500">*</span></label>
                                <input x-model="form.part_number" type="text" required maxlength="50"
                                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm uppercase
                                              focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Unit</label>
                                <select x-model="form.unit"
                                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm
                                               focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                                    <option value="">— Select unit —</option>
                                    <option value="pcs">pcs</option>
                                    <option value="kg">kg</option>
                                    <option value="m">m</option>
                                    <option value="mm">mm</option>
                                    <option value="set">set</option>
                                    <option value="lot">lot</option>
                                </select>
                            </div>
                            <div class="col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                                <input x-model="form.name" type="text" required maxlength="150"
                                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm
                                              focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                            </div>
                            <div class="col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Description</label>
                                <textarea x-model="form.description" rows="2" maxlength="500"
                                          class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm
                                                 focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300"></textarea>
                            </div>
                            <div x-show="editTarget?.total_cycle_time > 0" class="col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Std Cycle Time (from Routing)</label>
                                <input x-model="form.cycle_time_std" type="number" step="0.01" min="0" readonly
                                       class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500 cursor-not-allowed">
                                <p class="mt-1 text-xs text-indigo-500">
                                    Auto-calculated from process routing
                                    (<span x-text="(editTarget?.processes_count ?? 0) + ' steps'"></span>)
                                </p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                                <select x-model="form.status"
                                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm
                                               focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                                    <option value="active">Active</option>
                                    <option value="discontinued">Discontinued</option>
                                </select>
                            </div>
                        </div>
                        {{-- Existing drawings --}}
                        <div class="border-t border-gray-100 pt-4">
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-xs font-medium text-gray-700">Drawing Files</label>
                                <span class="text-[10px] text-gray-400" x-text="editDrawings.length + ' file(s)'"></span>
                            </div>

                            {{-- Loading skeleton --}}
                            <template x-if="drawingsLoading">
                                <div class="space-y-1.5">
                                    <template x-for="i in 2" :key="i">
                                        <div class="h-8 animate-pulse rounded bg-gray-100"></div>
                                    </template>
                                </div>
                            </template>

                            {{-- File list --}}
                            <template x-if="!drawingsLoading && editDrawings.length > 0">
                                <ul class="space-y-1.5 mb-3">
                                    <template x-for="d in editDrawings" :key="d.id">
                                        <li class="flex items-center gap-2 rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">
                                            {{-- type icon --}}
                                            <span class="shrink-0">
                                                <template x-if="d.file_type === 'pdf'">
                                                    <svg class="h-4 w-4 text-red-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg>
                                                </template>
                                                <template x-if="d.file_type === 'image'">
                                                    <svg class="h-4 w-4 text-blue-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/></svg>
                                                </template>
                                                <template x-if="d.file_type === 'cad'">
                                                    <svg class="h-4 w-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/></svg>
                                                </template>
                                                <template x-if="d.file_type === 'file'">
                                                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                </template>
                                            </span>
                                            <a :href="d.url" target="_blank"
                                               class="flex-1 min-w-0 text-xs text-indigo-600 hover:text-indigo-800 truncate"
                                               x-text="d.original_name"></a>
                                            <span class="text-[10px] text-gray-400 shrink-0" x-text="d.human_size"></span>
                                            <button @click="deleteDrawing(d)" type="button"
                                                    class="shrink-0 text-gray-300 hover:text-red-500 transition-colors"
                                                    title="Delete">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        </li>
                                    </template>
                                </ul>
                            </template>

                            <template x-if="!drawingsLoading && editDrawings.length === 0">
                                <p class="text-xs text-gray-400 mb-3">No drawings attached yet.</p>
                            </template>

                            {{-- Upload more --}}
                            <label class="flex items-center gap-2 cursor-pointer rounded-lg border border-dashed border-gray-200
                                          px-3 py-2 text-xs text-gray-400 hover:border-indigo-300 hover:text-indigo-500 transition-colors">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Add drawing files…
                                <input type="file" multiple accept=".pdf,.dwg,.dxf,.png,.jpg,.jpeg,.svg,.step,.stp,.iges,.igs"
                                       class="hidden"
                                       @change="uploadEditDrawings($event)">
                            </label>

                            <template x-if="drawingError">
                                <p class="mt-1 text-xs text-red-600" x-text="drawingError"></p>
                            </template>
                        </div>

                        <template x-if="formError">
                            <p class="text-xs text-red-600" x-text="formError"></p>
                        </template>
                    </div>
                    <div class="flex justify-end gap-3 border-t border-gray-100 px-6 py-4">
                        <button type="button" @click="showEdit = false"
                                class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" :disabled="saving"
                                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white
                                       hover:bg-indigo-700 disabled:opacity-60 transition-colors">
                            <span x-text="saving ? 'Saving…' : 'Save Changes'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

    {{-- ════════════════════════════════════════════════════════
         DISCONTINUE CONFIRM
    ════════════════════════════════════════════════════════ --}}
    <div x-show="showDiscontinue" style="display:none"
         class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4"
         @click.self="showDiscontinue = false">
        <div class="w-full max-w-sm rounded-2xl bg-white shadow-xl">
                <div class="px-6 py-5">
                    <h3 class="text-base font-semibold text-gray-900">Discontinue Part?</h3>
                    <p class="mt-2 text-sm text-gray-500">
                        <strong x-text="discontinueTarget?.part_number"></strong> —
                        <span x-text="discontinueTarget?.name"></span>
                        will be marked discontinued. This fails if the part has active production plans.
                    </p>
                    <template x-if="formError">
                        <p class="mt-3 text-xs text-red-600" x-text="formError"></p>
                    </template>
                </div>
                <div class="flex justify-end gap-3 border-t border-gray-100 px-6 py-4">
                    <button @click="showDiscontinue = false"
                            class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 transition-colors">
                        Cancel
                    </button>
                    <button @click="submitDiscontinue()" :disabled="saving"
                            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white
                                   hover:bg-red-700 disabled:opacity-60 transition-colors">
                        <span x-text="saving ? 'Processing…' : 'Discontinue'"></span>
                    </button>
                </div>
            </div>
        </div>

    {{-- ════════════════════════════════════════════════════════
         SCHEDULE MODAL — Part Production Schedule
    ════════════════════════════════════════════════════════ --}}
    <div x-show="showSchedule" style="display:none"
         class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4"
         @click.self="showSchedule = false">
        <div class="relative w-full max-w-3xl bg-white rounded-2xl shadow-2xl flex flex-col"
             style="max-height: calc(100vh - 2rem)" @click.stop>

            {{-- Header --}}
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 flex-shrink-0">
                <div>
                    <h3 class="text-base font-semibold text-gray-900">
                        Production Schedule —
                        <span class="font-mono text-indigo-600" x-text="schedulePart?.part_number"></span>
                    </h3>
                    <p class="text-xs text-gray-400 mt-0.5" x-text="schedulePart?.name"></p>
                </div>
                <button @click="showSchedule = false"
                        class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
            </div>

            {{-- Date range controls --}}
            <div class="flex items-center gap-3 px-6 py-3 bg-gray-50 border-b border-gray-100 flex-shrink-0">
                <label class="text-xs font-medium text-gray-600">From</label>
                <input type="date" x-model="scheduleFrom" @change="reloadSchedule()"
                       class="rounded-lg border border-gray-200 px-2.5 py-1.5 text-sm
                              focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                <label class="text-xs font-medium text-gray-600">To</label>
                <input type="date" x-model="scheduleTo" @change="reloadSchedule()"
                       class="rounded-lg border border-gray-200 px-2.5 py-1.5 text-sm
                              focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                <button @click="resetScheduleRange()"
                        class="ml-auto text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                    Reset to 60 days
                </button>
            </div>

            {{-- Content --}}
            <div class="flex-1 overflow-y-auto min-h-0 px-6 py-4">

                {{-- Loading --}}
                <template x-if="scheduleLoading">
                    <div class="flex items-center justify-center py-16 text-gray-400 text-sm gap-2">
                        <svg class="animate-spin h-5 w-5 text-violet-500" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                        </svg>
                        Loading schedule…
                    </div>
                </template>

                {{-- Empty --}}
                <template x-if="!scheduleLoading && schedulePlans.length === 0">
                    <div class="flex flex-col items-center justify-center py-16 text-center">
                        <svg class="h-12 w-12 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <p class="mt-3 text-sm font-medium text-gray-500">No plans found</p>
                        <p class="mt-1 text-xs text-gray-400">No production plans scheduled for this part in the selected range.</p>
                    </div>
                </template>

                {{-- Schedule grouped by date --}}
                <template x-if="!scheduleLoading && schedulePlans.length > 0">
                    <div class="space-y-4">

                        {{-- Summary bar --}}
                        <div class="flex flex-wrap gap-3">
                            <div class="flex items-center gap-2 rounded-lg bg-violet-50 border border-violet-100 px-4 py-2">
                                <span class="text-xs text-violet-600 font-medium">Total Planned</span>
                                <span class="text-lg font-bold text-violet-700"
                                      x-text="schedulePlans.reduce((s, p) => s + (p.planned_qty || 0), 0).toLocaleString()"></span>
                                <span class="text-xs text-violet-500" x-text="schedulePart?.unit || 'pcs'"></span>
                            </div>
                            <div class="flex items-center gap-2 rounded-lg bg-blue-50 border border-blue-100 px-4 py-2">
                                <span class="text-xs text-blue-600 font-medium">Plans</span>
                                <span class="text-lg font-bold text-blue-700" x-text="schedulePlans.length"></span>
                            </div>
                            <div class="flex items-center gap-2 rounded-lg bg-green-50 border border-green-100 px-4 py-2">
                                <span class="text-xs text-green-600 font-medium">Days</span>
                                <span class="text-lg font-bold text-green-700" x-text="schedulePlansByDate.length"></span>
                            </div>
                        </div>

                        {{-- Per-date blocks --}}
                        <template x-for="group in schedulePlansByDate" :key="group.date">
                            <div class="rounded-xl border border-gray-100 overflow-hidden">

                                {{-- Date header --}}
                                <div class="flex items-center justify-between px-4 py-2.5 bg-gradient-to-r from-slate-700 to-slate-600">
                                    <div class="flex items-center gap-2">
                                        <svg class="h-4 w-4 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        <span class="text-sm font-semibold text-white"
                                              x-text="new Date(group.date + 'T00:00:00').toLocaleDateString('en-IN', { weekday:'short', day:'numeric', month:'short', year:'numeric' })"></span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-slate-300">Total:</span>
                                        <span class="text-sm font-bold text-white"
                                              x-text="group.total.toLocaleString() + ' ' + (schedulePart?.unit || 'pcs')"></span>
                                    </div>
                                </div>

                                {{-- Plans table for this date --}}
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-50 border-b border-gray-100">
                                        <tr class="text-left text-[11px] font-medium uppercase tracking-wide text-gray-400">
                                            <th class="px-4 py-2">Machine</th>
                                            <th class="px-4 py-2">Process Step</th>
                                            <th class="px-4 py-2">Shift</th>
                                            <th class="px-4 py-2 text-right">Planned Qty</th>
                                            <th class="px-4 py-2 text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50">
                                        <template x-for="plan in group.plans" :key="plan.id">
                                            <tr class="hover:bg-gray-50 transition-colors">
                                                <td class="px-4 py-2.5">
                                                    <p class="font-medium text-gray-800 text-xs" x-text="plan.machine?.name || '—'"></p>
                                                    <p class="text-[10px] text-gray-400 font-mono" x-text="plan.machine?.code || ''"></p>
                                                </td>
                                                <td class="px-4 py-2.5">
                                                    <template x-if="plan.part_process?.process_master">
                                                        <div>
                                                            <p class="text-xs font-medium text-indigo-700"
                                                               x-text="plan.part_process.process_master.name"></p>
                                                            <p class="text-[10px] text-gray-400"
                                                               x-text="plan.part_process.process_master.code || ''"></p>
                                                        </div>
                                                    </template>
                                                    <template x-if="!plan.part_process?.process_master">
                                                        <span class="text-xs text-gray-400">—</span>
                                                    </template>
                                                </td>
                                                <td class="px-4 py-2.5 text-xs text-gray-600" x-text="plan.shift?.name || '—'"></td>
                                                <td class="px-4 py-2.5 text-right">
                                                    <span class="font-semibold text-gray-900"
                                                          x-text="(plan.planned_qty || 0).toLocaleString()"></span>
                                                    <span class="text-[10px] text-gray-400 ml-0.5"
                                                          x-text="schedulePart?.unit || 'pcs'"></span>
                                                </td>
                                                <td class="px-4 py-2.5 text-center">
                                                    <span class="rounded-full px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                                                          :class="{
                                                              'bg-gray-100 text-gray-500':   plan.status === 'draft',
                                                              'bg-blue-100 text-blue-700':   plan.status === 'scheduled',
                                                              'bg-amber-100 text-amber-700': plan.status === 'in_progress',
                                                              'bg-green-100 text-green-700': plan.status === 'completed',
                                                              'bg-red-100 text-red-600':     plan.status === 'cancelled',
                                                          }"
                                                          x-text="plan.status?.replace('_', ' ')"></span>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                    {{-- Daily total footer row --}}
                                    <tfoot>
                                        <tr class="bg-violet-50 border-t border-violet-100">
                                            <td colspan="3" class="px-4 py-2 text-xs font-semibold text-violet-600">
                                                Day Total
                                            </td>
                                            <td class="px-4 py-2 text-right text-sm font-bold text-violet-700"
                                                x-text="group.total.toLocaleString() + ' ' + (schedulePart?.unit || 'pcs')"></td>
                                            <td class="px-4 py-2"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            {{-- Footer --}}
            <div class="flex justify-end border-t border-gray-100 px-6 py-4 flex-shrink-0">
                <button @click="showSchedule = false"
                        class="rounded-lg px-5 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 transition-colors">
                    Close
                </button>
            </div>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════
         DRAWINGS PANEL — view & manage drawings for a part
    ════════════════════════════════════════════════════════ --}}
    <div x-show="showDrawings" style="display:none"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
         @click.self="showDrawings = false">
        <div class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl flex flex-col"
             style="max-height: calc(100vh - 4rem)" @click.stop>

            {{-- Header --}}
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 flex-shrink-0">
                <div>
                    <h3 class="text-base font-semibold text-gray-900">Drawing Files</h3>
                    <p class="text-xs text-gray-400 mt-0.5">
                        <span class="font-mono text-indigo-600" x-text="drawingsPart?.part_number"></span>
                        — <span x-text="drawingsPart?.name"></span>
                    </p>
                </div>
                <button @click="showDrawings = false"
                        class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
            </div>

            {{-- File list --}}
            <div class="flex-1 overflow-y-auto px-6 py-4">

                <template x-if="drawingsLoading">
                    <div class="space-y-2">
                        <template x-for="i in 3" :key="i">
                            <div class="h-10 animate-pulse rounded-lg bg-gray-100"></div>
                        </template>
                    </div>
                </template>

                <template x-if="!drawingsLoading && panelDrawings.length === 0">
                    <div class="flex flex-col items-center py-10 text-center">
                        <svg class="h-10 w-10 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                        </svg>
                        <p class="mt-3 text-sm text-gray-400">No drawings attached yet.</p>
                    </div>
                </template>

                <template x-if="!drawingsLoading && panelDrawings.length > 0">
                    <ul class="space-y-2">
                        <template x-for="d in panelDrawings" :key="d.id">
                            <li class="flex items-center gap-3 rounded-xl border border-gray-100 bg-gray-50 px-3 py-2.5">
                                {{-- type icon --}}
                                <span class="shrink-0">
                                    <template x-if="d.file_type === 'pdf'">
                                        <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg>
                                    </template>
                                    <template x-if="d.file_type === 'image'">
                                        <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/></svg>
                                    </template>
                                    <template x-if="d.file_type === 'cad'">
                                        <svg class="h-5 w-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/></svg>
                                    </template>
                                    <template x-if="d.file_type === 'file'">
                                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    </template>
                                </span>
                                <div class="flex-1 min-w-0">
                                    <a :href="d.url" target="_blank"
                                       class="block text-sm text-indigo-600 hover:text-indigo-800 truncate font-medium"
                                       x-text="d.original_name"></a>
                                    <p class="text-[10px] text-gray-400 mt-0.5" x-text="d.human_size"></p>
                                </div>
                                <a :href="d.url" target="_blank" download
                                   class="shrink-0 rounded-lg bg-indigo-50 p-1.5 text-indigo-600 hover:bg-indigo-100 transition-colors"
                                   title="Download">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                </a>
                                <button @click="deletePanelDrawing(d)" type="button"
                                        class="shrink-0 rounded-lg p-1.5 text-gray-300 hover:text-red-500 hover:bg-red-50 transition-colors"
                                        title="Delete">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </li>
                        </template>
                    </ul>
                </template>
            </div>

            {{-- Footer: upload + close --}}
            <div class="flex items-center justify-between border-t border-gray-100 px-6 py-4 flex-shrink-0 gap-3">
                <label class="flex items-center gap-2 cursor-pointer rounded-lg border border-dashed border-gray-200
                              px-3 py-2 text-xs text-gray-400 hover:border-indigo-300 hover:text-indigo-500 transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Upload files
                    <input type="file" multiple accept=".pdf,.dwg,.dxf,.png,.jpg,.jpeg,.svg,.step,.stp,.iges,.igs"
                           class="hidden" @change="uploadPanelDrawings($event)">
                </label>
                <template x-if="drawingError">
                    <p class="text-xs text-red-600 flex-1" x-text="drawingError"></p>
                </template>
                <button @click="showDrawings = false"
                        class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 transition-colors">
                    Close
                </button>
            </div>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script>
function partsPage(apiToken, factoryId, factories) {
    return {
        // ── List state
        parts:            [],
        customerDropdown: [],
        loading:          false,
        error:            null,
        search:           '',
        filterCustomer:   '',
        filterStatus:     '',
        total:            0,
        currentPage:      1,
        lastPage:         1,

        // ── Factory (super-admin)
        factories: factories ?? [],

        // ── Modal state
        showCreate:        false,
        showEdit:          false,
        showDiscontinue:   false,
        showSchedule:      false,
        showDrawings:      false,
        editTarget:        null,
        discontinueTarget: null,
        schedulePart:      null,
        schedulePlans:     [],
        scheduleLoading:   false,
        scheduleFrom:      '',
        scheduleTo:        '',
        form:              {},
        saving:            false,
        formError:         null,

        // ── Drawings state
        createFiles:    null,        // FileList from create modal picker
        editDrawings:   [],          // drawings shown in edit modal
        panelDrawings:  [],          // drawings shown in view panel
        drawingsPart:   null,        // part for the view panel
        drawingsLoading: false,
        drawingError:   null,

        // ── Toast
        toast: { show: false, message: '', type: 'success' },

        get headers() {
            return {
                'Accept':        'application/json',
                'Content-Type':  'application/json',
                'Authorization': `Bearer ${apiToken}`,
                'X-CSRF-TOKEN':  document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            };
        },

        async init() {
            await Promise.all([this.load(1), this.loadCustomers()]);
        },

        // ── Load customer dropdown (optionally scoped to a factory for super-admin)
        async loadCustomers(scopedFactoryId = null) {
            const fid = scopedFactoryId ?? factoryId;
            const url = fid
                ? `/api/v1/customers?per_page=200&factory_id=${fid}`
                : '/api/v1/customers?per_page=200';
            try {
                const res  = await fetch(url, { headers: this.headers });
                const data = await res.json();
                if (res.ok) this.customerDropdown = data.data ?? data;
            } catch {}
        },

        // ── When super-admin changes factory in create form, reload customers
        async loadCustomersForFactory(fid) {
            if (!fid) return;
            await this.loadCustomers(fid);
            this.form.customer_id = '';
        },

        // ── Load parts list
        async load(page = 1) {
            this.loading = true;
            this.error   = null;
            const p = new URLSearchParams({ page, per_page: 25 });
            if (this.search)         p.set('search',      this.search);
            if (this.filterCustomer) p.set('customer_id', this.filterCustomer);
            if (this.filterStatus)   p.set('status',      this.filterStatus);

            try {
                const res  = await fetch(`/api/v1/parts?${p}`, { headers: this.headers });
                const data = await res.json();
                if (!res.ok) { this.error = data.message ?? 'Failed to load parts.'; return; }
                this.parts       = data.data ?? data;
                this.total       = data.total ?? this.parts.length;
                this.currentPage = data.current_page ?? 1;
                this.lastPage    = data.last_page ?? 1;
            } catch {
                this.error = 'Network error loading parts.';
            } finally {
                this.loading = false;
            }
        },

        // ── Create
        openCreate() {
            const defaultFactory = factoryId ?? (this.factories[0]?.id ?? '');
            this.form = {
                factory_id:    defaultFactory,
                customer_id:   '',
                part_number:   '',
                name:          '',
                description:   '',
                unit:          'pcs',
                cycle_time_std: '',
            };
            this.formError  = null;
            this.createFiles = null;
            this.showCreate = true;
        },

        async submitCreate() {
            this.saving    = true;
            this.formError = null;
            const payload  = { ...this.form };
            if (!payload.cycle_time_std) delete payload.cycle_time_std;
            if (!payload.unit)           delete payload.unit;
            if (!payload.description)    delete payload.description;

            try {
                const res  = await fetch('/api/v1/parts', {
                    method: 'POST', headers: this.headers,
                    body: JSON.stringify(payload),
                });
                const data = await res.json();
                if (!res.ok) { this.formError = this.extractError(data); return; }

                // Upload any selected drawings
                if (this.createFiles && this.createFiles.length > 0) {
                    await this._uploadFiles(data.id, this.createFiles);
                }

                this.showCreate = false;
                this.showToast('Part created. Now configure its routing.', 'success');
                this.load(1);
            } catch {
                this.formError = 'Network error. Please try again.';
            } finally {
                this.saving = false;
            }
        },

        // ── Edit
        openEdit(p) {
            this.editTarget = p;
            const cycleTime = (p.total_cycle_time > 0)
                ? p.total_cycle_time
                : (p.cycle_time_std ?? '');
            this.form = {
                customer_id:    p.customer?.id ?? '',
                part_number:    p.part_number,
                name:           p.name,
                description:    p.description ?? '',
                unit:           p.unit || 'pcs',
                cycle_time_std: cycleTime,
                status:         p.status,
            };
            this.formError    = null;
            this.drawingError = null;
            this.editDrawings = [];
            this.showEdit     = true;
            this._loadEditDrawings(p.id);
        },

        async submitEdit() {
            this.saving    = true;
            this.formError = null;
            const payload  = { ...this.form };
            if (payload.cycle_time_std === '') delete payload.cycle_time_std;

            try {
                const res  = await fetch(`/api/v1/parts/${this.editTarget.id}`, {
                    method: 'PUT', headers: this.headers,
                    body: JSON.stringify(payload),
                });
                const data = await res.json();
                if (!res.ok) { this.formError = this.extractError(data); return; }
                this.showEdit = false;
                this.showToast('Part updated successfully.', 'success');
                this.load(this.currentPage);
            } catch {
                this.formError = 'Network error. Please try again.';
            } finally {
                this.saving = false;
            }
        },

        // ── Discontinue
        confirmDiscontinue(p) {
            this.discontinueTarget = p;
            this.formError         = null;
            this.showDiscontinue   = true;
        },

        async submitDiscontinue() {
            this.saving    = true;
            this.formError = null;
            try {
                const res  = await fetch(`/api/v1/parts/${this.discontinueTarget.id}`, {
                    method: 'DELETE', headers: this.headers,
                });
                const data = await res.json();
                if (!res.ok) { this.formError = data.message ?? 'Failed to discontinue.'; return; }
                this.showDiscontinue = false;
                this.showToast('Part discontinued.', 'success');
                this.load(this.currentPage);
            } catch {
                this.formError = 'Network error. Please try again.';
            } finally {
                this.saving = false;
            }
        },

        // ── Schedule
        async openSchedule(p) {
            this.schedulePart    = p;
            this.schedulePlans   = [];
            this.scheduleLoading = true;
            this.showSchedule    = true;

            const today  = new Date().toISOString().substring(0, 10);
            const future = new Date(Date.now() + 60 * 86400000).toISOString().substring(0, 10);
            this.scheduleFrom = today;
            this.scheduleTo   = future;

            await this._fetchSchedule(p.id, today, future);
        },

        async reloadSchedule() {
            if (!this.schedulePart || !this.scheduleFrom || !this.scheduleTo) return;
            this.scheduleLoading = true;
            await this._fetchSchedule(this.schedulePart.id, this.scheduleFrom, this.scheduleTo);
        },

        resetScheduleRange() {
            const today  = new Date().toISOString().substring(0, 10);
            const future = new Date(Date.now() + 60 * 86400000).toISOString().substring(0, 10);
            this.scheduleFrom = today;
            this.scheduleTo   = future;
            this.reloadSchedule();
        },

        async _fetchSchedule(partId, from, to) {
            const params = new URLSearchParams({ part_id: partId, from_date: from, to_date: to, per_page: 500 });
            try {
                const res  = await fetch(`/api/v1/production-plans?${params}`, { headers: this.headers });
                const data = await res.json();
                if (res.ok) this.schedulePlans = data.data ?? data;
                else this.schedulePlans = [];
            } catch {
                this.schedulePlans = [];
            } finally {
                this.scheduleLoading = false;
            }
        },

        get schedulePlansByDate() {
            const byDate = {};
            for (const plan of this.schedulePlans) {
                const date = plan.planned_date ? String(plan.planned_date).substring(0, 10) : 'Unknown';
                if (!byDate[date]) byDate[date] = { total: 0, plans: [] };
                byDate[date].plans.push(plan);
                byDate[date].total += plan.planned_qty || 0;
            }
            return Object.entries(byDate)
                .sort(([a], [b]) => a.localeCompare(b))
                .map(([date, data]) => ({ date, ...data }));
        },

        // ── Drawings ─────────────────────────────────────────────

        async _loadEditDrawings(partId) {
            this.drawingsLoading = true;
            try {
                const res  = await fetch(`/api/v1/parts/${partId}/drawings`, { headers: this.headers });
                const data = await res.json();
                this.editDrawings = res.ok ? data : [];
            } catch { this.editDrawings = []; }
            finally  { this.drawingsLoading = false; }
        },

        async _uploadFiles(partId, fileList) {
            const fd = new FormData();
            Array.from(fileList).forEach(f => fd.append('files[]', f));
            const headers = { Authorization: `Bearer ${apiToken}`, 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' };
            const res  = await fetch(`/api/v1/parts/${partId}/drawings`, { method: 'POST', headers, body: fd });
            return res.json();
        },

        async uploadEditDrawings(event) {
            if (!event.target.files.length) return;
            this.drawingError = null;
            try {
                const uploaded = await this._uploadFiles(this.editTarget.id, event.target.files);
                if (Array.isArray(uploaded)) {
                    this.editDrawings = [...this.editDrawings, ...uploaded];
                    // sync count in list
                    const p = this.parts.find(x => x.id === this.editTarget.id);
                    if (p) p.drawings_count = (p.drawings_count || 0) + uploaded.length;
                } else {
                    this.drawingError = uploaded.message ?? 'Upload failed.';
                }
            } catch { this.drawingError = 'Upload error.'; }
            event.target.value = '';
        },

        async deleteDrawing(drawing) {
            if (!confirm(`Delete "${drawing.original_name}"?`)) return;
            try {
                await fetch(`/api/v1/parts/${this.editTarget.id}/drawings/${drawing.id}`, { method: 'DELETE', headers: this.headers });
                this.editDrawings = this.editDrawings.filter(d => d.id !== drawing.id);
                const p = this.parts.find(x => x.id === this.editTarget.id);
                if (p) p.drawings_count = Math.max(0, (p.drawings_count || 1) - 1);
            } catch { this.drawingError = 'Delete failed.'; }
        },

        // ── View Drawings panel (from listing icon)
        async openDrawings(p) {
            this.drawingsPart   = p;
            this.panelDrawings  = [];
            this.drawingError   = null;
            this.showDrawings   = true;
            this.drawingsLoading = true;
            try {
                const res  = await fetch(`/api/v1/parts/${p.id}/drawings`, { headers: this.headers });
                const data = await res.json();
                this.panelDrawings = res.ok ? data : [];
            } catch { this.panelDrawings = []; }
            finally  { this.drawingsLoading = false; }
        },

        async uploadPanelDrawings(event) {
            if (!event.target.files.length) return;
            this.drawingError = null;
            try {
                const uploaded = await this._uploadFiles(this.drawingsPart.id, event.target.files);
                if (Array.isArray(uploaded)) {
                    this.panelDrawings = [...this.panelDrawings, ...uploaded];
                    const p = this.parts.find(x => x.id === this.drawingsPart.id);
                    if (p) p.drawings_count = (p.drawings_count || 0) + uploaded.length;
                } else {
                    this.drawingError = uploaded.message ?? 'Upload failed.';
                }
            } catch { this.drawingError = 'Upload error.'; }
            event.target.value = '';
        },

        async deletePanelDrawing(drawing) {
            if (!confirm(`Delete "${drawing.original_name}"?`)) return;
            try {
                await fetch(`/api/v1/parts/${this.drawingsPart.id}/drawings/${drawing.id}`, { method: 'DELETE', headers: this.headers });
                this.panelDrawings = this.panelDrawings.filter(d => d.id !== drawing.id);
                const p = this.parts.find(x => x.id === this.drawingsPart.id);
                if (p) p.drawings_count = Math.max(0, (p.drawings_count || 1) - 1);
            } catch { this.drawingError = 'Delete failed.'; }
        },

        // ── Helpers
        extractError(data) {
            if (data.message) return data.message;
            if (data.errors) {
                const first = Object.values(data.errors)[0];
                return Array.isArray(first) ? first[0] : first;
            }
            return 'An error occurred.';
        },

        showToast(message, type = 'success') {
            this.toast = { show: true, message, type };
            setTimeout(() => this.toast.show = false, 3500);
        },
    };
}
</script>
@endpush

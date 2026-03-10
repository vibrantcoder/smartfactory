@extends('admin.layouts.app')

@section('title', 'Work Orders')

@section('content')

@php
    $isSuperAdmin  = $factoryId === null;
    $factoriesJson = $factories->isNotEmpty() ? $factories->toJson() : '[]';
    $customersJson = $customers->toJson();
    $partsJson     = $parts->toJson();
@endphp

<div x-data="workOrderManager(
    {{ $apiToken ? json_encode($apiToken) : 'null' }},
    {{ $isSuperAdmin ? 'true' : 'false' }},
    {{ $factoryId ?? 'null' }},
    {{ $factoriesJson }},
    {{ $customersJson }},
    {{ $partsJson }}
)" x-init="init()">

    {{-- Flash --}}
    <template x-if="flash.message">
        <div class="mb-4 rounded-lg border px-4 py-3 text-sm"
             :class="flash.type === 'success' ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800'">
            <span x-text="flash.message"></span>
        </div>
    </template>

    {{-- Header + Filters --}}
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200">

        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
            <div>
                <h2 class="text-sm font-semibold text-gray-700">Work Orders</h2>
                <p class="text-xs text-gray-400 mt-0.5"><span x-text="pagination.total ?? '…'"></span> orders</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                {{-- Factory selector (super-admin) --}}
                @if($isSuperAdmin)
                <select x-model="currentFactoryId" @change="loadOrders(1)"
                        class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs text-gray-700 focus:border-indigo-400 focus:outline-none">
                    <option value="">All Factories</option>
                    <template x-for="f in factories" :key="f.id">
                        <option :value="f.id" x-text="f.name"></option>
                    </template>
                </select>
                @endif
                {{-- Status filter --}}
                <select x-model="filterStatus" @change="loadOrders(1)"
                        class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs text-gray-700 focus:border-indigo-400 focus:outline-none">
                    <option value="">All Statuses</option>
                    <option value="draft">Draft</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="released">Released</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                {{-- Priority filter --}}
                <select x-model="filterPriority" @change="loadOrders(1)"
                        class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs text-gray-700 focus:border-indigo-400 focus:outline-none">
                    <option value="">All Priorities</option>
                    <option value="urgent">Urgent</option>
                    <option value="high">High</option>
                    <option value="medium">Medium</option>
                    <option value="low">Low</option>
                </select>
                {{-- Search --}}
                <input x-model.debounce.400ms="searchTerm" @input="loadOrders(1)"
                       type="text" placeholder="WO #, customer, part…"
                       class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs text-gray-700 focus:border-indigo-400 focus:outline-none w-44">
                {{-- Add --}}
                <button @click="openCreate()"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-indigo-700 transition-colors shadow-sm">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    New Work Order
                </button>
            </div>
        </div>

        {{-- Skeleton --}}
        <template x-if="loading && orders.length === 0">
            <div class="space-y-px">
                <template x-for="i in 5" :key="i">
                    <div class="flex items-center gap-4 px-5 py-4 border-b border-gray-50">
                        <div class="h-3.5 w-32 animate-pulse rounded bg-gray-100"></div>
                        <div class="flex-1 h-3 w-48 animate-pulse rounded bg-gray-100"></div>
                    </div>
                </template>
            </div>
        </template>

        {{-- Table --}}
        <template x-if="orders.length > 0">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-gray-100 bg-gray-50">
                        <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                            <th class="px-5 py-3">WO #</th>
                            <th class="px-5 py-3">Customer / Part</th>
                            <th class="px-5 py-3 text-right">Order Qty</th>
                            <th class="px-5 py-3 text-right">+ Excess</th>
                            <th class="px-5 py-3 text-right">Total Plan</th>
                            <th class="px-5 py-3">Delivery</th>
                            <th class="px-5 py-3">Priority</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <template x-for="wo in orders" :key="wo.id">
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-3">
                                    <span class="font-mono text-xs font-semibold text-indigo-700" x-text="wo.wo_number"></span>
                                </td>
                                <td class="px-5 py-3">
                                    <p class="font-medium text-gray-900 text-xs" x-text="wo.customer_name"></p>
                                    <p class="text-xs text-gray-400" x-text="wo.part_number + ' — ' + wo.part_name"></p>
                                </td>
                                <td class="px-5 py-3 text-right text-xs font-medium text-gray-700" x-text="wo.order_qty.toLocaleString()"></td>
                                <td class="px-5 py-3 text-right text-xs text-amber-600" x-text="'+' + wo.excess_qty.toLocaleString()"></td>
                                <td class="px-5 py-3 text-right text-xs font-semibold text-gray-900" x-text="wo.total_planned_qty.toLocaleString()"></td>
                                <td class="px-5 py-3">
                                    <span class="text-xs"
                                          :class="isOverdue(wo) ? 'text-red-600 font-semibold' : 'text-gray-600'"
                                          x-text="formatDate(wo.expected_delivery_date)"></span>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                                          :class="priorityClass(wo.priority)"
                                          x-text="wo.priority.charAt(0).toUpperCase() + wo.priority.slice(1)"></span>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                                          :class="statusClass(wo.status)"
                                          x-text="statusLabel(wo.status)"></span>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-1.5">
                                        <button @click="openEdit(wo)"
                                                :disabled="wo.status === 'completed' || wo.status === 'cancelled'"
                                                class="rounded-lg bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-200 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                                            Edit
                                        </button>
                                        <template x-if="wo.status === 'draft'">
                                            <button @click="quickStatus(wo, 'confirmed')"
                                                    class="rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 hover:bg-blue-100 transition-colors">
                                                Confirm
                                            </button>
                                        </template>
                                        <template x-if="wo.status === 'confirmed'">
                                            <button @click="quickStatus(wo, 'released')"
                                                    class="rounded-lg bg-purple-50 px-2.5 py-1 text-xs font-medium text-purple-700 hover:bg-purple-100 transition-colors">
                                                Release
                                            </button>
                                        </template>
                                        <template x-if="wo.status === 'draft'">
                                            <button @click="deleteWo(wo)"
                                                    class="rounded-lg bg-red-50 px-2.5 py-1 text-xs font-medium text-red-600 hover:bg-red-100 transition-colors">
                                                Delete
                                            </button>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </template>

        <template x-if="!loading && orders.length === 0">
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <svg class="h-10 w-10 text-gray-300 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <p class="text-sm text-gray-400">No work orders found</p>
                <button @click="openCreate()" class="mt-3 text-xs text-indigo-600 hover:underline">Create the first one</button>
            </div>
        </template>

        {{-- Pagination --}}
        <template x-if="pagination.last_page > 1">
            <div class="flex items-center justify-between border-t border-gray-100 px-5 py-3 text-sm">
                <button @click="changePage(pagination.current_page - 1)"
                        :disabled="pagination.current_page <= 1"
                        class="rounded-lg px-3 py-1.5 text-gray-600 hover:bg-gray-100 disabled:opacity-40 transition-colors">← Prev</button>
                <span class="text-xs text-gray-500">
                    Page <strong x-text="pagination.current_page"></strong> of <strong x-text="pagination.last_page"></strong>
                </span>
                <button @click="changePage(pagination.current_page + 1)"
                        :disabled="pagination.current_page >= pagination.last_page"
                        class="rounded-lg px-3 py-1.5 text-gray-600 hover:bg-gray-100 disabled:opacity-40 transition-colors">Next →</button>
            </div>
        </template>

    </div>{{-- end card --}}

    {{-- ── CREATE / EDIT MODAL ────────────────────────────────── --}}
    <template x-if="modal.open">
        <div class="fixed inset-0 z-50 flex items-start justify-center bg-black/50 p-4 pt-10"
             @click.self="modal.open = false"
             @keydown.escape.window="modal.open = false">
            <div class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl">

                {{-- Header --}}
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900"
                            x-text="modal.mode === 'create' ? 'New Work Order' : 'Edit Work Order — ' + modal.wo?.wo_number"></h3>
                        <p class="text-xs text-gray-400 mt-0.5">ISO 9001 · §8.1 Operational Planning</p>
                    </div>
                    <button @click="modal.open = false" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 transition-colors">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Body --}}
                <div class="px-6 py-5 space-y-5">

                    {{-- Row 1: Customer + Part --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Customer *</label>
                            <select x-model="modal.form.customer_id" @change="onCustomerChange()"
                                    :disabled="!modal.form.factory_id && !currentFactoryId"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400 disabled:bg-gray-50 disabled:text-gray-400"
                                    :class="modal.errors.customer_id ? 'border-red-400' : ''">
                                <option value="" x-text="(!modal.form.factory_id && !currentFactoryId) ? '— Select a factory first —' : '— Select customer —'"></option>
                                <template x-for="c in filteredCustomers" :key="c.id">
                                    <option :value="c.id" x-text="c.name + ' (' + c.code + ')'"></option>
                                </template>
                            </select>
                            <p x-show="modal.errors.customer_id" class="mt-1 text-xs text-red-600"
                               x-text="(modal.errors.customer_id||[])[0]"></p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Part *</label>
                            <select x-model="modal.form.part_id" @change="onPartChange()"
                                    :disabled="!modal.form.customer_id"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400 disabled:bg-gray-50 disabled:text-gray-400"
                                    :class="modal.errors.part_id ? 'border-red-400' : ''">
                                <option value="">— Select part —</option>
                                <template x-for="p in partsForCustomer" :key="p.id">
                                    <option :value="p.id" x-text="p.part_number + ' — ' + p.name"></option>
                                </template>
                            </select>
                            <p x-show="modal.errors.part_id" class="mt-1 text-xs text-red-600"
                               x-text="(modal.errors.part_id||[])[0]"></p>
                            <template x-if="modal.selectedPart">
                                <p class="mt-1 text-xs text-gray-400">
                                    Cycle time: <span class="font-medium text-gray-600" x-text="modal.selectedPart.cycle_time_std + 's'"></span>
                                    · Unit: <span class="font-medium text-gray-600" x-text="modal.selectedPart.unit"></span>
                                </p>
                            </template>
                        </div>
                    </div>

                    {{-- Row 2: Quantities --}}
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Order Qty *</label>
                            <input x-model.number="modal.form.order_qty" type="number" min="1" placeholder="e.g. 500"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                   :class="modal.errors.order_qty ? 'border-red-400' : ''">
                            <p x-show="modal.errors.order_qty" class="mt-1 text-xs text-red-600"
                               x-text="(modal.errors.order_qty||[])[0]"></p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">
                                Excess / Buffer Qty
                                <span class="ml-1 font-normal text-gray-400">(scrap buffer)</span>
                            </label>
                            <input x-model.number="modal.form.excess_qty" type="number" min="0" placeholder="0"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Total Planned Qty</label>
                            <div class="flex items-center h-9 rounded-lg bg-indigo-50 border border-indigo-100 px-3">
                                <span class="text-sm font-semibold text-indigo-700"
                                      x-text="((modal.form.order_qty || 0) + (modal.form.excess_qty || 0)).toLocaleString()"></span>
                                <span class="ml-1.5 text-xs text-indigo-400" x-text="modal.selectedPart?.unit || 'pcs'"></span>
                            </div>
                        </div>
                    </div>

                    {{-- Row 3: Dates --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Expected Delivery Date *</label>
                            <input x-model="modal.form.expected_delivery_date" type="date"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                   :class="modal.errors.expected_delivery_date ? 'border-red-400' : ''">
                            <p x-show="modal.errors.expected_delivery_date" class="mt-1 text-xs text-red-600"
                               x-text="(modal.errors.expected_delivery_date||[])[0]"></p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Planned Start Date</label>
                            <input x-model="modal.form.planned_start_date" type="date"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                        </div>
                    </div>

                    {{-- Row 4: Priority + Status + Factory --}}
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Priority *</label>
                            <select x-model="modal.form.priority"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">🔴 Urgent</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                            <select x-model="modal.form.status"
                                    :disabled="modal.mode === 'create'"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400 disabled:bg-gray-50">
                                <option value="draft">Draft</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="released">Released</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        @if($isSuperAdmin)
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Factory *</label>
                            <select x-model="modal.form.factory_id" @change="onFactoryChange()"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                                <option value="">— Select factory —</option>
                                <template x-for="f in factories" :key="f.id">
                                    <option :value="f.id" x-text="f.name"></option>
                                </template>
                            </select>
                        </div>
                        @endif
                    </div>

                    {{-- Notes --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Notes / Remarks</label>
                        <textarea x-model="modal.form.notes" rows="2" placeholder="Special instructions, quality remarks…"
                                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400 resize-none"></textarea>
                    </div>

                    {{-- Estimated production time hint --}}
                    <template x-if="modal.selectedPart && modal.form.order_qty > 0">
                        <div class="rounded-lg bg-blue-50 border border-blue-100 px-4 py-3">
                            <p class="text-xs font-semibold text-blue-700 mb-1">Production Estimate</p>
                            <div class="grid grid-cols-3 gap-3 text-xs text-blue-600">
                                <div>
                                    <span class="block text-blue-400">Order qty</span>
                                    <span class="font-semibold" x-text="(modal.form.order_qty||0).toLocaleString() + ' ' + (modal.selectedPart.unit||'pcs')"></span>
                                </div>
                                <div>
                                    <span class="block text-blue-400">Total planned</span>
                                    <span class="font-semibold" x-text="((modal.form.order_qty||0) + (modal.form.excess_qty||0)).toLocaleString() + ' ' + (modal.selectedPart.unit||'pcs')"></span>
                                </div>
                                <div>
                                    <span class="block text-blue-400">Est. machine time</span>
                                    <span class="font-semibold" x-text="estimatedTime"></span>
                                </div>
                            </div>
                        </div>
                    </template>

                    {{-- Error --}}
                    <template x-if="modal.error">
                        <p class="rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-xs text-red-700" x-text="modal.error"></p>
                    </template>

                </div>

                {{-- Footer --}}
                <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4">
                    <button @click="modal.open = false"
                            class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 transition-colors">Cancel</button>
                    <button @click="submitModal()"
                            :disabled="modal.saving"
                            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 px-5 py-2 text-sm font-semibold text-white shadow-sm transition-colors">
                        <svg x-show="modal.saving" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span x-text="modal.saving ? 'Saving…' : (modal.mode === 'create' ? 'Create Work Order' : 'Save Changes')"></span>
                    </button>
                </div>

            </div>
        </div>
    </template>

</div>{{-- end x-data --}}

@endsection

@push('scripts')
<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>
<script>
function workOrderManager(apiToken, isSuperAdmin, factoryId, factories, allCustomers, allParts) {
    return {
        orders:     [],
        pagination: {},
        loading:    false,
        flash:      { type: '', message: '' },
        factories:  factories || [],
        allCustomers: allCustomers || [],
        allParts:   allParts || [],

        // Filters
        currentFactoryId: factoryId ?? '',
        filterStatus:  '',
        filterPriority:'',
        searchTerm:    '',

        modal: {
            open: false, mode: 'create', saving: false, wo: null, error: null, errors: {},
            selectedPart: null,
            form: {
                factory_id: factoryId ?? '',
                customer_id: '', part_id: '',
                order_qty: '', excess_qty: 0,
                expected_delivery_date: '', planned_start_date: '',
                priority: 'medium', status: 'draft',
                notes: '',
            },
        },

        // ── Init ───────────────────────────────────────────────
        init() { this.loadOrders(1); },

        // ── HTTP headers ───────────────────────────────────────
        get headers() {
            return {
                'Accept':        'application/json',
                'Content-Type':  'application/json',
                'X-CSRF-TOKEN':  document.querySelector('meta[name="csrf-token"]').content,
                'Authorization': apiToken ? `Bearer ${apiToken}` : '',
            };
        },

        // ── Derived data ───────────────────────────────────────
        get filteredCustomers() {
            const fid = this.modal.form.factory_id || this.currentFactoryId;
            // Super-admin must pick a factory first; factory-scoped users see their own
            if (!fid) return [];
            return this.allCustomers.filter(c => c.factory_id == fid);
        },

        get partsForCustomer() {
            if (!this.modal.form.customer_id) return [];
            return this.allParts.filter(p => p.customer_id == this.modal.form.customer_id);
        },

        get estimatedTime() {
            const part = this.modal.selectedPart;
            if (!part || !part.cycle_time_std) return '—';
            const totalQty  = (this.modal.form.order_qty || 0) + (this.modal.form.excess_qty || 0);
            const totalSec  = totalQty * part.cycle_time_std;
            if (totalSec < 60)   return totalSec + 's';
            if (totalSec < 3600) return Math.round(totalSec / 60) + ' min';
            const h = Math.floor(totalSec / 3600);
            const m = Math.round((totalSec % 3600) / 60);
            return h + 'h ' + m + 'm';
        },

        // ── Load orders ────────────────────────────────────────
        async loadOrders(page = 1) {
            this.loading = true;
            try {
                const params = new URLSearchParams({ per_page: 25, page });
                if (this.currentFactoryId) params.set('factory_id', this.currentFactoryId);
                if (this.filterStatus)     params.set('status', this.filterStatus);
                if (this.filterPriority)   params.set('priority', this.filterPriority);
                if (this.searchTerm)       params.set('search', this.searchTerm);

                const res  = await fetch(`/api/v1/work-orders?${params}`, { headers: this.headers });
                const data = await res.json();
                this.orders     = data.data ?? [];
                this.pagination = {
                    current_page: data.current_page,
                    last_page:    data.last_page,
                    total:        data.total,
                };
            } catch (e) {
                this.setFlash('error', 'Failed to load work orders.');
            } finally {
                this.loading = false;
            }
        },

        changePage(page) {
            if (page < 1 || page > this.pagination.last_page) return;
            this.loadOrders(page);
        },

        // ── Open create ────────────────────────────────────────
        openCreate() {
            this.modal = {
                open: true, mode: 'create', saving: false, wo: null, error: null, errors: {},
                selectedPart: null,
                form: {
                    factory_id: factoryId ?? (this.currentFactoryId || ''),
                    customer_id: '', part_id: '',
                    order_qty: '', excess_qty: 0,
                    expected_delivery_date: '', planned_start_date: '',
                    priority: 'medium', status: 'draft',
                    notes: '',
                },
            };
        },

        // ── Open edit ──────────────────────────────────────────
        openEdit(wo) {
            const selectedPart = this.allParts.find(p => p.id == wo.part_id) || null;
            this.modal = {
                open: true, mode: 'edit', saving: false, wo, error: null, errors: {},
                selectedPart,
                form: {
                    factory_id:              wo.factory_id,
                    customer_id:             wo.customer_id,
                    part_id:                 wo.part_id,
                    order_qty:               wo.order_qty,
                    excess_qty:              wo.excess_qty,
                    expected_delivery_date:  wo.expected_delivery_date,
                    planned_start_date:      wo.planned_start_date || '',
                    priority:                wo.priority,
                    status:                  wo.status,
                    notes:                   wo.notes || '',
                },
            };
        },

        // ── Event handlers ─────────────────────────────────────
        onCustomerChange() {
            this.modal.form.part_id    = '';
            this.modal.selectedPart    = null;
        },

        onPartChange() {
            this.modal.selectedPart = this.allParts.find(p => p.id == this.modal.form.part_id) || null;
        },

        onFactoryChange() {
            this.modal.form.customer_id = '';
            this.modal.form.part_id     = '';
            this.modal.selectedPart     = null;
        },

        // ── Submit create / edit ───────────────────────────────
        async submitModal() {
            this.modal.saving = true;
            this.modal.error  = null;
            this.modal.errors = {};
            try {
                const isCreate = this.modal.mode === 'create';
                const url    = isCreate ? '/api/v1/work-orders' : `/api/v1/work-orders/${this.modal.wo.id}`;
                const method = isCreate ? 'POST' : 'PUT';

                const res  = await fetch(url, {
                    method,
                    headers: this.headers,
                    body:    JSON.stringify(this.modal.form),
                });
                const data = await res.json();

                if (res.status === 422) {
                    this.modal.errors = data.errors ?? {};
                } else if (!res.ok) {
                    this.modal.error = data.message ?? 'Failed to save work order.';
                } else {
                    this.setFlash('success', data.message);
                    this.modal.open = false;
                    if (isCreate) {
                        this.loadOrders(1);
                    } else {
                        const idx = this.orders.findIndex(o => o.id === this.modal.wo.id);
                        if (idx !== -1) this.orders.splice(idx, 1, data.data);
                    }
                }
            } catch (e) {
                this.modal.error = 'Network error. Please retry.';
            } finally {
                this.modal.saving = false;
            }
        },

        // ── Quick status change (Confirm / Release buttons) ────
        async quickStatus(wo, newStatus) {
            try {
                const res  = await fetch(`/api/v1/work-orders/${wo.id}`, {
                    method:  'PUT',
                    headers: this.headers,
                    body:    JSON.stringify({ status: newStatus }),
                });
                const data = await res.json();
                if (!res.ok) {
                    this.setFlash('error', data.message ?? 'Failed to update status.');
                } else {
                    this.setFlash('success', data.message);
                    const idx = this.orders.findIndex(o => o.id === wo.id);
                    if (idx !== -1) this.orders.splice(idx, 1, data.data);
                }
            } catch (e) {
                this.setFlash('error', 'Network error.');
            }
        },

        // ── Delete (draft only) ────────────────────────────────
        async deleteWo(wo) {
            if (!confirm(`Delete Work Order ${wo.wo_number}? This cannot be undone.`)) return;
            try {
                const res  = await fetch(`/api/v1/work-orders/${wo.id}`, {
                    method: 'DELETE', headers: this.headers,
                });
                const data = await res.json();
                if (!res.ok) {
                    this.setFlash('error', data.message ?? 'Failed to delete.');
                } else {
                    this.setFlash('success', data.message);
                    this.orders = this.orders.filter(o => o.id !== wo.id);
                    this.pagination.total = (this.pagination.total || 1) - 1;
                }
            } catch (e) {
                this.setFlash('error', 'Network error.');
            }
        },

        // ── Helpers ────────────────────────────────────────────
        isOverdue(wo) {
            if (wo.status === 'completed' || wo.status === 'cancelled') return false;
            return wo.expected_delivery_date < new Date().toISOString().slice(0, 10);
        },

        formatDate(d) {
            if (!d) return '—';
            const date = new Date(d + 'T00:00:00');
            return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        },

        priorityClass(p) {
            return {
                urgent: 'bg-red-100 text-red-700',
                high:   'bg-orange-100 text-orange-700',
                medium: 'bg-yellow-100 text-yellow-700',
                low:    'bg-gray-100 text-gray-600',
            }[p] || 'bg-gray-100 text-gray-600';
        },

        statusClass(s) {
            return {
                draft:       'bg-gray-100 text-gray-600',
                confirmed:   'bg-blue-100 text-blue-700',
                released:    'bg-purple-100 text-purple-700',
                in_progress: 'bg-amber-100 text-amber-700',
                completed:   'bg-green-100 text-green-700',
                cancelled:   'bg-red-100 text-red-600',
            }[s] || 'bg-gray-100 text-gray-600';
        },

        statusLabel(s) {
            return {
                draft: 'Draft', confirmed: 'Confirmed', released: 'Released',
                in_progress: 'In Progress', completed: 'Completed', cancelled: 'Cancelled',
            }[s] || s;
        },

        setFlash(type, message) {
            this.flash = { type, message };
            setTimeout(() => this.flash = { type: '', message: '' }, 5000);
        },
    };
}
</script>
@endpush

@extends('admin.layouts.app')

@section('title', 'Work Orders')

@section('content')

@php
    $hasMultiFactory = $factories->isNotEmpty();
    $isSuperAdmin    = $factoryId === null;
    $factoriesJson   = $hasMultiFactory ? $factories->toJson() : '[]';
    $customersJson   = $customers->toJson();
    $partsJson       = $parts->toJson();
    $machinesJson    = $machines->toJson();
    $shiftsJson      = $shifts->toJson();
@endphp

<div x-data="workOrderManager(
    {{ $apiToken ? json_encode($apiToken) : 'null' }},
    {{ $isSuperAdmin ? 'true' : 'false' }},
    {{ $factoryId ?? 'null' }},
    {{ $factoriesJson }},
    {{ $customersJson }},
    {{ $partsJson }},
    {{ $machinesJson }},
    {{ $shiftsJson }},
    {{ json_encode($weekOffDays) }},
    {{ json_encode($holidays) }}
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
                {{-- Factory selector (only when multiple factories exist) --}}
                @if($hasMultiFactory)
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
                                        <template x-if="['confirmed','released','in_progress'].includes(wo.status)">
                                            <button @click="openSchedule(wo)"
                                                    class="rounded-lg bg-teal-50 px-2.5 py-1 text-xs font-medium text-teal-700 hover:bg-teal-100 transition-colors">
                                                Schedule
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
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             @keydown.escape.window="modal.open = false">
            <div class="absolute inset-0 bg-black/40" @click="modal.open = false"></div>
            <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl overflow-y-auto" style="max-height:92vh">

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
                                    :disabled="isSuperAdmin && !modal.form.factory_id && !currentFactoryId"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400 disabled:bg-gray-50 disabled:text-gray-400"
                                    :class="modal.errors.customer_id ? 'border-red-400' : ''">
                                <option value="">— Select customer —</option>
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
                                <!-- <span class="ml-1 font-normal text-gray-400">(scrap buffer)</span> -->
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
                        @if($hasMultiFactory)
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

    {{-- ══════════════════════════════════════════════════════════
         SCHEDULE PRODUCTION MODAL
    ══════════════════════════════════════════════════════════ --}}
    <template x-if="schedModal.open">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             @keydown.escape.window="schedModal.open = false">
            <div class="absolute inset-0 bg-black/40" @click="schedModal.open = false"></div>
            <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl modal-in overflow-y-auto" style="max-height:92vh">

                {{-- Header --}}
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <div>
                        <h3 class="text-base font-semibold text-gray-800">Schedule Production</h3>
                        <p class="text-xs text-gray-400 mt-0.5" x-text="schedModal.wo ? schedModal.wo.wo_number + ' — ' + schedModal.wo.part_name : ''"></p>
                    </div>
                    <button @click="schedModal.open = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Body --}}
                <div class="px-6 py-5 space-y-4">

                    {{-- WO qty overview --}}
                    <div class="rounded-lg bg-teal-50 border border-teal-100 px-4 py-3 text-xs text-teal-800 space-y-1">
                        <div class="flex justify-between">
                            <span class="text-teal-600">Part</span>
                            <span class="font-semibold" x-text="schedModal.wo?.part_name + ' (' + schedModal.wo?.part_number + ')'"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-teal-600">Total Order Qty</span>
                            <span class="font-semibold" x-text="schedModal.wo?.total_planned_qty?.toLocaleString()"></span>
                        </div>
                        <template x-if="!schedModal.qtyLoading && schedModal.qtySummary">
                            <div class="pt-1 border-t border-teal-200 space-y-0.5">
                                <div class="flex justify-between">
                                    <span class="text-teal-600">Already Scheduled</span>
                                    <span class="font-semibold text-amber-700" x-text="schedModal.qtySummary.total_scheduled.toLocaleString()"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-teal-600" x-text="schedModal.form.part_process_id ? 'Remaining (process)' : 'Remaining'"></span>
                                    <span class="font-semibold" :class="schedActiveRemaining > 0 ? 'text-green-700' : 'text-gray-400'"
                                          x-text="schedActiveRemaining !== null ? schedActiveRemaining.toLocaleString() : '…'"></span>
                                </div>
                            </div>
                        </template>
                        <div x-show="schedModal.qtyLoading" class="text-teal-500 text-center">Loading schedule history…</div>
                        <div class="flex justify-between pt-1 border-t border-teal-200">
                            <span class="text-teal-600">Cycle Time</span>
                            <span class="font-semibold" x-text="schedCycleTimeLabel"></span>
                        </div>
                        <div class="flex justify-between" x-show="schedModal.form.shift_ids.length > 0 && schedCapacityLabel">
                            <span class="text-teal-600">Daily Capacity</span>
                            <span class="font-semibold text-indigo-700" x-text="schedCapacityLabel"></span>
                        </div>
                    </div>

                    {{-- Schedule history by process (collapsible) --}}
                    <template x-if="schedModal.qtySummary && schedModal.qtySummary.by_process.length > 0">
                        <div class="rounded-lg bg-gray-50 border border-gray-200 text-xs overflow-hidden">
                            <button @click="schedModal.showHistory = !schedModal.showHistory"
                                    class="w-full flex items-center justify-between px-3 py-2 text-gray-600 font-medium hover:bg-gray-100 transition-colors">
                                <span class="flex items-center gap-1.5">
                                    <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                    Schedule History by Process
                                </span>
                                <span x-text="schedModal.showHistory ? '▲' : '▼'" class="text-gray-400 text-[10px]"></span>
                            </button>
                            <template x-if="schedModal.showHistory">
                                <div class="border-t border-gray-200 divide-y divide-gray-100">
                                    <template x-for="row in schedModal.qtySummary.by_process" :key="row.part_process_id ?? 'none'">
                                        <div x-data="{ open: true }">
                                            {{-- Process header row --}}
                                            <button @click="open = !open"
                                                    class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 transition-colors text-left">
                                                <span class="flex items-center gap-1.5 font-semibold text-gray-700">
                                                    <span x-show="row.sequence_order"
                                                          class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-indigo-100 text-indigo-600 text-[9px] font-bold"
                                                          x-text="row.sequence_order"></span>
                                                    <span x-text="row.process_name"></span>
                                                </span>
                                                <span class="flex items-center gap-3 text-[11px]">
                                                    <span class="text-amber-700 font-semibold"
                                                          x-text="row.scheduled_qty.toLocaleString() + ' pcs scheduled'"></span>
                                                    <span class="font-semibold"
                                                          :class="Math.max(0, (schedModal.wo?.total_planned_qty ?? 0) - row.scheduled_qty) > 0 ? 'text-green-600' : 'text-gray-400'"
                                                          x-text="Math.max(0, (schedModal.wo?.total_planned_qty ?? 0) - row.scheduled_qty).toLocaleString() + ' rem'"></span>
                                                    <span x-text="open ? '▲' : '▼'" class="text-gray-400 text-[9px]"></span>
                                                </span>
                                            </button>
                                            {{-- Date breakdown sub-table --}}
                                            <template x-if="open">
                                                <table class="w-full bg-white">
                                                    <thead>
                                                        <tr class="bg-gray-50 border-b border-gray-100">
                                                            <th class="pl-8 pr-3 py-1 text-left text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Date</th>
                                                            <th class="px-3 py-1 text-right text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Planned Qty</th>
                                                            <th class="px-3 py-1 text-right text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Cumulative</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <template x-for="(d, idx) in row.dates" :key="d.planned_date">
                                                            <tr class="border-b border-gray-50 hover:bg-blue-50/30">
                                                                <td class="pl-8 pr-3 py-1.5 text-gray-600 font-mono text-[11px]" x-text="d.planned_date"></td>
                                                                <td class="px-3 py-1.5 text-right font-semibold text-gray-700 text-[11px]"
                                                                    x-text="d.planned_qty.toLocaleString()"></td>
                                                                <td class="px-3 py-1.5 text-right text-indigo-600 font-semibold text-[11px]"
                                                                    x-text="row.dates.slice(0, idx + 1).reduce((s, r) => s + r.planned_qty, 0).toLocaleString()"></td>
                                                            </tr>
                                                        </template>
                                                    </tbody>
                                                </table>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- Part Process --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            Part Process *
                            <span class="font-normal text-gray-400">(determines cycle time)</span>
                        </label>
                        <template x-if="schedProcesses.length === 0">
                            <p class="text-xs text-amber-600 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                                No routing defined for this part. Scheduling will use the part's std cycle time.
                            </p>
                        </template>
                        <template x-if="schedProcesses.length > 0">
                            <select x-model="schedModal.form.part_process_id"
                                    @change="if (schedActiveRemaining !== null) schedModal.form.plan_qty = schedActiveRemaining; checkMachineAvailability()"
                                    class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-teal-400 focus:outline-none focus:ring-1 focus:ring-teal-300">
                                <option value="">— Select process —</option>
                                <template x-for="proc in schedProcesses" :key="proc.id">
                                    <option :value="proc.id"
                                            x-text="proc.sequence_order + '. ' + proc.process_master_name + (proc.effective_cycle_time > 0 ? ' — ' + toMMSS(proc.effective_cycle_time) + ' min' : '')">
                                    </option>
                                </template>
                            </select>
                        </template>
                    </div>

                    {{-- Machine --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Machine *</label>
                        <select x-model="schedModal.form.machine_id"
                                @change="schedModal.availability = null; checkMachineAvailability()"
                                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-teal-400 focus:outline-none focus:ring-1 focus:ring-teal-300">
                            <option value="">— Select machine —</option>
                            <template x-for="m in allMachines" :key="m.id">
                                <option :value="m.id" x-text="m.name"></option>
                            </template>
                        </select>
                    </div>

                    {{-- Shifts (multi-select checkboxes) --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            Shift *
                            <span class="font-normal text-gray-400">(select one or more)</span>
                        </label>
                        <div class="rounded-lg border border-gray-200 divide-y divide-gray-100 overflow-hidden">
                            <template x-for="s in allShifts" :key="s.id">
                                <label class="flex items-center gap-3 px-3 py-2 cursor-pointer transition-colors"
                                       :class="schedModal.form.shift_ids.includes(s.id) ? 'bg-teal-50' : 'hover:bg-gray-50'">
                                    <input type="checkbox"
                                           :checked="schedModal.form.shift_ids.includes(s.id)"
                                           @change="toggleSchedShift(s.id)"
                                           class="h-4 w-4 rounded border-gray-300 text-teal-600 focus:ring-teal-400">
                                    <span class="flex-1 text-sm font-medium"
                                          :class="schedModal.form.shift_ids.includes(s.id) ? 'text-teal-700' : 'text-gray-700'"
                                          x-text="s.name"></span>
                                    <span class="text-[11px] text-gray-400"
                                          x-text="((s.duration_min || 0) - (s.break_min || 0)) + ' min'"></span>
                                </label>
                            </template>
                        </div>

                        {{-- Selected shifts summary --}}
                        <div x-show="schedModal.form.shift_ids.length > 0" class="mt-1.5 flex flex-wrap gap-1">
                            <template x-for="sid in schedModal.form.shift_ids" :key="sid">
                                <span class="inline-flex items-center gap-1 rounded-full bg-teal-100 text-teal-700 text-[11px] font-semibold px-2 py-0.5">
                                    <span x-text="allShifts.find(s=>s.id==sid)?.name ?? sid"></span>
                                    <button type="button" @click="toggleSchedShift(sid)" class="hover:text-teal-900">✕</button>
                                </span>
                            </template>
                        </div>

                        <template x-if="schedModal.form.shift_ids.length > 1">
                            <p class="mt-1 text-[11px] text-indigo-600 font-medium">
                                Combined: <span x-text="schedCombinedCapacityMin + ' min/day'"></span>
                                <template x-if="schedEffectiveCycleMin > 0">
                                    — <span x-text="Math.floor(schedCombinedCapacityMin / schedEffectiveCycleMin).toLocaleString()"></span> pcs/day
                                </template>
                            </p>
                        </template>
                    </div>

                    {{-- Plan Qty --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            Plan Qty *
                            <template x-if="schedModal.qtySummary">
                                <span class="font-normal text-gray-400">
                                    (remaining: <span :class="schedActiveRemaining > 0 ? 'font-semibold text-green-600' : 'font-semibold text-red-500'"
                                                      x-text="schedActiveRemaining !== null ? schedActiveRemaining.toLocaleString() : '…'"></span>
                                    of <span x-text="schedModal.wo?.total_planned_qty?.toLocaleString()"></span>)
                                </span>
                            </template>
                        </label>

                        {{-- Remaining = 0 → fully scheduled for this process, block input --}}
                        <template x-if="schedModal.qtySummary && schedActiveRemaining === 0">
                            <div class="rounded-lg bg-red-50 border border-red-200 px-3 py-2.5 text-xs text-red-700 flex items-start gap-2">
                                <svg class="h-4 w-4 shrink-0 mt-0.5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span x-text="schedModal.form.part_process_id
                                    ? `All ${(schedModal.wo?.total_planned_qty ?? 0).toLocaleString()} units are already scheduled for this process. Select a different process or cancel existing plans.`
                                    : `All ${(schedModal.wo?.total_planned_qty ?? 0).toLocaleString()} units are already scheduled. Cancel existing plans to reschedule.`">
                                </span>
                            </div>
                        </template>

                        <template x-if="!schedModal.qtySummary || schedActiveRemaining > 0">
                            <div>
                                <div class="flex items-center gap-2">
                                    <input type="number"
                                           x-model.number="schedModal.form.plan_qty"
                                           min="1"
                                           :max="schedActiveRemaining !== null ? schedActiveRemaining : schedModal.wo?.total_planned_qty"
                                           :class="schedPlanQtyError ? 'border-red-400 bg-red-50' : 'border-gray-200'"
                                           class="flex-1 rounded-lg border px-3 py-2 text-sm focus:border-teal-400 focus:outline-none focus:ring-1 focus:ring-teal-300">
                                    <template x-if="schedActiveRemaining > 0">
                                        <button type="button"
                                                @click="schedModal.form.plan_qty = schedActiveRemaining"
                                                class="shrink-0 rounded-lg border border-green-300 bg-green-50 px-2.5 py-2 text-xs font-medium text-green-700 hover:bg-green-100 transition-colors whitespace-nowrap">
                                            Use remaining
                                        </button>
                                    </template>
                                    <template x-if="schedModal.wo && (!schedModal.qtySummary || schedActiveRemaining === schedModal.wo.total_planned_qty)">
                                        <button type="button"
                                                @click="schedModal.form.plan_qty = schedModal.wo.total_planned_qty"
                                                class="shrink-0 rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-2 text-xs font-medium text-gray-600 hover:bg-gray-100 transition-colors whitespace-nowrap">
                                            Full qty
                                        </button>
                                    </template>
                                </div>
                                {{-- Exceeds remaining --}}
                                <template x-if="schedPlanQtyError">
                                    <p class="mt-1 text-[11px] text-red-600 font-medium" x-text="schedPlanQtyError"></p>
                                </template>
                                <template x-if="!schedPlanQtyError && schedModal.form.plan_qty > 0 && schedModal.form.shift_ids.length > 0 && schedEffectiveCycleMin > 0">
                                    <p class="mt-1 text-[11px] text-gray-400"
                                       x-text="'Est. ' + schedEstDays + ' day(s) to complete ' + schedModal.form.plan_qty.toLocaleString() + ' units'"></p>
                                </template>
                            </div>
                        </template>
                    </div>

                    {{-- Start date --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Start Date *</label>
                        <input type="date"
                               x-model="schedModal.form.start_date"
                               @change="schedModal.form.allow_week_off_holiday = false; checkMachineAvailability()"
                               class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-teal-400 focus:outline-none focus:ring-1 focus:ring-teal-300">

                        {{-- Week-off / Holiday warning --}}
                        <template x-if="schedModal.form.start_date && isOffDay(schedModal.form.start_date)">
                            <div class="mt-1.5 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5">
                                <div class="flex items-start gap-2">
                                    <svg class="h-4 w-4 shrink-0 mt-0.5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <div class="flex-1">
                                        <p class="text-xs font-semibold text-amber-800"
                                           x-text="getOffDayReason(schedModal.form.start_date)"></p>
                                        <p class="text-[11px] text-amber-600 mt-0.5">
                                            By default these days are skipped during scheduling.
                                        </p>
                                        <label class="mt-2 flex items-center gap-2 cursor-pointer select-none">
                                            <input type="checkbox"
                                                   x-model="schedModal.form.allow_week_off_holiday"
                                                   class="h-3.5 w-3.5 rounded border-amber-300 accent-amber-600">
                                            <span class="text-xs font-medium text-amber-800">
                                                Yes, plan on week-off / holiday days too
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </template>

                        {{-- Availability feedback --}}
                        <div x-show="schedModal.availChecking" class="mt-1.5 flex items-center gap-1.5 text-[11px] text-gray-400">
                            <svg class="h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                            </svg>
                            Checking machine availability…
                        </div>

                        {{-- Machine FULL on selected date --}}
                        <template x-if="!schedModal.availChecking && schedModal.availability && schedModal.availability.is_full">
                            <div class="mt-1.5 rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-xs text-red-700">
                                <div class="flex items-start gap-2">
                                    <svg class="h-4 w-4 shrink-0 mt-0.5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636"/>
                                    </svg>
                                    <div class="space-y-1">
                                        <p class="font-semibold">
                                            <span x-text="schedModal.availability.machine_name"></span>
                                            — all selected shifts fully booked on
                                            <span x-text="schedModal.availability.date"></span>.
                                        </p>
                                        {{-- Per-shift detail --}}
                                        <template x-if="schedModal.availability.shift_results && schedModal.availability.shift_results.length > 1">
                                            <div class="space-y-0.5 mt-1">
                                                <template x-for="sr in schedModal.availability.shift_results" :key="sr.date + '-' + (sr.shift_id ?? 0)">
                                                    <p class="flex items-center gap-1.5">
                                                        <span class="inline-block w-2 h-2 rounded-full"
                                                              :class="sr.is_full ? 'bg-red-400' : 'bg-green-400'"></span>
                                                        <span x-text="allShifts.find(s=>s.id == schedModal.form.shift_ids[schedModal.availability.shift_results.indexOf(sr)])?.name ?? 'Shift'"></span>:
                                                        <span x-text="sr.is_full ? 'Full (' + sr.used_min + '/' + sr.capacity_min + ' min)' : sr.free_min + ' min free (' + (sr.free_qty ?? 0) + ' pcs)'"></span>
                                                    </p>
                                                </template>
                                            </div>
                                        </template>
                                        <template x-if="schedModal.availability.next_available_date">
                                            <p class="flex items-center gap-2 mt-1">
                                                Next available (all shifts):
                                                <strong x-text="schedModal.availability.next_available_date"></strong>
                                                <button type="button"
                                                        @click="schedModal.form.start_date = schedModal.availability.next_available_date; checkMachineAvailability()"
                                                        class="rounded bg-red-600 hover:bg-red-700 text-white px-2 py-0.5 font-semibold transition-colors">
                                                    Use this date
                                                </button>
                                            </p>
                                        </template>
                                        <template x-if="!schedModal.availability.next_available_date">
                                            <p class="text-red-500 mt-1">No availability found in the next 60 days.</p>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </template>

                        {{-- Machine has capacity (at least one shift free) --}}
                        <template x-if="!schedModal.availChecking && schedModal.availability && !schedModal.availability.is_full">
                            <div class="mt-1.5 rounded-lg bg-green-50 border border-green-200 px-3 py-2 text-[11px] text-green-700 space-y-0.5">
                                <p class="font-semibold flex items-center gap-1.5">
                                    <svg class="h-3.5 w-3.5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Available on <span x-text="schedModal.availability.date"></span>
                                    — <span x-text="schedModal.availability.free_min"></span> min free
                                    (<span x-text="Number(schedModal.availability.free_qty ?? 0).toLocaleString()"></span> pcs combined)
                                </p>
                                {{-- Per-shift breakdown when multiple selected --}}
                                <template x-if="schedModal.availability.shift_results && schedModal.availability.shift_results.length > 1">
                                    <div class="space-y-0.5 pl-5">
                                        <template x-for="(sr, i) in schedModal.availability.shift_results" :key="i">
                                            <p class="flex items-center gap-1.5">
                                                <span class="inline-block w-2 h-2 rounded-full"
                                                      :class="sr.is_full ? 'bg-red-400' : 'bg-green-400'"></span>
                                                <span x-text="allShifts.find(s=>s.id == schedModal.form.shift_ids[i])?.name ?? 'Shift'"></span>:
                                                <span x-text="sr.is_full ? 'Full' : sr.free_min + ' min free (' + (sr.free_qty ?? 0) + ' pcs)'"></span>
                                            </p>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>

                    {{-- Error --}}
                    <template x-if="schedModal.error">
                        <p class="rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-xs text-red-700" x-text="schedModal.error"></p>
                    </template>

                    {{-- Result --}}
                    <template x-if="schedModal.result">
                        <div class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-xs text-green-800 space-y-1">
                            <p class="font-semibold text-green-700 text-sm" x-text="schedModal.result.message"></p>
                            <div class="flex justify-between">
                                <span class="text-green-600">Plans created</span>
                                <span class="font-semibold" x-text="schedModal.result.plan_count + ' day(s)'"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-green-600">Date range</span>
                                <span class="font-semibold" x-text="formatDate(schedModal.result.from_date) + ' → ' + formatDate(schedModal.result.to_date)"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-green-600">Qty scheduled this run</span>
                                <span class="font-semibold" x-text="schedModal.result.total_qty?.toLocaleString()"></span>
                            </div>
                            <template x-if="schedActiveRemaining !== null">
                                <div class="flex justify-between border-t border-green-200 pt-1 mt-1">
                                    <span class="text-green-600" x-text="schedModal.form.part_process_id ? 'Remaining (process) after' : 'Remaining after this'"></span>
                                    <span class="font-semibold"
                                          :class="Math.max(0, schedActiveRemaining - schedModal.result.total_qty) > 0 ? 'text-amber-700' : 'text-green-700'"
                                          x-text="Math.max(0, schedActiveRemaining - schedModal.result.total_qty).toLocaleString()"></span>
                                </div>
                            </template>
                        </div>
                    </template>

                </div>

                {{-- Footer --}}
                <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4">
                    <button @click="schedModal.open = false"
                            class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 transition-colors">
                        Close
                    </button>
                    <button @click="saveSchedule()"
                            x-show="!schedModal.result"
                            :disabled="schedModal.saving
                                || !schedModal.form.machine_id
                                || !schedModal.form.shift_ids.length
                                || !schedModal.form.start_date
                                || !(schedModal.form.plan_qty > 0)
                                || !!schedPlanQtyError
                                || schedActiveRemaining === 0
                                || (schedModal.availability && schedModal.availability.is_full)"
                            :class="(schedModal.saving
                                || !schedModal.form.machine_id
                                || !schedModal.form.shift_ids.length
                                || !schedModal.form.start_date
                                || !(schedModal.form.plan_qty > 0)
                                || !!schedPlanQtyError
                                || schedActiveRemaining === 0
                                || (schedModal.availability && schedModal.availability.is_full))
                                ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                                : 'bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white shadow-md hover:shadow-lg cursor-pointer'"
                            class="inline-flex items-center gap-2 rounded-lg px-6 py-2.5 text-sm font-bold transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-1">
                        <svg x-show="schedModal.saving" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <svg x-show="!schedModal.saving" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span x-text="schedModal.saving ? 'Scheduling…' : 'Schedule Production'"></span>
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
function workOrderManager(apiToken, isSuperAdmin, factoryId, factories, allCustomers, allParts, allMachines, allShifts, weekOffDays, holidays) {
    return {
        orders:     [],
        pagination: {},
        loading:    false,
        flash:      { type: '', message: '' },
        isSuperAdmin: isSuperAdmin,
        factories:  factories || [],
        allCustomers: allCustomers || [],
        allParts:   allParts || [],
        allMachines: allMachines || [],
        allShifts:  allShifts || [],
        weekOffDays: (weekOffDays || []).map(Number),
        holidays:    (holidays || []),

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

        schedModal: {
            open: false, saving: false, wo: null, error: null, result: null,
            qtyLoading: false, qtySummary: null, showHistory: false,
            form: { part_process_id: '', machine_id: '', shift_ids: [], start_date: '', plan_qty: '' },
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
            // Factory-scoped users: PHP FactoryScope already filters — return as-is
            if (!this.isSuperAdmin) return this.allCustomers;
            // Super-admin: filter by whichever factory is selected in the modal (or filter bar)
            const fid = this.modal.form.factory_id || this.currentFactoryId;
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

        // ── Schedule modal computed ────────────────────────────

        // All processes for the WO's part (for the process dropdown)
        get schedProcesses() {
            const wo = this.schedModal.wo;
            if (!wo) return [];
            const part = this.allParts.find(p => p.id == wo.part_id);
            return (part && part.processes) ? part.processes : [];
        },

        // Effective cycle time for the currently selected process (or part fallback)
        get schedEffectiveCycleMin() {
            const procId = this.schedModal.form.part_process_id;
            if (procId) {
                const proc = this.schedProcesses.find(p => p.id == procId);
                if (proc && proc.effective_cycle_time > 0) return parseFloat(proc.effective_cycle_time);
            }
            // Fallback: part level
            const wo   = this.schedModal.wo;
            const part = wo ? this.allParts.find(p => p.id == wo.part_id) : null;
            if (!part) return 0;
            if (part.total_cycle_time > 0) return parseFloat(part.total_cycle_time);
            return part.cycle_time_std > 0 ? parseFloat(part.cycle_time_std) / 60 : 0;
        },

        get schedCycleTimeLabel() {
            const ct = this.schedEffectiveCycleMin;
            if (ct <= 0) return 'Not set';
            return this.toMMSS(ct) + ' min/unit';
        },

        // Total available minutes/day across all selected shifts
        get schedCombinedCapacityMin() {
            const ids = this.schedModal.form.shift_ids || [];
            if (!ids.length) return 0;
            return ids.reduce((sum, id) => {
                const s = this.allShifts.find(s => s.id == id);
                return sum + (s ? (parseFloat(s.duration_min) || 0) - (parseFloat(s.break_min) || 0) : 0);
            }, 0);
        },

        get schedCapacityLabel() {
            const ids = this.schedModal.form.shift_ids || [];
            const ct  = this.schedEffectiveCycleMin;
            if (!ids.length || ct <= 0) return '';
            const avail    = this.schedCombinedCapacityMin;
            const dailyCap = Math.floor(avail / ct);
            return dailyCap + ' units/day (' + Math.round(avail) + ' min combined)';
        },

        // Estimated days for the current plan_qty across all selected shifts
        get schedEstDays() {
            const qty = parseInt(this.schedModal.form.plan_qty) || 0;
            const ct  = this.schedEffectiveCycleMin;
            if (!qty || !this.schedModal.form.shift_ids.length || ct <= 0) return 0;
            const perDay = Math.floor(this.schedCombinedCapacityMin / ct);
            if (perDay <= 0) return 0;
            return Math.ceil(qty / perDay);
        },

        // Remaining qty scoped to the selected process (or total if no process selected)
        get schedActiveRemaining() {
            if (!this.schedModal.qtySummary) return null;
            const processId = this.schedModal.form.part_process_id;
            if (!processId) return this.schedModal.qtySummary.remaining;
            const proc      = this.schedModal.qtySummary.by_process.find(p => p.part_process_id == processId);
            const scheduled = proc ? (proc.scheduled_qty || 0) : 0;
            return Math.max(0, (this.schedModal.qtySummary.total_planned_qty || 0) - scheduled);
        },

        get schedPlanQtyError() {
            const qty = parseInt(this.schedModal.form.plan_qty) || 0;
            if (!qty || this.schedActiveRemaining === null) return null;
            if (qty > this.schedActiveRemaining) {
                return `Cannot plan ${qty.toLocaleString()} — only ${this.schedActiveRemaining.toLocaleString()} units remaining for this process.`;
            }
            return null;
        },

        // MM:SS formatter shared with schedule modal (mirrors routing builder)
        toMMSS(decimalMinutes) {
            const v = parseFloat(decimalMinutes);
            if (isNaN(v) || v <= 0) return '0:00';
            const mm = Math.floor(v);
            const ss = Math.round((v - mm) * 60);
            if (ss === 60) return (mm + 1) + ':00';
            return mm + ':' + (ss < 10 ? '0' + ss : String(ss));
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
            const customerId   = wo.customer_id;
            const partId       = wo.part_id;
            const selectedPart = this.allParts.find(p => p.id == partId) || null;

            // Open modal with blank customer/part so x-for renders options first
            this.modal = {
                open: true, mode: 'edit', saving: false, wo, error: null, errors: {},
                selectedPart,
                form: {
                    factory_id:             wo.factory_id,
                    customer_id:            '',
                    part_id:                '',
                    order_qty:              wo.order_qty,
                    excess_qty:             wo.excess_qty,
                    expected_delivery_date: wo.expected_delivery_date,
                    planned_start_date:     wo.planned_start_date || '',
                    priority:               wo.priority,
                    status:                 wo.status,
                    notes:                  wo.notes || '',
                },
            };

            // After x-if inserts template and x-for renders customer options, set customer_id
            Alpine.nextTick(() => {
                this.modal.form.customer_id = customerId;
                // After customer options selected and partsForCustomer populates, set part_id
                Alpine.nextTick(() => {
                    this.modal.form.part_id = partId;
                });
            });
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

        // ── Schedule Production ────────────────────────────────
        // ── Week-off / Holiday helpers ───────────────────────────
        isWeekOff(dateStr) {
            if (!dateStr || !this.weekOffDays.length) return false;
            // getDay() returns 0=Sun … 6=Sat, matching our weekOffDays convention
            const d = new Date(dateStr + 'T00:00:00');
            return this.weekOffDays.includes(d.getDay());
        },

        isHoliday(dateStr) {
            if (!dateStr || !this.holidays.length) return false;
            return this.holidays.some(h => h.date === dateStr);
        },

        isOffDay(dateStr) {
            return this.isWeekOff(dateStr) || this.isHoliday(dateStr);
        },

        getOffDayReason(dateStr) {
            const parts = [];
            if (this.isWeekOff(dateStr)) {
                const days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
                const d = new Date(dateStr + 'T00:00:00');
                parts.push(days[d.getDay()] + ' is a week-off day');
            }
            const hol = this.holidays.find(h => h.date === dateStr);
            if (hol) parts.push(`Public holiday: ${hol.name}`);
            return parts.join(' · ');
        },

        openSchedule(wo) {
            this.schedModal = {
                open:        true,
                saving:      false,
                wo:          wo,
                error:       null,
                result:      null,
                qtyLoading:    true,
                qtySummary:    null,
                showHistory:   false,
                availChecking: false,
                availability:  null,
                form: {
                    part_process_id:         '',
                    machine_id:              '',
                    shift_ids:               [],
                    start_date:              new Date().toISOString().slice(0, 10),
                    plan_qty:                wo.total_planned_qty ?? '',
                    allow_week_off_holiday:  false,
                },
            };
            // Fetch existing schedule history
            fetch(`/api/v1/work-orders/${wo.id}/scheduled-qty`, { headers: this.headers })
                .then(r => r.json())
                .then(data => {
                    this.schedModal.qtySummary  = data;
                    this.schedModal.qtyLoading  = false;
                    // Default plan_qty to remaining (if any scheduled already)
                    // Default to total remaining (no process selected yet)
                    if (data.remaining > 0 && data.total_scheduled > 0) {
                        this.schedModal.form.plan_qty = data.remaining;
                    }
                })
                .catch(() => { this.schedModal.qtyLoading = false; });
        },

        async saveSchedule() {
            this.schedModal.saving = true;
            this.schedModal.error  = null;
            const payload = {
                machine_id:              this.schedModal.form.machine_id,
                shift_ids:               this.schedModal.form.shift_ids,
                start_date:              this.schedModal.form.start_date,
                part_process_id:         this.schedModal.form.part_process_id || null,
                plan_qty:                parseInt(this.schedModal.form.plan_qty) || null,
                allow_week_off_holiday:  this.schedModal.form.allow_week_off_holiday ? 1 : 0,
            };
            try {
                const res  = await fetch(`/api/v1/work-orders/${this.schedModal.wo.id}/schedule`, {
                    method:  'POST',
                    headers: this.headers,
                    body:    JSON.stringify(payload),
                });
                const data = await res.json();
                if (!res.ok) {
                    this.schedModal.error = data.message ?? 'Scheduling failed.';
                } else {
                    this.schedModal.result = data;
                }
            } catch (e) {
                this.schedModal.error = 'Network error. Please retry.';
            } finally {
                this.schedModal.saving = false;
            }
        },

        // ── Machine availability check ─────────────────────────
        // Called whenever machine, shift, start_date, or part_process changes.
        // Requires machine + shift + date at minimum.
        // Toggle a shift in/out of the selected shift_ids array, then re-check availability
        toggleSchedShift(shiftId) {
            const id  = parseInt(shiftId);
            const idx = this.schedModal.form.shift_ids.indexOf(id);
            if (idx === -1) {
                this.schedModal.form.shift_ids.push(id);
            } else {
                this.schedModal.form.shift_ids.splice(idx, 1);
            }
            this.schedModal.availability = null;
            this.checkMachineAvailability();
        },

        checkMachineAvailability() {
            const machineId     = this.schedModal.form.machine_id;
            const shiftIds      = this.schedModal.form.shift_ids || [];
            const startDate     = this.schedModal.form.start_date;
            const partProcessId = this.schedModal.form.part_process_id;

            if (!machineId || !shiftIds.length || !startDate) {
                this.schedModal.availability = null;
                return;
            }

            this.schedModal.availChecking = true;
            this.schedModal.availability  = null;

            // Check all selected shifts in parallel; merge into a single result
            const requests = shiftIds.map(sid => {
                const p = new URLSearchParams({ machine_id: machineId, shift_id: sid, start_date: startDate });
                if (partProcessId) p.set('part_process_id', partProcessId);
                return fetch(`/api/v1/machine-availability?${p}`, { headers: this.headers }).then(r => r.json());
            });

            Promise.all(requests)
                .then(results => {
                    // Any shift with capacity → not fully blocked
                    const anyFree    = results.some(r => !r.is_full);
                    const allFull    = results.every(r => r.is_full);
                    const totalFreeMin = results.reduce((s, r) => s + (r.free_min || 0), 0);
                    const totalFreeQty = results.reduce((s, r) => s + (r.free_qty || 0), 0);

                    // For next_available_date: latest date across all full shifts
                    let nextDate = null;
                    if (allFull) {
                        const nextDates = results.map(r => r.next_available_date).filter(Boolean);
                        nextDate = nextDates.sort().pop() ?? null; // latest
                    }

                    this.schedModal.availability = {
                        date:                 startDate,
                        machine_name:         results[0]?.machine_name ?? '',
                        is_full:              allFull,
                        used_min:             results.reduce((s, r) => s + (r.used_min || 0), 0),
                        capacity_min:         results.reduce((s, r) => s + (r.capacity_min || 0), 0),
                        free_min:             totalFreeMin,
                        free_qty:             totalFreeQty,
                        shift_results:        results,
                        next_available_date:  nextDate,
                        next_free_qty:        null,
                    };
                    this.schedModal.availChecking = false;
                })
                .catch(() => { this.schedModal.availChecking = false; });
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

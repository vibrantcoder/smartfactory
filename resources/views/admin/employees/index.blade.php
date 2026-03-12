@extends('admin.layouts.app')
@section('title', 'Employee Permissions')

@section('content')
<div
    x-data="employeePerms(
        {{ $employees->toJson() }},
        {{ $machines->toJson() }},
        {{ json_encode($permissionGroups) }}
    )"
    x-init="init()"
>

{{-- ── Toast ──────────────────────────────────────────────────────────── --}}
<div x-show="toast.show" x-cloak
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 translate-y-2"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     :class="toast.type === 'success' ? 'bg-green-600' : 'bg-red-600'"
     class="fixed top-5 right-5 z-50 flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-white shadow-xl">
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

{{-- ════════════════════════════════════════════════════════════════════
     PERMISSION DRAWER (right-side slide-in panel)
════════════════════════════════════════════════════════════════════ --}}
<div x-show="showDrawer" x-cloak
     class="fixed inset-0 z-40 flex"
     @keydown.escape.window="showDrawer = false">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm"
         @click="showDrawer = false"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
    </div>

    {{-- Drawer panel --}}
    <div class="relative ml-auto flex h-full w-full max-w-2xl flex-col bg-white shadow-2xl"
         @click.stop
         x-transition:enter="transition ease-out duration-250"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full">

        {{-- ── Drawer header ─────────────────────────────────────── --}}
        <div class="flex items-start justify-between border-b border-gray-200 px-6 py-4 shrink-0">
            <div>
                <h2 class="text-base font-bold text-gray-900" x-text="selected?.name"></h2>
                <p class="text-xs text-gray-500 mt-0.5">
                    <span class="font-medium" x-text="selected?.email"></span>
                    &nbsp;·&nbsp;
                    <span x-show="selected?.role_label" x-text="selected?.role_label" class="text-indigo-600 font-semibold"></span>
                    <span x-show="!selected?.role_label" class="text-gray-400 italic">No role</span>
                </p>
            </div>
            <button @click="showDrawer = false"
                    class="ml-4 rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- ── Drawer body (scrollable) ──────────────────────────── --}}
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-6">

            {{-- Loading spinner --}}
            <div x-show="loadingPerms" class="flex items-center justify-center py-16">
                <svg class="h-8 w-8 animate-spin text-indigo-400" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
            </div>

            <div x-show="!loadingPerms">

                {{-- ── Section 1: Machine Assignment ─────────────── --}}
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                    <h3 class="text-xs font-bold uppercase tracking-widest text-gray-500 mb-3">
                        Machine Assignment
                    </h3>
                    <div class="flex items-center gap-3">
                        <select x-model="machineForm.machine_id"
                                class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                            <option value="">— No machine assigned —</option>
                            <template x-for="m in machines" :key="m.id">
                                <option :value="m.id" x-text="m.name + ' (' + m.code + ')'"></option>
                            </template>
                        </select>
                        <button @click="saveMachine()" :disabled="savingMachine"
                                class="shrink-0 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                            <span x-text="savingMachine ? 'Saving…' : 'Assign'"></span>
                        </button>
                    </div>
                    <p class="mt-2 text-xs text-gray-400">
                        The employee can only see production jobs for their assigned machine.
                    </p>
                </div>

                {{-- ── Section 2: Permission Matrix ──────────────── --}}
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-xs font-bold uppercase tracking-widest text-gray-500">
                            Individual Permissions
                        </h3>
                        <div class="flex items-center gap-3 text-xs">
                            <span class="flex items-center gap-1.5 text-gray-400">
                                <span class="inline-block h-3 w-3 rounded border-2 border-gray-300 bg-gray-100"></span>
                                Via role
                            </span>
                            <span class="flex items-center gap-1.5 text-indigo-600 font-medium">
                                <span class="inline-block h-3 w-3 rounded border-2 border-indigo-500 bg-indigo-500"></span>
                                Direct grant
                            </span>
                        </div>
                    </div>

                    <p class="text-xs text-gray-500 mb-4 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                        <strong>Role permissions</strong> (grayed) are inherited from the employee's role and cannot be unchecked here.
                        Use the checkboxes to grant or revoke <strong>additional individual permissions</strong>.
                    </p>

                    <div class="space-y-4">
                        <template x-for="group in permissionGroups" :key="group.label">
                            <div class="rounded-xl border border-gray-200 overflow-hidden">
                                {{-- Group header --}}
                                <div class="flex items-center justify-between bg-gray-50 border-b border-gray-200 px-4 py-2.5">
                                    <span class="text-xs font-bold text-gray-700" x-text="group.label"></span>
                                    <span class="text-[10px] text-gray-400"
                                          x-text="groupGrantCount(group) + ' granted'"></span>
                                </div>
                                {{-- Permissions grid --}}
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-px bg-gray-100">
                                    <template x-for="perm in group.permissions" :key="perm.value">
                                        <label
                                            :class="[
                                                'flex items-start gap-3 px-4 py-3 bg-white cursor-pointer transition-colors',
                                                isRolePerm(perm.value) ? 'opacity-75 cursor-not-allowed' : 'hover:bg-indigo-50/60',
                                            ]"
                                        >
                                            <div class="relative mt-0.5 shrink-0">
                                                <input type="checkbox"
                                                       :value="perm.value"
                                                       :checked="isGranted(perm.value)"
                                                       :disabled="isRolePerm(perm.value)"
                                                       @change="togglePerm(perm.value, $event.target.checked)"
                                                       class="sr-only">
                                                <div :class="[
                                                         'h-4 w-4 rounded border-2 flex items-center justify-center transition-all',
                                                         isRolePerm(perm.value)
                                                             ? 'border-gray-300 bg-gray-100'
                                                             : (isDirectPerm(perm.value) ? 'border-indigo-500 bg-indigo-500' : 'border-gray-300 bg-white'),
                                                     ]">
                                                    <svg x-show="isGranted(perm.value)"
                                                         class="h-2.5 w-2.5 text-white"
                                                         :class="isRolePerm(perm.value) ? 'text-gray-400' : 'text-white'"
                                                         fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                              stroke-width="3" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                </div>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-xs font-medium text-gray-800" x-text="perm.label"></p>
                                                <p class="text-[10px] text-gray-400 font-mono" x-text="perm.value"></p>
                                                <span x-show="isRolePerm(perm.value)"
                                                      class="inline-block mt-0.5 text-[9px] font-semibold uppercase tracking-wider text-gray-400 bg-gray-100 rounded px-1 py-0.5">
                                                    via role
                                                </span>
                                            </div>
                                        </label>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

            </div>{{-- end !loadingPerms --}}
        </div>{{-- end scrollable body --}}

        {{-- ── Drawer footer ─────────────────────────────────────── --}}
        <div class="shrink-0 border-t border-gray-200 bg-gray-50 px-6 py-4 flex items-center justify-between">
            <div class="text-xs text-gray-500">
                <span class="font-semibold text-gray-800" x-text="directPerms.length"></span>
                direct permission<span x-show="directPerms.length !== 1">s</span> granted
            </div>
            <div class="flex gap-3">
                <button @click="showDrawer = false"
                        class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                    Close
                </button>
                <button @click="savePermissions()" :disabled="savingPerms"
                        class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50 transition-colors flex items-center gap-2">
                    <svg x-show="savingPerms" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span x-text="savingPerms ? 'Saving…' : 'Save Permissions'"></span>
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ════════════════════════════════════════════════════════════════════
     PAGE BODY — Employee Table
════════════════════════════════════════════════════════════════════ --}}

{{-- Info banner --}}
<div class="mb-5 flex items-start gap-3 rounded-xl bg-indigo-50 border border-indigo-200 px-4 py-3 text-sm text-indigo-700">
    <svg class="h-5 w-5 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    <div>
        <p class="font-semibold">Individual Permission Management</p>
        <p class="text-xs mt-0.5 text-indigo-600">
            Each employee inherits permissions from their role. Use the <strong>Manage</strong> button to grant or revoke
            additional permissions per person, and to assign their machine.
        </p>
    </div>
</div>

{{-- Search & filter bar --}}
<div class="mb-4 flex flex-wrap items-center gap-3">
    <div class="relative">
        <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400"
             fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
        </svg>
        <input type="text" x-model="search"
               placeholder="Search by name or email…"
               class="w-64 rounded-lg border border-gray-300 pl-9 pr-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
    </div>

    <select x-model="filterRole"
            class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
        <option value="">All Roles</option>
        <option value="factory-admin">Factory Admin</option>
        <option value="production-manager">Production Manager</option>
        <option value="supervisor">Supervisor</option>
        <option value="operator">Operator</option>
        <option value="viewer">Viewer</option>
    </select>

    <span class="ml-auto text-sm text-gray-500">
        <span class="font-semibold text-gray-800" x-text="filteredEmployees.length"></span> employee<span x-show="filteredEmployees.length !== 1">s</span>
    </span>
</div>

{{-- Employee table --}}
<div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                <th class="px-5 py-3">Employee</th>
                <th class="px-5 py-3">Role</th>
                <th class="px-5 py-3">Assigned Machine</th>
                <th class="px-5 py-3 text-center">Direct Permissions</th>
                <th class="px-5 py-3">Status</th>
                <th class="px-5 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">

            {{-- Empty state --}}
            <template x-if="filteredEmployees.length === 0">
                <tr>
                    <td colspan="6" class="py-20 text-center text-gray-400">
                        <svg class="h-12 w-12 mx-auto mb-3 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <p class="font-medium text-sm">No employees found</p>
                        <p class="text-xs mt-1">Try adjusting the search or filter.</p>
                    </td>
                </tr>
            </template>

            {{-- Employee rows --}}
            <template x-for="emp in filteredEmployees" :key="emp.id">
                <tr class="hover:bg-slate-50 transition-colors">

                    {{-- Name + email --}}
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-bold text-indigo-600"
                                 x-text="emp.name.charAt(0).toUpperCase()">
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900" x-text="emp.name"></p>
                                <p class="text-xs text-gray-400" x-text="emp.email"></p>
                            </div>
                        </div>
                    </td>

                    {{-- Role badge --}}
                    <td class="px-5 py-3">
                        <template x-if="emp.role_label">
                            <span class="inline-block rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700"
                                  x-text="emp.role_label">
                            </span>
                        </template>
                        <template x-if="!emp.role_label">
                            <span class="text-xs text-gray-400 italic">No role</span>
                        </template>
                    </td>

                    {{-- Assigned machine --}}
                    <td class="px-5 py-3">
                        <template x-if="emp.machine_name">
                            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-700">
                                <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/>
                                </svg>
                                <span x-text="emp.machine_name"></span>
                            </span>
                        </template>
                        <template x-if="!emp.machine_name">
                            <span class="text-xs text-gray-400">—</span>
                        </template>
                    </td>

                    {{-- Direct permissions count --}}
                    <td class="px-5 py-3 text-center">
                        <span x-show="(emp.direct_perm_count ?? 0) > 0"
                              class="inline-block rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-700"
                              x-text="emp.direct_perm_count + ' extra'">
                        </span>
                        <span x-show="!(emp.direct_perm_count ?? 0)"
                              class="text-xs text-gray-300">—</span>
                    </td>

                    {{-- Status --}}
                    <td class="px-5 py-3">
                        <span :class="emp.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                              class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold">
                            <span class="h-1.5 w-1.5 rounded-full"
                                  :class="emp.is_active ? 'bg-green-500' : 'bg-gray-400'"></span>
                            <span x-text="emp.is_active ? 'Active' : 'Inactive'"></span>
                        </span>
                    </td>

                    {{-- Actions --}}
                    <td class="px-5 py-3 text-right">
                        <button @click="openDrawer(emp)"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 transition-colors">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                            Manage
                        </button>
                    </td>

                </tr>
            </template>

        </tbody>
    </table>
</div>

</div>{{-- end x-data --}}
@endsection

@push('scripts')
<script>
function employeePerms(employees, machines, permissionGroups) {
    return {
        // ── Source data ──────────────────────────────────────────
        employees:        employees || [],
        machines:         machines  || [],
        permissionGroups: permissionGroups || [],

        // ── Filter state ─────────────────────────────────────────
        search:     '',
        filterRole: '',

        // ── Drawer state ─────────────────────────────────────────
        showDrawer:    false,
        selected:      null,
        loadingPerms:  false,
        savingPerms:   false,
        savingMachine: false,

        // ── Permissions for currently selected employee ───────────
        rolePerms:   [],   // inherited from their role
        directPerms: [],   // granted directly to this user

        // ── Machine assignment form ───────────────────────────────
        machineForm: { machine_id: '' },

        // ── Toast ─────────────────────────────────────────────────
        toast: { show: false, message: '', type: 'success' },

        // ── CSRF token (from meta tag) ────────────────────────────
        get csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        },

        get headers() {
            return {
                'Content-Type':  'application/json',
                'Accept':        'application/json',
                'X-CSRF-TOKEN':  this.csrfToken,
            };
        },

        // ── Computed ─────────────────────────────────────────────

        get filteredEmployees() {
            const s = this.search.toLowerCase();
            const r = this.filterRole;
            return this.employees.filter(e => {
                const matchSearch = !s ||
                    e.name.toLowerCase().includes(s) ||
                    e.email.toLowerCase().includes(s);
                const matchRole = !r || e.role === r;
                return matchSearch && matchRole;
            });
        },

        // ── Lifecycle ─────────────────────────────────────────────
        init() {},

        // ── Drawer open ──────────────────────────────────────────

        async openDrawer(emp) {
            this.selected      = emp;
            this.showDrawer    = true;
            this.loadingPerms  = true;
            this.rolePerms     = [];
            this.directPerms   = [];
            this.machineForm   = { machine_id: emp.machine_id ? String(emp.machine_id) : '' };

            try {
                const res  = await fetch(`/admin/users/${emp.id}/permissions`, { headers: this.headers });
                if (!res.ok) throw new Error(`Error ${res.status}`);
                const data = await res.json();
                this.rolePerms   = data.role_permissions   || [];
                this.directPerms = data.direct_permissions || [];
            } catch (e) {
                this.showToast('Failed to load permissions: ' + e.message, 'error');
                this.showDrawer = false;
            } finally {
                this.loadingPerms = false;
            }
        },

        // ── Permission helpers ────────────────────────────────────

        isRolePerm(value)   { return this.rolePerms.includes(value); },
        isDirectPerm(value) { return this.directPerms.includes(value); },
        isGranted(value)    { return this.isRolePerm(value) || this.isDirectPerm(value); },

        groupGrantCount(group) {
            return group.permissions.filter(p => this.isGranted(p.value)).length;
        },

        togglePerm(value, checked) {
            // Role permissions are read-only
            if (this.isRolePerm(value)) return;
            if (checked) {
                if (!this.directPerms.includes(value)) this.directPerms.push(value);
            } else {
                this.directPerms = this.directPerms.filter(p => p !== value);
            }
        },

        // ── Save permissions ──────────────────────────────────────

        async savePermissions() {
            this.savingPerms = true;
            try {
                const res = await fetch(`/admin/users/${this.selected.id}/sync-permissions`, {
                    method:  'POST',
                    headers: this.headers,
                    body:    JSON.stringify({ permissions: this.directPerms }),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || `Error ${res.status}`);

                // Update the employee's direct perm count in the table
                const idx = this.employees.findIndex(e => e.id === this.selected.id);
                if (idx !== -1) {
                    this.employees[idx] = {
                        ...this.employees[idx],
                        direct_perm_count: this.directPerms.length,
                    };
                }
                this.showToast(data.message || 'Permissions saved.');
            } catch (e) {
                this.showToast(e.message, 'error');
            } finally {
                this.savingPerms = false;
            }
        },

        // ── Save machine assignment ───────────────────────────────

        async saveMachine() {
            this.savingMachine = true;
            try {
                const machineId = this.machineForm.machine_id
                    ? parseInt(this.machineForm.machine_id)
                    : null;

                const res = await fetch(`/admin/users/${this.selected.id}/assign-machine`, {
                    method:  'POST',
                    headers: this.headers,
                    body:    JSON.stringify({ machine_id: machineId }),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || `Error ${res.status}`);

                // Update selected & employee row
                const machine     = machineId ? this.machines.find(m => m.id === machineId) : null;
                const machineName = machine ? machine.name + ' (' + machine.code + ')' : null;

                this.selected = { ...this.selected, machine_id: machineId, machine_name: machineName };

                const idx = this.employees.findIndex(e => e.id === this.selected.id);
                if (idx !== -1) {
                    this.employees[idx] = {
                        ...this.employees[idx],
                        machine_id:   machineId,
                        machine_name: machineName,
                    };
                }

                this.showToast(data.message || 'Machine assigned.');
            } catch (e) {
                this.showToast(e.message, 'error');
            } finally {
                this.savingMachine = false;
            }
        },

        // ── Toast ─────────────────────────────────────────────────
        showToast(message, type = 'success') {
            this.toast = { show: true, message, type };
            setTimeout(() => { this.toast.show = false; }, 3500);
        },
    };
}
</script>
@endpush

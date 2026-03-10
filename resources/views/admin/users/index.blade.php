@extends('admin.layouts.app')

@section('title', 'Users')

@section('content')

@php
    $isSuperAdmin    = $factoryId === null;
    $factoriesJson   = $factories->isNotEmpty() ? $factories->toJson() : '[]';
    $permGroupsJson  = json_encode($permissionGroups ?? []);
@endphp

<div x-data="userManager(
    {{ $apiToken ? json_encode($apiToken) : 'null' }},
    {{ $isSuperAdmin ? 'true' : 'false' }},
    {{ $factoriesJson }},
    {{ $permGroupsJson }}
)" x-init="init()">

    {{-- Flash --}}
    <template x-if="flash.message">
        <div class="mb-4 rounded-lg border px-4 py-3 text-sm"
             :class="flash.type === 'success' ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800'">
            <span x-text="flash.message"></span>
        </div>
    </template>

    {{-- Table card --}}
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200">

        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
            <div>
                <h2 class="text-sm font-semibold text-gray-700">All Users</h2>
                <p class="text-xs text-gray-400 mt-0.5"><span x-text="pagination.total ?? '…'"></span> users</p>
            </div>
            <template x-if="canCreate">
                <button @click="openCreate()"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-indigo-700 transition-colors shadow-sm">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Add User
                </button>
            </template>
        </div>

        {{-- Skeleton --}}
        <template x-if="loading && users.length === 0">
            <div class="space-y-px">
                <template x-for="i in 5" :key="i">
                    <div class="flex items-center gap-4 px-5 py-4 border-b border-gray-50">
                        <div class="h-8 w-8 animate-pulse rounded-full bg-gray-100"></div>
                        <div class="flex-1 space-y-1.5">
                            <div class="h-3.5 w-48 animate-pulse rounded bg-gray-100"></div>
                            <div class="h-3 w-32 animate-pulse rounded bg-gray-50"></div>
                        </div>
                    </div>
                </template>
            </div>
        </template>

        {{-- Table --}}
        <template x-if="users.length > 0">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-gray-100 bg-gray-50">
                        <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                            <th class="px-5 py-3">User</th>
                            <th class="px-5 py-3">Role</th>
                            @if($isSuperAdmin)
                            <th class="px-5 py-3">Factory</th>
                            @endif
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <template x-for="user in users" :key="user.id">
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-700"
                                             x-text="user.name.charAt(0).toUpperCase()"></div>
                                        <div>
                                            <p class="font-medium text-gray-900" x-text="user.name"></p>
                                            <p class="text-xs text-gray-400" x-text="user.email"></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    <template x-if="user.role_label">
                                        <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700"
                                              x-text="user.role_label"></span>
                                    </template>
                                    <template x-if="!user.role_label">
                                        <span class="text-xs text-gray-400 italic">No Role</span>
                                    </template>
                                </td>
                                @if($isSuperAdmin)
                                <td class="px-5 py-3 text-xs text-gray-500"
                                    x-text="user.factory_id ? 'Factory #' + user.factory_id : 'Global'"></td>
                                @endif
                                <td class="px-5 py-3">
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                                          :class="user.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500'"
                                          x-text="user.is_active ? 'Active' : 'Inactive'"></span>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <template x-if="user.can_edit">
                                            <button @click="openEdit(user)"
                                                    class="rounded-lg bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-200 transition-colors">
                                                Edit
                                            </button>
                                        </template>
                                        {{-- Dedicated Assign Role button --}}
                                        <template x-if="user.can_reassign">
                                            <button @click="openRoleModal(user)"
                                                    class="rounded-lg px-2.5 py-1 text-xs font-medium transition-colors"
                                                    :class="user.role
                                                        ? 'bg-indigo-50 text-indigo-700 hover:bg-indigo-100'
                                                        : 'bg-green-50 text-green-700 hover:bg-green-100'">
                                                <span x-text="user.role ? '✎ Role' : '+ Assign Role'"></span>
                                            </button>
                                        </template>
                                        <template x-if="user.can_edit">
                                            <button @click="openPermDrawer(user)"
                                                    class="rounded-lg bg-violet-50 px-2.5 py-1 text-xs font-medium text-violet-700 hover:bg-violet-100 transition-colors">
                                                Perms
                                            </button>
                                        </template>
                                        <template x-if="user.can_reassign && user.role">
                                            <button @click="revokeRole(user)"
                                                    class="rounded-lg bg-red-50 px-2.5 py-1 text-xs font-medium text-red-600 hover:bg-red-100 transition-colors">
                                                Revoke
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

        <template x-if="!loading && users.length === 0">
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <p class="text-sm text-gray-400">No users found</p>
            </div>
        </template>

        <template x-if="pagination.last_page > 1">
            <div class="flex items-center justify-between border-t border-gray-100 px-5 py-3 text-sm">
                <button @click="changePage(pagination.current_page - 1)"
                        :disabled="pagination.current_page <= 1"
                        class="rounded-lg px-3 py-1.5 text-gray-600 hover:bg-gray-100 disabled:opacity-40 transition-colors">
                    ← Prev
                </button>
                <span class="text-gray-500">
                    Page <strong x-text="pagination.current_page"></strong>
                    of <strong x-text="pagination.last_page"></strong>
                </span>
                <button @click="changePage(pagination.current_page + 1)"
                        :disabled="pagination.current_page >= pagination.last_page"
                        class="rounded-lg px-3 py-1.5 text-gray-600 hover:bg-gray-100 disabled:opacity-40 transition-colors">
                    Next →
                </button>
            </div>
        </template>

    </div>{{-- end card --}}

    {{-- CREATE USER MODAL --}}
    <template x-if="createModal.open">
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
             @click.self="createModal.open = false">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Add New User</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Full Name *</label>
                        <input x-model="createModal.form.name" type="text" placeholder="John Smith"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                               :class="createModal.errors.name ? 'border-red-400' : ''">
                        <p x-show="createModal.errors.name" class="mt-1 text-xs text-red-600"
                           x-text="(createModal.errors.name||[])[0]"></p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Email *</label>
                        <input x-model="createModal.form.email" type="email" placeholder="user@company.com"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                               :class="createModal.errors.email ? 'border-red-400' : ''">
                        <p x-show="createModal.errors.email" class="mt-1 text-xs text-red-600"
                           x-text="(createModal.errors.email||[])[0]"></p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Password *</label>
                        <input x-model="createModal.form.password" type="password" placeholder="Min. 8 characters"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                               :class="createModal.errors.password ? 'border-red-400' : ''">
                        <p x-show="createModal.errors.password" class="mt-1 text-xs text-red-600"
                           x-text="(createModal.errors.password||[])[0]"></p>
                    </div>
                    @if($isSuperAdmin)
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Factory *</label>
                        <select x-model="createModal.form.factory_id"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                :class="createModal.errors.factory_id ? 'border-red-400' : ''">
                            <option value="">-- Select Factory --</option>
                            <template x-for="f in factories" :key="f.id">
                                <option :value="f.id" x-text="f.name"></option>
                            </template>
                        </select>
                        <p x-show="createModal.errors.factory_id" class="mt-1 text-xs text-red-600"
                           x-text="(createModal.errors.factory_id||[])[0]"></p>
                    </div>
                    @endif
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input x-model="createModal.form.is_active" type="checkbox"
                               class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm text-gray-700">Active account</span>
                    </label>
                </div>
                <div class="mt-5 flex justify-end gap-3">
                    <button @click="createModal.open = false"
                            class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 transition-colors">Cancel</button>
                    <button @click="submitCreate()" :disabled="createModal.saving"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                        <span x-show="!createModal.saving">Create User</span>
                        <span x-show="createModal.saving">Creating…</span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{-- EDIT USER MODAL --}}
    <template x-if="editModal.open">
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
             @click.self="editModal.open = false">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900 mb-4">
                    Edit — <span x-text="editModal.user?.name" class="text-indigo-600"></span>
                </h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Full Name *</label>
                        <input x-model="editModal.form.name" type="text"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                               :class="editModal.errors.name ? 'border-red-400' : ''">
                        <p x-show="editModal.errors.name" class="mt-1 text-xs text-red-600"
                           x-text="(editModal.errors.name||[])[0]"></p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Email *</label>
                        <input x-model="editModal.form.email" type="email"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                               :class="editModal.errors.email ? 'border-red-400' : ''">
                        <p x-show="editModal.errors.email" class="mt-1 text-xs text-red-600"
                           x-text="(editModal.errors.email||[])[0]"></p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            New Password
                            <span class="ml-1 font-normal text-gray-400">(leave blank to keep current)</span>
                        </label>
                        <input x-model="editModal.form.password" type="password" placeholder="Min. 8 characters"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                               :class="editModal.errors.password ? 'border-red-400' : ''">
                        <p x-show="editModal.errors.password" class="mt-1 text-xs text-red-600"
                           x-text="(editModal.errors.password||[])[0]"></p>
                    </div>
                    @if($isSuperAdmin)
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Factory</label>
                        <select x-model="editModal.form.factory_id"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                            <option value="">-- Global (no factory) --</option>
                            <template x-for="f in factories" :key="f.id">
                                <option :value="f.id" x-text="f.name"></option>
                            </template>
                        </select>
                    </div>
                    @endif
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input x-model="editModal.form.is_active" type="checkbox"
                               class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm text-gray-700">Active account</span>
                    </label>
                </div>
                <div class="mt-5 flex justify-end gap-3">
                    <button @click="editModal.open = false"
                            class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 transition-colors">Cancel</button>
                    <button @click="submitEdit()" :disabled="editModal.saving"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                        <span x-show="!editModal.saving">Save Changes</span>
                        <span x-show="editModal.saving">Saving…</span>
                    </button>
                </div>
            </div>
        </div>
    </template>


    {{-- ASSIGN MACHINE MODAL --}}
    <template x-if="machineModal.open">
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
             @click.self="machineModal.open = false">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900 mb-1">Assign Machine</h3>
                <p class="text-sm text-gray-500 mb-4">
                    Set the machine for <strong x-text="machineModal.user?.name"></strong>
                </p>
                <div class="mb-5">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Machine</label>
                    <template x-if="machineModal.loadingMachines">
                        <p class="text-sm text-gray-400 italic">Loading machines…</p>
                    </template>
                    <template x-if="!machineModal.loadingMachines">
                        <select x-model="machineModal.selectedMachineId"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-amber-400 focus:outline-none focus:ring-1 focus:ring-amber-400">
                            <option value="">— No machine assigned —</option>
                            <template x-for="m in machineModal.machines" :key="m.id">
                                <option :value="m.id" x-text="m.name + (m.code ? ' (' + m.code + ')' : '')"></option>
                            </template>
                        </select>
                    </template>
                </div>
                <div class="flex items-center gap-3 justify-end">
                    <button @click="machineModal.open = false"
                            class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 transition-colors">Cancel</button>
                    <button @click="submitMachineAssign()"
                            :disabled="machineModal.saving || machineModal.loadingMachines"
                            class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:opacity-50 transition-colors">
                        <span x-show="!machineModal.saving">Save</span>
                        <span x-show="machineModal.saving">Saving…</span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{-- ASSIGN ROLE MODAL --}}
    <template x-if="roleModal.open">
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
             @click.self="roleModal.open = false"
             @keydown.escape.window="roleModal.open = false">
            <div class="w-full max-w-md rounded-2xl bg-white shadow-xl">

                {{-- Header --}}
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">Assign Role</h3>
                        <p class="text-xs text-gray-400 mt-0.5" x-text="roleModal.user?.name + ' — ' + roleModal.user?.email"></p>
                    </div>
                    <button @click="roleModal.open = false"
                            class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 transition-colors">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Body --}}
                <div class="px-6 py-5 space-y-5">

                    {{-- Role radio buttons --}}
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Select Role</p>
                        <template x-if="roleModal.loadingRoles">
                            <p class="text-sm text-gray-400 animate-pulse">Loading roles…</p>
                        </template>
                        <template x-if="!roleModal.loadingRoles">
                            <div class="space-y-2">
                                {{-- No Role option --}}
                                <label class="flex items-center gap-3 rounded-xl border p-3 cursor-pointer transition-colors"
                                       :class="roleModal.selectedRole === ''
                                           ? 'border-red-300 bg-red-50'
                                           : 'border-gray-200 hover:border-gray-300'">
                                    <input type="radio" value="" x-model="roleModal.selectedRole"
                                           @change="roleModal.machines = []"
                                           class="text-red-500 focus:ring-red-400">
                                    <div>
                                        <p class="text-sm font-medium text-gray-700">No Role</p>
                                        <p class="text-xs text-gray-400">Revoke current role assignment</p>
                                    </div>
                                </label>
                                {{-- Assignable roles --}}
                                <template x-for="r in roleModal.assignableRoles" :key="r.value">
                                    <label class="flex items-center gap-3 rounded-xl border p-3 cursor-pointer transition-colors"
                                           :class="roleModal.selectedRole === r.value
                                               ? 'border-indigo-400 bg-indigo-50'
                                               : 'border-gray-200 hover:border-indigo-200'">
                                        <input type="radio" :value="r.value" x-model="roleModal.selectedRole"
                                               @change="onRoleModalRoleChange()"
                                               class="text-indigo-600 focus:ring-indigo-500">
                                        <div>
                                            <p class="text-sm font-medium text-gray-700" x-text="r.label"></p>
                                        </div>
                                    </label>
                                </template>
                            </div>
                        </template>
                    </div>

                    {{-- Machine dropdown — only for operator/viewer --}}
                    <template x-if="roleModal.selectedRole === 'operator' || roleModal.selectedRole === 'viewer'">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Assign Machine</p>
                            <template x-if="roleModal.loadingMachines">
                                <p class="text-sm text-gray-400 animate-pulse">Loading machines…</p>
                            </template>
                            <template x-if="!roleModal.loadingMachines">
                                <select x-model="roleModal.machineId"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-amber-400 focus:outline-none focus:ring-1 focus:ring-amber-400">
                                    <option value="">— No machine —</option>
                                    <template x-for="m in roleModal.machines" :key="m.id">
                                        <option :value="m.id" x-text="m.name + (m.code ? ' (' + m.code + ')' : '')"></option>
                                    </template>
                                </select>
                            </template>
                        </div>
                    </template>

                    {{-- Error --}}
                    <template x-if="roleModal.error">
                        <p class="rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-xs text-red-700" x-text="roleModal.error"></p>
                    </template>
                </div>

                {{-- Footer --}}
                <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4">
                    <button @click="roleModal.open = false"
                            class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 transition-colors">Cancel</button>
                    <button @click="submitRoleAssign()"
                            :disabled="roleModal.saving || roleModal.loadingRoles"
                            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 px-5 py-2 text-sm font-semibold text-white shadow-sm transition-colors">
                        <svg x-show="roleModal.saving" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span x-text="roleModal.saving ? 'Saving…' : 'Save Role'"></span>
                    </button>
                </div>

            </div>
        </div>
    </template>

    {{-- PERMISSIONS MODAL --}}
    <template x-if="permDrawer.open">
        <div class="fixed inset-0 z-50 bg-black/50"
             style="display:flex;align-items:flex-start;justify-content:center;padding:2.5rem 1rem 1rem"
             @click.self="permDrawer.open = false"
             @keydown.escape.window="permDrawer.open = false">

            <div style="background:#fff;border-radius:1rem;box-shadow:0 25px 50px rgba(0,0,0,.25);width:100%;max-width:680px;display:flex;flex-direction:column;max-height:88vh"
                 @click.stop>

                {{-- Header --}}
                <div style="display:flex;align-items:center;gap:0.75rem;border-bottom:1px solid #e5e7eb;padding:1rem 1.5rem;flex-shrink:0">
                    <div style="width:2.5rem;height:2.5rem;border-radius:9999px;background:#ede9fe;display:flex;align-items:center;justify-content:center;font-weight:700;color:#6d28d9;font-size:0.875rem;flex-shrink:0"
                         x-text="permDrawer.user?.name?.charAt(0)?.toUpperCase()"></div>
                    <div style="min-width:0;flex:1">
                        <p style="font-weight:600;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="permDrawer.user?.name"></p>
                        <p style="font-size:0.75rem;color:#9ca3af;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="permDrawer.user?.email"></p>
                    </div>
                    <template x-if="permDrawer.user?.role_label">
                        <span style="background:#eef2ff;color:#4338ca;border-radius:9999px;padding:0.125rem 0.625rem;font-size:0.75rem;font-weight:500;flex-shrink:0"
                              x-text="permDrawer.user?.role_label"></span>
                    </template>
                    <button @click="permDrawer.open = false"
                            style="margin-left:0.25rem;border-radius:0.5rem;padding:0.375rem;color:#9ca3af;cursor:pointer;background:none;border:none;line-height:1;flex-shrink:0"
                            onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='none'">
                        <svg style="width:1.25rem;height:1.25rem" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Loading --}}
                <template x-if="permDrawer.loading">
                    <div style="flex:1;display:flex;align-items:center;justify-content:center;padding:3rem">
                        <p style="font-size:0.875rem;color:#9ca3af">Loading permissions…</p>
                    </div>
                </template>

                {{-- Scrollable content --}}
                <template x-if="!permDrawer.loading">
                    <div style="flex:1;overflow-y:auto;min-height:0;padding:1.25rem 1.5rem">
                        <div style="display:flex;flex-direction:column;gap:1.5rem">

                            {{-- Role Assignment --}}
                            <div>
                                <p style="margin-bottom:0.5rem;font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:#6b7280">Assign Role</p>
                                <template x-if="permDrawer.loadingRoles">
                                    <p style="font-size:0.875rem;color:#9ca3af">Loading roles…</p>
                                </template>
                                <template x-if="!permDrawer.loadingRoles">
                                    <div style="display:flex;flex-wrap:wrap;gap:0.5rem">
                                        <template x-for="r in permDrawer.assignableRoles" :key="r.value">
                                            <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;border-radius:0.5rem;border:1px solid;padding:0.375rem 0.75rem;font-size:0.75rem;font-weight:500;transition:all .15s"
                                                   :style="permDrawer.selectedRole === r.value
                                                       ? 'border-color:#818cf8;background:#eef2ff;color:#4338ca'
                                                       : 'border-color:#e5e7eb;color:#4b5563'">
                                                <input type="radio" :value="r.value" x-model="permDrawer.selectedRole" style="accent-color:#4f46e5">
                                                <span x-text="r.label"></span>
                                            </label>
                                        </template>
                                        <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;border-radius:0.5rem;border:1px solid;padding:0.375rem 0.75rem;font-size:0.75rem;font-weight:500;transition:all .15s"
                                               :style="permDrawer.selectedRole === ''
                                                   ? 'border-color:#fca5a5;background:#fef2f2;color:#dc2626'
                                                   : 'border-color:#e5e7eb;color:#9ca3af'">
                                            <input type="radio" value="" x-model="permDrawer.selectedRole" style="accent-color:#ef4444">
                                            <span>No Role</span>
                                        </label>
                                    </div>
                                </template>
                            </div>

                            {{-- Machine Assignment (operator / viewer only) --}}
                            <template x-if="permDrawer.selectedRole === 'operator' || permDrawer.selectedRole === 'viewer'">
                                <div>
                                    <p style="margin-bottom:0.5rem;font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:#6b7280">Machine Assignment</p>
                                    <template x-if="permDrawer.loadingMachines">
                                        <p style="font-size:0.875rem;color:#9ca3af">Loading machines…</p>
                                    </template>
                                    <template x-if="!permDrawer.loadingMachines">
                                        <select x-model="permDrawer.machineId"
                                                style="width:100%;border-radius:0.5rem;border:1px solid #d1d5db;padding:0.5rem 0.75rem;font-size:0.875rem;outline:none">
                                            <option value="">— No machine assigned —</option>
                                            <template x-for="m in permDrawer.machines" :key="m.id">
                                                <option :value="m.id" x-text="m.name + (m.code ? ' (' + m.code + ')' : '')"></option>
                                            </template>
                                        </select>
                                    </template>
                                </div>
                            </template>

                            {{-- Legend --}}
                            <div style="display:flex;align-items:center;gap:1.25rem;font-size:0.75rem;color:#6b7280">
                                <span style="display:flex;align-items:center;gap:0.375rem">
                                    <span style="display:inline-block;width:0.875rem;height:0.875rem;border-radius:0.25rem;border:2px solid #d1d5db;background:#f3f4f6"></span>
                                    Via role (read-only)
                                </span>
                                <span style="display:flex;align-items:center;gap:0.375rem">
                                    <span style="display:inline-block;width:0.875rem;height:0.875rem;border-radius:0.25rem;border:2px solid #7c3aed;background:#7c3aed"></span>
                                    Direct grant
                                </span>
                            </div>

                            {{-- Permission groups --}}
                            <template x-for="group in permGroups" :key="group.label">
                                <div>
                                    <p style="margin-bottom:0.5rem;font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:#6b7280"
                                       x-text="group.label"></p>
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.125rem 1rem">
                                        <template x-for="perm in group.permissions" :key="perm.value">
                                            <label style="display:flex;align-items:center;gap:0.5rem;border-radius:0.375rem;padding:0.25rem 0.5rem;transition:background .1s"
                                                   :style="isRolePerm(perm.value) ? 'opacity:.6;cursor:not-allowed' : 'cursor:pointer'"
                                                   onmouseover="if(!this.querySelector('input').disabled) this.style.background='#f5f3ff'"
                                                   onmouseout="this.style.background=''">
                                                <input type="checkbox"
                                                       :checked="isRolePerm(perm.value) || isDirectPerm(perm.value)"
                                                       :disabled="isRolePerm(perm.value)"
                                                       @change="!isRolePerm(perm.value) && togglePerm(perm.value)"
                                                       style="width:0.875rem;height:0.875rem;border-radius:0.25rem;accent-color:#7c3aed;flex-shrink:0">
                                                <span style="font-size:0.75rem;color:#374151;line-height:1.25" x-text="perm.label"></span>
                                            </label>
                                        </template>
                                    </div>
                                </div>
                            </template>

                        </div>
                    </div>
                </template>

                {{-- Footer — always visible --}}
                <div style="display:flex;align-items:center;justify-content:space-between;border-top:1px solid #e5e7eb;padding:1rem 1.5rem;flex-shrink:0">
                    <p style="font-size:0.75rem;color:#9ca3af">Role + permissions saved together.</p>
                    <div style="display:flex;align-items:center;gap:0.75rem">
                        <button @click="permDrawer.open = false"
                                style="border-radius:0.5rem;padding:0.5rem 1rem;font-size:0.875rem;color:#4b5563;cursor:pointer;background:none;border:1px solid #e5e7eb"
                                onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='none'">
                            Cancel
                        </button>
                        <button @click="savePermissions()"
                                :disabled="permDrawer.saving"
                                style="display:inline-flex;align-items:center;gap:0.5rem;border-radius:0.5rem;padding:0.5rem 1.25rem;font-size:0.875rem;font-weight:500;color:#fff;background:#7c3aed;border:none;cursor:pointer;transition:background .15s"
                                onmouseover="if(!this.disabled) this.style.background='#6d28d9'" onmouseout="this.style.background='#7c3aed'"
                                :style="permDrawer.saving ? 'opacity:.6;cursor:not-allowed' : ''">
                            <svg x-show="permDrawer.saving" style="width:1rem;height:1rem;animation:spin 1s linear infinite" fill="none" viewBox="0 0 24 24">
                                <circle style="opacity:.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path style="opacity:.75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <span x-text="permDrawer.saving ? 'Saving…' : 'Save Changes'">Save Changes</span>
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </template>{{-- end permissions modal --}}

</div>{{-- end x-data --}}

@endsection

@push('scripts')
<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>
<script>
function userManager(apiToken, isSuperAdmin, factories, permissionGroups) {
    return {
        users:      [],
        pagination: {},
        loading:    false,
        canCreate:  false,
        flash:      { type: '', message: '' },
        factories:  factories || [],
        permGroups: permissionGroups || [],

        createModal: {
            open: false, saving: false, errors: {},
            form: { name: '', email: '', password: '', factory_id: '', is_active: true },
        },
        editModal: {
            open: false, saving: false, errors: {}, user: null,
            form: { name: '', email: '', password: '', factory_id: '', is_active: true },
        },
        roleModal: {
            open: false, saving: false, user: null, error: null,
            assignableRoles: [], loadingRoles: false,
            selectedRole: '',
            machines: [], loadingMachines: false, machineId: '',
        },
machineModal: {
            open: false, saving: false, user: null,
            machines: [], loadingMachines: false, selectedMachineId: '',
        },
        permDrawer: {
            open: false, loading: false, saving: false, user: null,
            rolePerms: [], directPerms: [],
            machineId: '', machines: [], loadingMachines: false,
            selectedRole: '', assignableRoles: [], loadingRoles: false,
        },

        init() { this.loadUsers(1); },

        get headers() {
            return {
                'Accept':        'application/json',
                'Content-Type':  'application/json',
                'X-CSRF-TOKEN':  document.querySelector('meta[name="csrf-token"]').content,
                'Authorization': apiToken ? `Bearer ${apiToken}` : '',
            };
        },

        async loadUsers(page = 1) {
            this.loading = true;
            try {
                const res  = await fetch(`/admin/users?page=${page}`, { headers: this.headers });
                const data = await res.json();
                this.users      = data.data;
                this.canCreate  = data.can_create ?? false;
                this.pagination = {
                    current_page: data.current_page,
                    last_page:    data.last_page,
                    total:        data.total,
                };
            } catch (e) {
                this.setFlash('error', 'Failed to load users.');
            } finally {
                this.loading = false;
            }
        },

        changePage(page) {
            if (page < 1 || page > this.pagination.last_page) return;
            this.loadUsers(page);
        },

        openCreate() {
            this.createModal = {
                open: true, saving: false, errors: {},
                form: { name: '', email: '', password: '', factory_id: '', is_active: true },
            };
        },

        async submitCreate() {
            this.createModal.saving = true;
            this.createModal.errors = {};
            try {
                const res  = await fetch('/admin/users', {
                    method:  'POST',
                    headers: this.headers,
                    body:    JSON.stringify(this.createModal.form),
                });
                const data = await res.json();
                if (res.status === 422) {
                    this.createModal.errors = data.errors ?? {};
                } else if (!res.ok) {
                    this.setFlash('error', data.message ?? 'Failed to create user.');
                } else {
                    this.setFlash('success', data.message);
                    this.createModal.open = false;
                    this.loadUsers(1);
                }
            } catch (e) {
                this.setFlash('error', 'Network error. Please retry.');
            } finally {
                this.createModal.saving = false;
            }
        },

        openEdit(user) {
            this.editModal = {
                open: true, saving: false, errors: {}, user,
                form: {
                    name:       user.name,
                    email:      user.email,
                    password:   '',
                    factory_id: user.factory_id ?? '',
                    is_active:  user.is_active,
                },
            };
        },

        async submitEdit() {
            this.editModal.saving = true;
            this.editModal.errors = {};
            try {
                const res  = await fetch(`/admin/users/${this.editModal.user.id}`, {
                    method:  'PUT',
                    headers: this.headers,
                    body:    JSON.stringify(this.editModal.form),
                });
                const data = await res.json();
                if (res.status === 422) {
                    this.editModal.errors = data.errors ?? {};
                } else if (!res.ok) {
                    this.setFlash('error', data.message ?? 'Failed to update user.');
                } else {
                    this.setFlash('success', data.message);
                    this.editModal.open = false;
                    const idx = this.users.findIndex(u => u.id === data.data.id);
                    if (idx !== -1) this.users.splice(idx, 1, data.data);
                }
            } catch (e) {
                this.setFlash('error', 'Network error. Please retry.');
            } finally {
                this.editModal.saving = false;
            }
        },

        async revokeRole(user) {
            if (!confirm(`Revoke role from ${user.name}?`)) return;
            try {
                const res  = await fetch(`/admin/users/${user.id}/revoke-role`, {
                    method:  'DELETE',
                    headers: this.headers,
                });
                const data = await res.json();
                if (!res.ok) {
                    this.setFlash('error', data.message ?? 'Failed to revoke role.');
                } else {
                    this.setFlash('success', data.message);
                    this.loadUsers(this.pagination.current_page);
                }
            } catch (e) {
                this.setFlash('error', 'Network error. Please retry.');
            }
        },

        async openRoleModal(user) {
            this.roleModal = {
                open: true, saving: false, user,
                assignableRoles: [], loadingRoles: true,
                selectedRole: user.role ?? '',
                machines: [], loadingMachines: false, machineId: user.machine_id ?? '',
                error: null,
            };
            try {
                const res  = await fetch(`/admin/users/${user.id}`, { headers: this.headers });
                const data = await res.json();
                this.roleModal.assignableRoles = data.data?.assignable_roles ?? [];
            } catch (e) {
                this.roleModal.error = 'Could not load assignable roles.';
            } finally {
                this.roleModal.loadingRoles = false;
            }
            // Auto-load machines if current role is operator/viewer
            if (this.roleModal.selectedRole === 'operator' || this.roleModal.selectedRole === 'viewer') {
                await this.loadRoleModalMachines(user);
            }
        },

        async onRoleModalRoleChange() {
            const role = this.roleModal.selectedRole;
            if (role === 'operator' || role === 'viewer') {
                await this.loadRoleModalMachines(this.roleModal.user);
            } else {
                this.roleModal.machines = [];
            }
        },

        async loadRoleModalMachines(user) {
            this.roleModal.loadingMachines = true;
            try {
                const factoryParam = user.factory_id ? `&factory_id=${user.factory_id}` : '';
                const res  = await fetch(`/api/v1/machines?per_page=200&status=active${factoryParam}`, { headers: this.headers });
                const data = await res.json();
                this.roleModal.machines = data.data ?? [];
            } catch (e) { /* non-fatal */ }
            finally { this.roleModal.loadingMachines = false; }
        },

        async submitRoleAssign() {
            this.roleModal.saving = true;
            this.roleModal.error  = null;
            try {
                const u        = this.roleModal.user;
                const newRole  = this.roleModal.selectedRole;
                const origRole = u.role ?? '';

                // 1. Role assignment / revocation
                if (newRole !== origRole) {
                    if (newRole) {
                        const r = await fetch(`/admin/users/${u.id}/assign-role`, {
                            method:  'POST',
                            headers: this.headers,
                            body:    JSON.stringify({ role: newRole }),
                        });
                        if (!r.ok) {
                            const d = await r.json();
                            this.roleModal.error = d.message ?? 'Failed to assign role.';
                            return;
                        }
                    } else {
                        const r = await fetch(`/admin/users/${u.id}/revoke-role`, {
                            method: 'DELETE', headers: this.headers,
                        });
                        if (!r.ok) {
                            const d = await r.json();
                            this.roleModal.error = d.message ?? 'Failed to revoke role.';
                            return;
                        }
                    }
                }

                // 2. Machine assignment for operator/viewer
                if (newRole === 'operator' || newRole === 'viewer') {
                    await fetch(`/admin/users/${u.id}/assign-machine`, {
                        method:  'POST',
                        headers: this.headers,
                        body:    JSON.stringify({ machine_id: this.roleModal.machineId || null }),
                    });
                }

                // 3. Update user row in-place (no full reload needed)
                const idx = this.users.findIndex(uu => uu.id === u.id);
                if (idx !== -1) {
                    const roleLabel = newRole
                        ? (this.roleModal.assignableRoles.find(r => r.value === newRole)?.label ?? newRole)
                        : null;
                    this.users[idx].role       = newRole || null;
                    this.users[idx].role_label = roleLabel;
                    if (newRole === 'operator' || newRole === 'viewer') {
                        this.users[idx].machine_id = this.roleModal.machineId || null;
                    }
                }

                this.roleModal.open = false;
                this.setFlash('success', newRole
                    ? `Role assigned to ${u.name}.`
                    : `Role revoked from ${u.name}.`);
            } catch (e) {
                this.roleModal.error = 'Network error. Please retry.';
            } finally {
                this.roleModal.saving = false;
            }
        },

        async openMachineModal(user) {
            this.machineModal = {
                open: true, saving: false, user,
                machines: [], loadingMachines: true,
                selectedMachineId: user.machine_id ?? '',
            };
            try {
                const factoryParam = user.factory_id ? `&factory_id=${user.factory_id}` : '';
                const res  = await fetch(`/api/v1/machines?per_page=200&status=active${factoryParam}`, { headers: this.headers });
                const data = await res.json();
                this.machineModal.machines = data.data ?? [];
            } catch (e) {
                this.setFlash('error', 'Could not load machines.');
                this.machineModal.open = false;
            } finally {
                this.machineModal.loadingMachines = false;
            }
        },

        async submitMachineAssign() {
            this.machineModal.saving = true;
            try {
                const body = { machine_id: this.machineModal.selectedMachineId || null };
                const res  = await fetch(`/admin/users/${this.machineModal.user.id}/assign-machine`, {
                    method:  'POST',
                    headers: this.headers,
                    body:    JSON.stringify(body),
                });
                const data = await res.json();
                if (!res.ok) {
                    this.setFlash('error', data.message ?? 'Failed to assign machine.');
                } else {
                    this.setFlash('success', data.message);
                    this.machineModal.open = false;
                    // Update user row in place
                    const idx = this.users.findIndex(u => u.id === this.machineModal.user.id);
                    if (idx !== -1) this.users[idx].machine_id = data.machine_id;
                }
            } catch (e) {
                this.setFlash('error', 'Network error. Please retry.');
            } finally {
                this.machineModal.saving = false;
            }
        },

        async openPermDrawer(user) {
            this.permDrawer = {
                open: true, loading: true, saving: false, user,
                rolePerms: [], directPerms: [],
                machineId: user.machine_id ?? '',
                machines: [], loadingMachines: false,
                selectedRole: user.role ?? '', assignableRoles: [], loadingRoles: true,
            };
            // Load permissions and assignable roles in parallel
            try {
                const [permRes, userRes] = await Promise.all([
                    fetch(`/admin/users/${user.id}/permissions`, { headers: this.headers }),
                    fetch(`/admin/users/${user.id}`, { headers: this.headers }),
                ]);
                const permData = await permRes.json();
                const userData = await userRes.json();
                this.permDrawer.rolePerms       = permData.role_permissions   ?? [];
                this.permDrawer.directPerms     = permData.direct_permissions ?? [];
                this.permDrawer.assignableRoles = userData.data?.assignable_roles ?? [];
            } catch (e) {
                this.setFlash('error', 'Could not load user data.');
                this.permDrawer.open = false;
                return;
            } finally {
                this.permDrawer.loading     = false;
                this.permDrawer.loadingRoles = false;
            }
            // Load machines if user is operator/viewer
            const role = this.permDrawer.selectedRole;
            if (role === 'operator' || role === 'viewer') {
                this.permDrawer.loadingMachines = true;
                try {
                    const factoryParam = user.factory_id ? `&factory_id=${user.factory_id}` : '';
                    const res  = await fetch(`/api/v1/machines?per_page=200&status=active${factoryParam}`, { headers: this.headers });
                    const data = await res.json();
                    this.permDrawer.machines = data.data ?? [];
                } catch (e) { /* non-fatal */ }
                finally { this.permDrawer.loadingMachines = false; }
            }
        },

        isRolePerm(value)   { return this.permDrawer.rolePerms.includes(value); },
        isDirectPerm(value) { return this.permDrawer.directPerms.includes(value); },

        togglePerm(value) {
            const idx = this.permDrawer.directPerms.indexOf(value);
            if (idx === -1) this.permDrawer.directPerms.push(value);
            else            this.permDrawer.directPerms.splice(idx, 1);
        },

        async savePermissions() {
            this.permDrawer.saving = true;
            try {
                const u           = this.permDrawer.user;
                const origRole    = u.role ?? '';
                const newRole     = this.permDrawer.selectedRole;

                // 1. Role change
                if (newRole !== origRole) {
                    if (newRole) {
                        const r = await fetch(`/admin/users/${u.id}/assign-role`, {
                            method:  'POST',
                            headers: this.headers,
                            body:    JSON.stringify({ role: newRole }),
                        });
                        if (!r.ok) {
                            const d = await r.json();
                            this.setFlash('error', d.message ?? 'Failed to assign role.');
                            return;
                        }
                    } else {
                        await fetch(`/admin/users/${u.id}/revoke-role`, {
                            method: 'DELETE', headers: this.headers,
                        });
                    }
                }

                // 2. Machine assignment (only for operator/viewer final role)
                if (newRole === 'operator' || newRole === 'viewer') {
                    await fetch(`/admin/users/${u.id}/assign-machine`, {
                        method:  'POST',
                        headers: this.headers,
                        body:    JSON.stringify({ machine_id: this.permDrawer.machineId || null }),
                    });
                }

                // 3. Direct permissions
                const res  = await fetch(`/admin/users/${u.id}/sync-permissions`, {
                    method:  'POST',
                    headers: this.headers,
                    body:    JSON.stringify({ permissions: this.permDrawer.directPerms }),
                });
                const data = await res.json();
                if (!res.ok) {
                    this.setFlash('error', data.message ?? 'Failed to save permissions.');
                } else {
                    this.setFlash('success', 'User updated successfully.');
                    // Update role badge in-place
                    const idx = this.users.findIndex(uu => uu.id === u.id);
                    if (idx !== -1) {
                        const newRole  = this.permDrawer.selectedRole;
                        const roleLabel = newRole
                            ? (this.permDrawer.assignableRoles.find(r => r.value === newRole)?.label ?? newRole)
                            : null;
                        this.users[idx].role       = newRole || null;
                        this.users[idx].role_label = roleLabel;
                    }
                    this.permDrawer.open = false;
                    this.loadUsers(this.pagination.current_page);
                }
            } catch (e) {
                this.setFlash('error', 'Network error. Please retry.');
            } finally {
                this.permDrawer.saving = false;
            }
        },

        setFlash(type, message) {
            this.flash = { type, message };
            setTimeout(() => this.flash = { type: '', message: '' }, 5000);
        },
    };
}
</script>
@endpush
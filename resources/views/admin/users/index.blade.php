@extends('admin.layouts.app')

@section('title', 'Users')

@section('content')

@php
    $isSuperAdmin = $factoryId === null;
    $factoriesJson = $factories->isNotEmpty() ? $factories->toJson() : '[]';
@endphp

<div x-data="userManager(
    {{ $apiToken ? json_encode($apiToken) : 'null' }},
    {{ $isSuperAdmin ? 'true' : 'false' }},
    {{ $factoriesJson }}
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
                                        <template x-if="user.can_reassign">
                                            <button @click="openAssign(user)"
                                                    class="rounded-lg bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100 transition-colors">
                                                Assign Role
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

    {{-- ASSIGN ROLE MODAL --}}
    <template x-if="assignModal.open">
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
             @click.self="assignModal.open = false">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900 mb-1">Assign Role</h3>
                <p class="text-sm text-gray-500 mb-4">
                    Assign a role to <strong x-text="assignModal.user?.name"></strong>
                </p>
                <template x-if="assignModal.assignableRoles.length === 0">
                    <p class="text-sm text-gray-400 italic">Loading roles…</p>
                </template>
                <div class="space-y-2 mb-5">
                    <template x-for="r in assignModal.assignableRoles" :key="r.value">
                        <label class="flex items-center gap-3 cursor-pointer rounded-lg border border-gray-200 px-3 py-2.5 hover:border-indigo-300 hover:bg-indigo-50 transition-colors"
                               :class="assignModal.selectedRole === r.value ? 'border-indigo-400 bg-indigo-50' : ''">
                            <input type="radio" :value="r.value" x-model="assignModal.selectedRole"
                                   class="text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm font-medium text-gray-800" x-text="r.label"></span>
                        </label>
                    </template>
                </div>
                <div class="flex items-center gap-3 justify-end">
                    <button @click="assignModal.open = false"
                            class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 transition-colors">Cancel</button>
                    <button @click="submitAssign()"
                            :disabled="!assignModal.selectedRole || assignModal.saving"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50 transition-colors">
                        <span x-show="!assignModal.saving">Assign</span>
                        <span x-show="assignModal.saving">Saving…</span>
                    </button>
                </div>
            </div>
        </div>
    </template>

</div>{{-- end x-data --}}

@endsection

@push('scripts')
<script>
function userManager(apiToken, isSuperAdmin, factories) {
    return {
        users:      [],
        pagination: {},
        loading:    false,
        canCreate:  false,
        flash:      { type: '', message: '' },
        factories:  factories || [],

        createModal: {
            open: false, saving: false, errors: {},
            form: { name: '', email: '', password: '', factory_id: '', is_active: true },
        },
        editModal: {
            open: false, saving: false, errors: {}, user: null,
            form: { name: '', email: '', password: '', factory_id: '', is_active: true },
        },
        assignModal: {
            open: false, saving: false, user: null,
            assignableRoles: [], selectedRole: null,
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

        async openAssign(user) {
            this.assignModal = { open: true, user, assignableRoles: [], selectedRole: user.role ?? null, saving: false };
            try {
                const res  = await fetch(`/admin/users/${user.id}`, { headers: this.headers });
                const data = await res.json();
                this.assignModal.assignableRoles = data.data.assignable_roles ?? [];
            } catch (e) {
                this.setFlash('error', 'Could not load assignable roles.');
                this.assignModal.open = false;
            }
        },

        async submitAssign() {
            if (!this.assignModal.selectedRole) return;
            this.assignModal.saving = true;
            try {
                const res  = await fetch(`/admin/users/${this.assignModal.user.id}/assign-role`, {
                    method:  'POST',
                    headers: this.headers,
                    body:    JSON.stringify({ role: this.assignModal.selectedRole }),
                });
                const data = await res.json();
                if (!res.ok) {
                    this.setFlash('error', data.message ?? 'Failed to assign role.');
                } else {
                    this.setFlash('success', data.message);
                    this.assignModal.open = false;
                    this.loadUsers(this.pagination.current_page);
                }
            } catch (e) {
                this.setFlash('error', 'Network error. Please retry.');
            } finally {
                this.assignModal.saving = false;
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

        setFlash(type, message) {
            this.flash = { type, message };
            setTimeout(() => this.flash = { type: '', message: '' }, 5000);
        },
    };
}
</script>
@endpush
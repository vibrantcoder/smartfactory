@extends('admin.layouts.app')

@section('title', 'Roles')

@php
    $summariesJson = json_encode($summaries ?? []);
    $canCreate     = json_encode($canCreate ?? false);
@endphp

@section('content')
<div
    x-data="rolesManager({{ $apiToken ? json_encode($apiToken) : 'null' }}, {{ $summariesJson }}, {{ $canCreate }})"
    x-cloak
>

    {{-- ── Header ─────────────────────────────────────────── --}}
    <div class="mb-5 flex items-center justify-between">
        <div>
            <p class="text-sm text-gray-500">Manage roles and their permission sets.</p>
        </div>
        <button
            x-show="canCreate"
            @click="showCreate = true; newName = ''; createError = null"
            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition-colors"
        >
            + New Role
        </button>
    </div>

    {{-- ── Role Table ──────────────────────────────────────── --}}
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="border-b border-gray-100 bg-gray-50">
                <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <th class="px-5 py-3">Role</th>
                    <th class="px-5 py-3">Description</th>
                    <th class="px-5 py-3 text-center">Level</th>
                    <th class="px-5 py-3 text-center">Scope</th>
                    <th class="px-5 py-3 text-center">Permissions</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <template x-for="role in roles" :key="role.id">
                    <tr class="hover:bg-gray-50 transition-colors">

                        {{-- Name --}}
                        <td class="px-5 py-3">
                            <span class="font-medium text-gray-900" x-text="role.label"></span>
                            <p class="mt-0.5 font-mono text-xs text-gray-400" x-text="role.name"></p>
                        </td>

                        {{-- Description --}}
                        <td class="px-5 py-3 text-gray-600 max-w-xs text-xs" x-text="role.description ?? '—'"></td>

                        {{-- Level --}}
                        <td class="px-5 py-3 text-center">
                            <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700"
                                  x-text="role.level"></span>
                        </td>

                        {{-- Scope --}}
                        <td class="px-5 py-3 text-center">
                            <template x-if="role.is_factory_scoped">
                                <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">Factory</span>
                            </template>
                            <template x-if="!role.is_factory_scoped">
                                <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-500">Global</span>
                            </template>
                        </td>

                        {{-- Permission count --}}
                        <td class="px-5 py-3 text-center">
                            <template x-if="role.name === 'super-admin'">
                                <span class="rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-700">All (bypass)</span>
                            </template>
                            <template x-if="role.name !== 'super-admin'">
                                <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700"
                                      x-text="role.permission_count"></span>
                            </template>
                        </td>

                        {{-- Actions --}}
                        <td class="px-5 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button
                                    @click="openDrawer(role)"
                                    class="rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100 transition-colors"
                                >
                                    Edit Permissions
                                </button>

                                {{-- Delete (custom roles only, two-click confirm) --}}
                                <template x-if="!role.is_system && canCreate">
                                    <button
                                        @click="confirmDelete(role)"
                                        :class="deletingId === role.id
                                            ? 'bg-red-600 text-white'
                                            : 'bg-red-50 text-red-700 hover:bg-red-100'"
                                        class="rounded-lg px-3 py-1.5 text-xs font-medium transition-colors"
                                        x-text="deletingId === role.id ? 'Confirm?' : 'Delete'"
                                    ></button>
                                </template>
                                <template x-if="deletingId === role.id">
                                    <button @click="deletingId = null"
                                            class="text-xs text-gray-400 hover:text-gray-600">Cancel</button>
                                </template>
                            </div>
                        </td>
                    </tr>
                </template>

                <template x-if="roles.length === 0">
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-sm text-gray-400">No roles found.</td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    {{-- ── Create Role Modal ────────────────────────────────── --}}
    <template x-if="showCreate">
        <div class="fixed inset-0 z-40 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/40" @click="showCreate = false"></div>
            <div class="relative z-50 w-full max-w-md rounded-xl bg-white p-6 shadow-xl"
                 @click.stop>
                <h3 class="mb-4 text-base font-semibold text-gray-900">New Role</h3>

                <div class="mb-1">
                    <label class="mb-1 block text-xs font-medium text-gray-700">Role slug
                        <span class="font-normal text-gray-400">(lowercase, letters, digits, hyphens)</span>
                    </label>
                    <input
                        type="text"
                        x-model="newName"
                        @keydown.enter="createRole"
                        placeholder="e.g. quality-inspector"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                    >
                </div>
                <p x-show="createError" x-text="createError"
                   class="mb-3 text-xs text-red-600"></p>

                <div class="mt-4 flex justify-end gap-3">
                    <button @click="showCreate = false"
                            class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100">
                        Cancel
                    </button>
                    <button @click="createRole"
                            :disabled="creating || !newName.trim()"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                        <span x-show="!creating">Create Role</span>
                        <span x-show="creating">Creating…</span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{-- ── Permission Drawer ────────────────────────────────── --}}

    {{-- Backdrop --}}
    <div
        x-show="drawer.open"
        x-transition:enter="transition-opacity ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-30 bg-black/30"
        @click="drawer.open = false"
    ></div>

    {{-- Panel --}}
    <div
        x-show="drawer.open"
        x-transition:enter="transition ease-out duration-250 transform"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-200 transform"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="fixed inset-y-0 right-0 z-40 flex w-full max-w-2xl flex-col bg-white shadow-2xl"
        @click.stop
    >
        {{-- Drawer header --}}
        <div class="flex shrink-0 items-center justify-between border-b border-gray-200 px-6 py-4">
            <div>
                <h3 class="text-base font-semibold text-gray-900">
                    Permissions —
                    <span x-text="drawer.role?.label ?? ''"></span>
                </h3>
                <p class="mt-0.5 font-mono text-xs text-gray-400" x-text="drawer.role?.name ?? ''"></p>
            </div>
            <button @click="drawer.open = false"
                    class="rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Super-admin notice --}}
        <div x-show="drawer.role?.name === 'super-admin'"
             class="shrink-0 border-b border-purple-100 bg-purple-50 px-6 py-3 text-xs text-purple-700">
            Super Admin permissions are managed via Gate bypass — explicit assignments are not used.
        </div>

        {{-- Flash --}}
        <div x-show="drawer.flash.message"
             x-transition
             class="shrink-0 px-6 py-2 text-xs font-medium"
             :class="drawer.flash.type === 'success'
                ? 'bg-green-50 text-green-800 border-b border-green-200'
                : 'bg-red-50 text-red-800 border-b border-red-200'"
             x-text="drawer.flash.message">
        </div>

        {{-- Loading state --}}
        <div x-show="drawer.loading"
             class="flex flex-1 items-center justify-center text-sm text-gray-400">
            Loading permissions…
        </div>

        {{-- Matrix body (scrollable) --}}
        <div x-show="!drawer.loading" class="flex-1 overflow-y-auto">

            {{-- Bulk controls (only for non-super-admin) --}}
            <div x-show="drawer.role?.name !== 'super-admin'"
                 class="sticky top-0 z-10 flex items-center gap-4 border-b border-gray-100 bg-white px-6 py-2">
                <span class="text-xs text-gray-500">
                    <span x-text="totalAssigned()"></span> of
                    <span x-text="drawer.matrix.reduce((s,g) => s + g.permissions.length, 0)"></span>
                    assigned
                </span>
                <button @click="selectAll()"
                        class="text-xs text-indigo-600 hover:underline">Select all</button>
                <button @click="deselectAll()"
                        class="text-xs text-red-500 hover:underline">Deselect all</button>
            </div>

            <div class="px-6 py-4 space-y-6">
                <template x-for="group in drawer.matrix" :key="group.group_key">
                    <div>
                        {{-- Group header --}}
                        <div class="mb-2 flex items-center justify-between">
                            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500"
                                x-text="group.group_label"></h4>
                            <div x-show="drawer.role?.name !== 'super-admin'" class="flex gap-2">
                                <button @click="toggleGroup(group, true)"
                                        class="text-xs text-indigo-500 hover:underline">All</button>
                                <span class="text-gray-300">|</span>
                                <button @click="toggleGroup(group, false)"
                                        class="text-xs text-red-400 hover:underline">None</button>
                            </div>
                        </div>
                        {{-- Checkboxes --}}
                        <div class="flex flex-wrap gap-2">
                            <template x-for="perm in group.permissions" :key="perm.name">
                                <label
                                    class="flex cursor-pointer items-center gap-1.5 rounded-md px-2 py-1"
                                    :class="perm.assigned ? 'bg-indigo-50 text-indigo-800' : 'bg-gray-100 text-gray-600'"
                                >
                                    <input
                                        type="checkbox"
                                        x-model="perm.assigned"
                                        @change="markDirty()"
                                        :disabled="drawer.role?.name === 'super-admin'"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-40"
                                    >
                                    <span x-text="perm.label" class="select-none text-xs"></span>
                                </label>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Drawer footer --}}
        <div x-show="drawer.role?.name !== 'super-admin'"
             class="shrink-0 border-t border-gray-200 bg-gray-50 px-6 py-4 flex items-center justify-between">
            <span x-show="drawer.dirty"
                  class="text-xs font-medium text-amber-600">Unsaved changes</span>
            <span x-show="!drawer.dirty" class="text-xs text-gray-400">No changes</span>
            <button
                @click="savePermissions"
                :disabled="!drawer.dirty || drawer.saving"
                :class="drawer.dirty ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-gray-300 cursor-not-allowed'"
                class="rounded-lg px-4 py-2 text-sm font-medium text-white transition-colors disabled:opacity-60"
            >
                <span x-show="!drawer.saving">Save Permissions</span>
                <span x-show="drawer.saving">Saving…</span>
            </button>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function rolesManager(apiToken, initialRoles, canCreate) {
    return {
        roles: initialRoles ?? [],
        canCreate: canCreate ?? false,

        // Create modal
        showCreate: false,
        newName:    '',
        createError: null,
        creating:   false,

        // Delete confirm
        deletingId: null,

        // Permission drawer
        drawer: {
            open:     false,
            role:     null,
            matrix:   [],
            original: [],
            loading:  false,
            saving:   false,
            dirty:    false,
            flash:    { type: '', message: '' },
        },

        get headers() {
            return {
                'Accept':        'application/json',
                'Content-Type':  'application/json',
                'X-CSRF-TOKEN':  document.querySelector('meta[name="csrf-token"]').content,
                'Authorization': 'Bearer ' + apiToken,
            };
        },

        // ── Create ─────────────────────────────────────────

        async createRole() {
            if (!this.newName.trim()) return;
            this.creating    = true;
            this.createError = null;
            try {
                const res  = await fetch('/admin/roles', {
                    method: 'POST', headers: this.headers,
                    body:   JSON.stringify({ name: this.newName.trim() }),
                });
                const json = await res.json();
                if (!res.ok) {
                    this.createError = json.message
                        ?? (json.errors ? Object.values(json.errors)[0][0] : 'Failed to create role.');
                    return;
                }
                this.roles.push(json.data);
                this.roles.sort((a, b) => b.level - a.level || a.name.localeCompare(b.name));
                this.showCreate = false;
                this.newName    = '';
            } catch (e) {
                this.createError = 'Network error.';
            } finally {
                this.creating = false;
            }
        },

        // ── Delete (two-click confirm) ─────────────────────

        async confirmDelete(role) {
            if (this.deletingId !== role.id) {
                this.deletingId = role.id;
                return;
            }
            try {
                const res  = await fetch('/admin/roles/' + role.id, {
                    method: 'DELETE', headers: this.headers,
                });
                const json = await res.json();
                if (!res.ok) {
                    alert(json.message ?? 'Delete failed.');
                    this.deletingId = null;
                    return;
                }
                this.roles      = this.roles.filter(r => r.id !== role.id);
                this.deletingId = null;
                if (this.drawer.role?.id === role.id) this.drawer.open = false;
            } catch (e) {
                alert('Network error.');
                this.deletingId = null;
            }
        },

        // ── Permission Drawer ──────────────────────────────

        async openDrawer(role) {
            this.drawer.role    = role;
            this.drawer.open    = true;
            this.drawer.loading = true;
            this.drawer.matrix  = [];
            this.drawer.dirty   = false;
            this.drawer.flash   = { type: '', message: '' };
            try {
                const res  = await fetch('/admin/roles/' + role.id + '/matrix', {
                    headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + apiToken },
                });
                const json = await res.json();
                this.drawer.matrix   = JSON.parse(JSON.stringify(json.data));
                this.drawer.original = JSON.parse(JSON.stringify(json.data));
            } finally {
                this.drawer.loading = false;
            }
        },

        toggleGroup(group, val) {
            group.permissions.forEach(p => p.assigned = val);
            this.drawer.dirty = true;
        },

        selectAll() {
            this.drawer.matrix.forEach(g => g.permissions.forEach(p => p.assigned = true));
            this.drawer.dirty = true;
        },

        deselectAll() {
            this.drawer.matrix.forEach(g => g.permissions.forEach(p => p.assigned = false));
            this.drawer.dirty = true;
        },

        countAssigned(group) {
            return group.permissions.filter(p => p.assigned).length;
        },

        totalAssigned() {
            return this.drawer.matrix.reduce((sum, g) => sum + this.countAssigned(g), 0);
        },

        markDirty() {
            this.drawer.dirty = true;
        },

        async savePermissions() {
            if (!this.drawer.role || this.drawer.role.name === 'super-admin') return;
            this.drawer.saving = true;
            this.drawer.flash  = { type: '', message: '' };
            try {
                const perms = this.drawer.matrix
                    .flatMap(g => g.permissions.filter(p => p.assigned).map(p => p.name));
                const res  = await fetch('/admin/roles/' + this.drawer.role.id + '/permissions', {
                    method: 'POST', headers: this.headers,
                    body:   JSON.stringify({ permissions: perms }),
                });
                const json = await res.json();
                if (!res.ok) {
                    this.drawer.flash = { type: 'error', message: json.message ?? 'Save failed.' };
                    return;
                }
                // Update matrix snapshot
                this.drawer.matrix   = json.permission_matrix;
                this.drawer.original = JSON.parse(JSON.stringify(json.permission_matrix));
                this.drawer.dirty    = false;
                this.drawer.flash    = { type: 'success', message: json.message };
                // Update permission count in the table row
                const idx = this.roles.findIndex(r => r.id === this.drawer.role.id);
                if (idx !== -1) this.roles[idx].permission_count = json.permission_count;
                setTimeout(() => { this.drawer.flash = { type: '', message: '' }; }, 4000);
            } catch (e) {
                this.drawer.flash = { type: 'error', message: 'Network error.' };
            } finally {
                this.drawer.saving = false;
            }
        },
    };
}
</script>
@endpush

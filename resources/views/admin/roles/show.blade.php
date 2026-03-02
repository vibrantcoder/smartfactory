{{--
  Permission Matrix — Blade/Alpine.js Checkbox UI
  Route: GET /admin/roles/{role}
  Data:  $role (Spatie Role), $matrix (array from PermissionService), $roleEnum (Role enum)

  FEATURES:
    - Grouped by resource (factory_management, machine_management, etc.)
    - Select All / Deselect All per group row
    - Column-level Select All (e.g., select all "create" permissions)
    - Dirty-state detection (shows Save button only when changed)
    - Disabled for super-admin role (cannot set explicit permissions)
    - AJAX submit via fetch API → POST /admin/roles/{role}/permissions
--}}

@extends('admin.layouts.app')

@section('title', "Permissions — {$roleEnum->label()}")

@section('content')
<div
    x-data="permissionMatrix({{ json_encode($matrix) }}, {{ json_encode($role->id) }})"
    x-init="init()"
    class="max-w-7xl mx-auto px-4 py-8"
>
    {{-- ── Header ─────────────────────────────────────────── --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $roleEnum->label() }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $roleEnum->description() }}</p>
        </div>

        <div class="flex items-center gap-3">
            {{-- Dirty indicator --}}
            <span x-show="isDirty" class="text-amber-600 text-sm font-medium">
                ● Unsaved changes
            </span>

            @if($role->name !== \App\Domain\Shared\Enums\Role::SUPER_ADMIN->value)
                <button
                    @click="save()"
                    :disabled="!isDirty || saving"
                    :class="isDirty ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-gray-300 cursor-not-allowed'"
                    class="px-4 py-2 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-60"
                >
                    <span x-show="!saving">Save Permissions</span>
                    <span x-show="saving">Saving...</span>
                </button>
            @else
                <span class="text-xs text-gray-400 italic">Super Admin permissions are managed by Gate bypass</span>
            @endif
        </div>
    </div>

    {{-- ── Success / Error Flash ───────────────────────────── --}}
    <div x-show="flash.message" x-transition class="mb-4 p-3 rounded-lg"
         :class="flash.type === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200'">
        <span x-text="flash.message"></span>
    </div>

    {{-- ── Permission Matrix Table ─────────────────────────── --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="text-left px-4 py-3 font-semibold text-gray-700 w-48">Group</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-700">Permissions</th>
                    <th class="text-center px-4 py-3 font-semibold text-gray-700 w-28">
                        <button @click="selectAll()" class="text-indigo-600 hover:underline text-xs">All</button>
                        /
                        <button @click="deselectAll()" class="text-red-500 hover:underline text-xs">None</button>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <template x-for="group in matrix" :key="group.group_key">
                    <tr class="hover:bg-gray-50">
                        {{-- Group label + bulk toggle --}}
                        <td class="px-4 py-3 font-medium text-gray-800 align-top">
                            <div x-text="group.group_label"></div>
                            <div class="mt-1 flex gap-2">
                                <button
                                    @click="toggleGroup(group, true)"
                                    class="text-xs text-indigo-500 hover:underline"
                                >All</button>
                                <span class="text-gray-300">|</span>
                                <button
                                    @click="toggleGroup(group, false)"
                                    class="text-xs text-red-400 hover:underline"
                                >None</button>
                            </div>
                        </td>

                        {{-- Individual permission checkboxes --}}
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-2">
                                <template x-for="perm in group.permissions" :key="perm.name">
                                    <label
                                        class="flex items-center gap-1.5 px-2 py-1 rounded-md cursor-pointer"
                                        :class="perm.assigned ? 'bg-indigo-50 text-indigo-800' : 'bg-gray-100 text-gray-600'"
                                    >
                                        <input
                                            type="checkbox"
                                            :value="perm.name"
                                            x-model="perm.assigned"
                                            @change="markDirty()"
                                            :disabled="{{ $role->name === \App\Domain\Shared\Enums\Role::SUPER_ADMIN->value ? 'true' : 'false' }}"
                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-40"
                                        >
                                        <span x-text="perm.label" class="text-xs select-none"></span>
                                    </label>
                                </template>
                            </div>
                        </td>

                        {{-- Group permission count --}}
                        <td class="px-4 py-3 text-center text-xs text-gray-500 align-top">
                            <span x-text="countAssigned(group)"></span>
                            /
                            <span x-text="group.permissions.length"></span>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    {{-- ── Summary Bar ─────────────────────────────────────── --}}
    <div class="mt-4 flex items-center justify-between text-sm text-gray-500">
        <div>
            Total assigned: <strong x-text="totalAssigned()" class="text-gray-800"></strong>
            of <strong class="text-gray-800">{{ collect($matrix)->sum(fn($g) => count($g['permissions'])) }}</strong> permissions
        </div>
        <div x-show="isDirty" class="text-amber-600 text-xs">
            Changes will take effect on next user login or permission cache clear.
        </div>
    </div>
</div>

@push('scripts')
<script>
function permissionMatrix(initialMatrix, roleId) {
    return {
        matrix:    JSON.parse(JSON.stringify(initialMatrix)), // deep clone
        original:  JSON.parse(JSON.stringify(initialMatrix)), // reference for dirty check
        isDirty:   false,
        saving:    false,
        flash:     { type: '', message: '' },

        init() {
            // Watch for any checkbox change
            this.$watch('matrix', () => {
                this.isDirty = JSON.stringify(this.matrix) !== JSON.stringify(this.original);
            }, { deep: true });
        },

        markDirty() {
            this.isDirty = true;
        },

        // ── Bulk Operations ──────────────────────────────────

        toggleGroup(group, checked) {
            group.permissions.forEach(p => p.assigned = checked);
            this.markDirty();
        },

        selectAll() {
            this.matrix.forEach(g => g.permissions.forEach(p => p.assigned = true));
            this.markDirty();
        },

        deselectAll() {
            this.matrix.forEach(g => g.permissions.forEach(p => p.assigned = false));
            this.markDirty();
        },

        // ── Counts ──────────────────────────────────────────

        countAssigned(group) {
            return group.permissions.filter(p => p.assigned).length;
        },

        totalAssigned() {
            return this.matrix.reduce((sum, g) => sum + this.countAssigned(g), 0);
        },

        // ── Collect selected permission names ────────────────

        getSelectedPermissions() {
            const selected = [];
            this.matrix.forEach(group => {
                group.permissions.forEach(perm => {
                    if (perm.assigned) selected.push(perm.name);
                });
            });
            return selected;
        },

        // ── Save (AJAX POST) ─────────────────────────────────

        async save() {
            this.saving = true;
            this.flash  = { type: '', message: '' };

            try {
                const response = await fetch(
                    `/admin/roles/${roleId}/permissions`,
                    {
                        method:  'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept':       'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({
                            permissions: this.getSelectedPermissions(),
                        }),
                    }
                );

                const data = await response.json();

                if (!response.ok) {
                    this.flash = { type: 'error', message: data.message ?? 'Save failed.' };
                    return;
                }

                // Update original reference (resets dirty state)
                this.matrix   = data.permission_matrix;
                this.original = JSON.parse(JSON.stringify(data.permission_matrix));
                this.isDirty  = false;
                this.flash    = { type: 'success', message: data.message };

                // Auto-clear flash after 4 seconds
                setTimeout(() => this.flash = { type: '', message: '' }, 4000);

            } catch (err) {
                this.flash = { type: 'error', message: 'Network error. Please retry.' };
            } finally {
                this.saving = false;
            }
        },
    };
}
</script>
@endpush
@endsection

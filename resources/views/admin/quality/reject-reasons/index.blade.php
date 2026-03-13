@extends('admin.layouts.app')

@section('title', 'Reject Reasons')


@section('content')

<div
    x-data="rejectReasons(
        '{{ $apiToken }}',
        {{ $factoryId ?? 'null' }},
        {{ $reasons->toJson() }},
        {{ $factories->map(fn($f) => ['id' => $f->id, 'name' => $f->name])->values()->toJson() }}
    )"
    x-init="init()"
>

{{-- ── Flash ─────────────────────────────────────────────────────── --}}
<template x-if="flash.show">
    <div class="fixed top-5 right-5 z-50 flex items-center gap-3 rounded-xl px-5 py-3 text-sm font-medium shadow-lg transition-all"
         :class="flash.type === 'success' ? 'bg-green-600 text-white' : 'bg-red-600 text-white'">
        <span x-text="flash.msg"></span>
        <button @click="flash.show = false" class="opacity-70 hover:opacity-100">&times;</button>
    </div>
</template>

{{-- ── Header row ────────────────────────────────────────────────── --}}
<div class="mb-5 flex flex-wrap items-center gap-3">

    {{-- Factory selector (super-admin only) --}}
    <template x-if="factories.length > 0">
        <select @change="currentFactoryId = $event.target.value; loadReasons()"
                class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-rose-500">
            <option value="">All Factories</option>
            <template x-for="f in factories" :key="f.id">
                <option :value="f.id" :selected="currentFactoryId == f.id" x-text="f.name"></option>
            </template>
        </select>
    </template>

    {{-- Search --}}
    <input x-model="search" type="text" placeholder="Search code or name…"
           class="w-full max-w-xs rounded-lg border border-gray-200 px-3 py-1.5 text-sm
                  focus:border-rose-400 focus:outline-none focus:ring-1 focus:ring-rose-300">

    {{-- Category filter --}}
    <select x-model="categoryFilter"
            class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-700
                   focus:border-rose-400 focus:outline-none focus:ring-1 focus:ring-rose-300">
        <option value="">All Categories</option>
        <option value="cosmetic">Cosmetic</option>
        <option value="dimensional">Dimensional</option>
        <option value="functional">Functional</option>
        <option value="material">Material</option>
        <option value="assembly">Assembly</option>
        <option value="other">Other</option>
    </select>

    {{-- Active filter --}}
    <select x-model="activeFilter"
            class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-700
                   focus:border-rose-400 focus:outline-none focus:ring-1 focus:ring-rose-300">
        <option value="">All Status</option>
        <option value="active">Active only</option>
        <option value="inactive">Inactive only</option>
    </select>

    <span class="text-xs text-gray-400"
          x-text="filtered.length + ' reason(s)'"></span>

    <button @click="openCreate()"
            class="ml-auto inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white
                   hover:bg-indigo-700 transition-colors">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Add Reason
    </button>
</div>

{{-- ── Table ─────────────────────────────────────────────────────── --}}
<div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200 overflow-hidden">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Code</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Category</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <template x-if="filtered.length === 0">
                <tr>
                    <td colspan="5" class="px-4 py-10 text-center text-gray-400 text-sm">
                        No reject reasons found. Add one to start categorising defects.
                    </td>
                </tr>
            </template>
            <template x-for="r in filtered" :key="r.id">
                <tr class="hover:bg-gray-50 transition-colors" :class="!r.is_active ? 'opacity-60' : ''">
                    <td class="px-4 py-3">
                        <span class="font-mono font-semibold text-rose-700 text-xs bg-rose-50 px-2 py-0.5 rounded"
                              x-text="r.code"></span>
                    </td>
                    <td class="px-4 py-3 font-medium text-gray-900" x-text="r.name"></td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                              :class="categoryClass(r.category)"
                              x-text="r.category.charAt(0).toUpperCase() + r.category.slice(1)"></span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium"
                              :class="r.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'">
                            <span class="h-1.5 w-1.5 rounded-full"
                                  :class="r.is_active ? 'bg-green-500' : 'bg-gray-400'"></span>
                            <span x-text="r.is_active ? 'Active' : 'Inactive'"></span>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <button @click="openEdit(r)"
                                    class="rounded-lg border border-gray-200 px-2.5 py-1 text-xs text-gray-600
                                           hover:bg-gray-50 transition-colors">Edit</button>
                            <button @click="toggleActive(r)"
                                    class="rounded-lg border px-2.5 py-1 text-xs transition-colors"
                                    :class="r.is_active
                                        ? 'border-amber-200 text-amber-700 hover:bg-amber-50'
                                        : 'border-green-200 text-green-700 hover:bg-green-50'"
                                    x-text="r.is_active ? 'Deactivate' : 'Activate'"></button>
                            <button @click="deleteReason(r)"
                                    class="rounded-lg border border-red-200 px-2.5 py-1 text-xs text-red-600
                                           hover:bg-red-50 transition-colors">Delete</button>
                        </div>
                    </td>
                </tr>
            </template>
        </tbody>
    </table>
</div>

{{-- ══════════════════════════════════════════════════════ --}}
{{-- MODAL — Create / Edit                                  --}}
{{-- ══════════════════════════════════════════════════════ --}}
<div x-show="showModal" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
     @click.self="showModal = false">
    <div class="w-full max-w-md rounded-2xl bg-white shadow-xl">

        {{-- Header --}}
        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
            <h3 class="text-base font-semibold text-gray-900"
                x-text="modalMode === 'create' ? 'Add Reject Reason' : 'Edit Reject Reason'"></h3>
            <button @click="showModal = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>

        {{-- Body --}}
        <div class="space-y-4 px-6 py-5">

            {{-- Code --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">
                    Code <span class="text-red-500">*</span>
                    <span class="font-normal text-gray-400 ml-1">(max 20 chars, auto uppercase)</span>
                </label>
                <input x-model="form.code"
                       @input="form.code = form.code.toUpperCase()"
                       maxlength="20" type="text" placeholder="e.g. SCRATCH"
                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm font-mono
                              focus:border-rose-400 focus:outline-none focus:ring-1 focus:ring-rose-300">
            </div>

            {{-- Name --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">
                    Name <span class="text-red-500">*</span>
                </label>
                <input x-model="form.name" maxlength="100" type="text"
                       placeholder="e.g. Surface Scratch"
                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm
                              focus:border-rose-400 focus:outline-none focus:ring-1 focus:ring-rose-300">
            </div>

            {{-- Category --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">
                    Category <span class="text-red-500">*</span>
                </label>
                <select x-model="form.category"
                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm
                               focus:border-rose-400 focus:outline-none focus:ring-1 focus:ring-rose-300">
                    <option value="cosmetic">Cosmetic — visual surface defects</option>
                    <option value="dimensional">Dimensional — size/tolerance out of spec</option>
                    <option value="functional">Functional — part doesn't perform correctly</option>
                    <option value="material">Material — wrong or defective material</option>
                    <option value="assembly">Assembly — incorrect assembly or missing part</option>
                    <option value="other">Other</option>
                </select>
            </div>

            {{-- is_active (edit only) --}}
            <template x-if="modalMode === 'edit'">
                <div class="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3">
                    <div>
                        <p class="text-sm font-medium text-gray-700">Active</p>
                        <p class="text-xs text-gray-400">Inactive reasons are hidden from operator forms</p>
                    </div>
                    <button @click="form.is_active = !form.is_active" type="button"
                            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2
                                   border-transparent transition-colors duration-200 focus:outline-none"
                            :class="form.is_active ? 'bg-rose-500' : 'bg-gray-200'">
                        <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow
                                     transition duration-200 ease-in-out"
                              :class="form.is_active ? 'translate-x-5' : 'translate-x-0'"></span>
                    </button>
                </div>
            </template>

            <template x-if="formError">
                <p class="text-xs text-red-600" x-text="formError"></p>
            </template>
        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4">
            <button @click="showModal = false"
                    class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">Cancel</button>
            <button @click="save()" :disabled="saving"
                    class="rounded-lg bg-rose-600 px-5 py-2 text-sm font-medium text-white hover:bg-rose-700 disabled:opacity-50">
                <span x-text="saving ? 'Saving…' : (modalMode === 'create' ? 'Add Reason' : 'Save Changes')"></span>
            </button>
        </div>
    </div>
</div>

</div>{{-- end x-data --}}
@endsection

@push('scripts')
<script>
function rejectReasons(apiToken, factoryId, reasons, factories) {
    return {
        apiToken,
        currentFactoryId: factoryId,
        factories:  factories || [],
        reasons:    reasons   || [],

        // Filters
        search:         '',
        categoryFilter: '',
        activeFilter:   '',

        // Modal
        showModal:  false,
        modalMode:  'create',  // 'create' | 'edit'
        saving:     false,
        formError:  null,
        editTarget: null,
        form: { code: '', name: '', category: 'other', is_active: true },

        // Flash
        flash: { show: false, type: 'success', msg: '' },

        get headers() {
            return {
                'Authorization': `Bearer ${this.apiToken}`,
                'Content-Type':  'application/json',
                'Accept':        'application/json',
            };
        },

        get filtered() {
            return this.reasons.filter(r => {
                const q = this.search.toLowerCase();
                if (q && !r.code.toLowerCase().includes(q) && !r.name.toLowerCase().includes(q)) return false;
                if (this.categoryFilter && r.category !== this.categoryFilter) return false;
                if (this.activeFilter === 'active'   && !r.is_active) return false;
                if (this.activeFilter === 'inactive' &&  r.is_active) return false;
                return true;
            });
        },

        init() { /* reasons pre-loaded from server */ },

        setFlash(type, msg) {
            this.flash = { show: true, type, msg };
            setTimeout(() => this.flash.show = false, 3000);
        },

        // ── Load from API ─────────────────────────────────────────────
        async loadReasons() {
            try {
                const params = new URLSearchParams();
                if (this.currentFactoryId) params.append('factory_id', this.currentFactoryId);
                const res  = await fetch(`/api/v1/reject-reasons?${params}`, { headers: this.headers });
                if (!res.ok) return;
                const data = await res.json();
                this.reasons = Array.isArray(data) ? data : (data.data ?? []);
            } catch(e) { /* silent */ }
        },

        // ── Create ────────────────────────────────────────────────────
        openCreate() {
            this.modalMode = 'create';
            this.formError = null;
            this.form = { code: '', name: '', category: 'other', is_active: true };
            this.showModal = true;
        },

        // ── Edit ──────────────────────────────────────────────────────
        openEdit(r) {
            this.modalMode  = 'edit';
            this.formError  = null;
            this.editTarget = r;
            this.form = { code: r.code, name: r.name, category: r.category, is_active: !!r.is_active };
            this.showModal = true;
        },

        // ── Save (create or update) ───────────────────────────────────
        async save() {
            this.saving    = true;
            this.formError = null;

            if (!this.form.code.trim() || !this.form.name.trim()) {
                this.formError = 'Code and Name are required.';
                this.saving = false;
                return;
            }

            const isCreate = this.modalMode === 'create';
            const url    = isCreate
                ? '/api/v1/reject-reasons'
                : `/api/v1/reject-reasons/${this.editTarget.id}`;
            const method = isCreate ? 'POST' : 'PUT';

            const payload = {
                code:     this.form.code.trim().toUpperCase(),
                name:     this.form.name.trim(),
                category: this.form.category,
            };
            if (!isCreate) payload.is_active = this.form.is_active;

            try {
                const res = await fetch(url, {
                    method,
                    headers: this.headers,
                    body: JSON.stringify(payload),
                });
                if (!res.ok) {
                    const err = await res.json();
                    this.formError = err.message || JSON.stringify(err.errors || err);
                    this.saving = false;
                    return;
                }
                this.showModal = false;
                this.setFlash('success', isCreate ? 'Reject reason added.' : 'Reject reason updated.');
                await this.loadReasons();
            } catch(e) {
                this.formError = e.message || 'Network error.';
            }
            this.saving = false;
        },

        // ── Toggle active ─────────────────────────────────────────────
        async toggleActive(r) {
            try {
                const res = await fetch(`/api/v1/reject-reasons/${r.id}`, {
                    method: 'PUT',
                    headers: this.headers,
                    body: JSON.stringify({ is_active: !r.is_active }),
                });
                if (res.ok) {
                    r.is_active = !r.is_active;
                    this.setFlash('success', r.is_active ? 'Reason activated.' : 'Reason deactivated.');
                }
            } catch(e) { /* silent */ }
        },

        // ── Delete ────────────────────────────────────────────────────
        async deleteReason(r) {
            if (!confirm(`Delete "${r.name}" (${r.code})? This cannot be undone.`)) return;
            try {
                const res = await fetch(`/api/v1/reject-reasons/${r.id}`, {
                    method: 'DELETE',
                    headers: this.headers,
                });
                if (res.ok || res.status === 204) {
                    this.reasons = this.reasons.filter(x => x.id !== r.id);
                    this.setFlash('success', 'Reject reason deleted.');
                } else {
                    const err = await res.json().catch(() => ({}));
                    this.setFlash('error', err.message || 'Delete failed.');
                }
            } catch(e) { this.setFlash('error', 'Network error.'); }
        },

        // ── Helpers ───────────────────────────────────────────────────
        categoryClass(cat) {
            const map = {
                cosmetic:    'bg-pink-100 text-pink-700',
                dimensional: 'bg-blue-100 text-blue-700',
                functional:  'bg-red-100 text-red-700',
                material:    'bg-amber-100 text-amber-700',
                assembly:    'bg-purple-100 text-purple-700',
                other:       'bg-gray-100 text-gray-600',
            };
            return map[cat] ?? 'bg-gray-100 text-gray-600';
        },
    };
}
</script>
@endpush

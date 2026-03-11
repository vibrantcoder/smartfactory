@extends('admin.layouts.app')

@section('title', 'Factories')

@section('header-actions')
<button onclick="window.dispatchEvent(new CustomEvent('open-factory-create'))"
        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition-colors">
    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
    </svg>
    New Factory
</button>
@endsection

@section('content')

<div x-data="factoryManager({{ json_encode($apiToken) }})"
     x-init="init()"
     @open-factory-create.window="openCreate()">

    {{-- Flash --}}
    <div x-show="flash.message" style="display:none"
         class="mb-4 rounded-lg border px-4 py-3 text-sm"
         :class="flash.type === 'success' ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800'">
        <span x-text="flash.message"></span>
    </div>

    {{-- Table card --}}
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
        <div class="border-b border-gray-100 px-5 py-4">
            <h2 class="text-sm font-semibold text-gray-700">Factories</h2>
            <p class="text-xs text-gray-400 mt-0.5"><span x-text="factories.length"></span> factories</p>
        </div>

        {{-- Skeleton --}}
        <div x-show="loading && factories.length === 0" class="space-y-px">
            <template x-for="i in 3" :key="i">
                <div class="flex items-center gap-4 px-5 py-4 border-b border-gray-50">
                    <div class="h-3.5 w-32 animate-pulse rounded bg-gray-100"></div>
                    <div class="flex-1 h-3 w-48 animate-pulse rounded bg-gray-100"></div>
                </div>
            </template>
        </div>

        {{-- Table --}}
        <div x-show="!loading || factories.length > 0" class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-5 py-3 text-left">Name</th>
                        <th class="px-5 py-3 text-left">Code</th>
                        <th class="px-5 py-3 text-left">Location</th>
                        <th class="px-5 py-3 text-left">Timezone</th>
                        <th class="px-5 py-3 text-left">Status</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <template x-for="f in factories" :key="f.id">
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3.5 font-medium text-gray-800" x-text="f.name"></td>
                            <td class="px-5 py-3.5 font-mono text-xs text-gray-500" x-text="f.code"></td>
                            <td class="px-5 py-3.5 text-gray-600" x-text="f.location || '—'"></td>
                            <td class="px-5 py-3.5 text-gray-500 text-xs" x-text="f.timezone || '—'"></td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                      :class="f.status === 'active'
                                          ? 'bg-green-50 text-green-700 ring-1 ring-green-200'
                                          : 'bg-gray-100 text-gray-500 ring-1 ring-gray-200'"
                                      x-text="f.status"></span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <div class="flex justify-end gap-2">
                                    <button @click="openEdit(f)"
                                            class="rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100 transition-colors">
                                        Edit
                                    </button>
                                    <button x-show="f.status === 'active'"
                                            @click="deactivate(f)"
                                            class="rounded-md bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700 hover:bg-red-100 transition-colors">
                                        Deactivate
                                    </button>
                                    <button x-show="f.status !== 'active'"
                                            @click="reactivate(f)"
                                            class="rounded-md bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700 hover:bg-green-100 transition-colors">
                                        Reactivate
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!loading && factories.length === 0">
                        <td colspan="6" class="px-5 py-12 text-center text-sm text-gray-400">
                            No factories found. Use "New Factory" to create one.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Create / Edit Modal --}}
    <div x-show="modal.open" style="display:none"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
         @click.self="modal.open = false">
        <div class="w-full max-w-lg rounded-xl bg-white shadow-2xl ring-1 ring-gray-200">
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                <h3 class="text-sm font-semibold text-gray-800"
                    x-text="modal.mode === 'create' ? 'New Factory' : 'Edit Factory'"></h3>
                <button @click="modal.open = false" class="rounded-md p-1 text-gray-400 hover:text-gray-600 hover:bg-gray-100">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="px-6 py-5 space-y-4">
                {{-- Name --}}
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Factory Name *</label>
                    <input x-model="modal.form.name" type="text" placeholder="e.g. Main Production Plant"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                           :class="modal.errors.name ? 'border-red-400' : ''">
                    <p x-show="modal.errors.name" class="mt-1 text-xs text-red-600" x-text="(modal.errors.name||[])[0]"></p>
                </div>

                {{-- Code --}}
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Code *
                        <span class="font-normal text-gray-400">(unique short identifier)</span>
                    </label>
                    <input x-model="modal.form.code" type="text" placeholder="e.g. PLANT-01"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                           :class="modal.errors.code ? 'border-red-400' : ''">
                    <p x-show="modal.errors.code" class="mt-1 text-xs text-red-600" x-text="(modal.errors.code||[])[0]"></p>
                </div>

                {{-- Location + Timezone --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Location</label>
                        <input x-model="modal.form.location" type="text" placeholder="e.g. Shah Alam, Selangor"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Timezone</label>
                        <select x-model="modal.form.timezone"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                            <option value="UTC">UTC</option>
                            <option value="Asia/Kuala_Lumpur">Asia/Kuala_Lumpur (MYT)</option>
                            <option value="Asia/Singapore">Asia/Singapore (SGT)</option>
                            <option value="Asia/Jakarta">Asia/Jakarta (WIB)</option>
                            <option value="Asia/Bangkok">Asia/Bangkok (ICT)</option>
                            <option value="Asia/Tokyo">Asia/Tokyo (JST)</option>
                            <option value="Asia/Shanghai">Asia/Shanghai (CST)</option>
                        </select>
                    </div>
                </div>

                {{-- Error banner --}}
                <div x-show="modal.error" style="display:none"
                     class="rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-xs text-red-700"
                     x-text="modal.error"></div>
            </div>

            <div class="flex justify-end gap-3 border-t border-gray-100 px-6 py-4">
                <button @click="modal.open = false"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button @click="submitModal()" :disabled="modal.saving"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold text-white hover:bg-indigo-700 disabled:opacity-60 transition-colors">
                    <svg x-show="modal.saving" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                    <span x-text="modal.saving ? 'Saving…' : (modal.mode === 'create' ? 'Create Factory' : 'Save Changes')"></span>
                </button>
            </div>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script>
function factoryManager(apiToken) {
    return {
        factories: [],
        loading: false,
        flash: { type: '', message: '' },
        modal: {
            open: false, mode: 'create', saving: false, factory: null,
            error: null, errors: {},
            form: { name: '', code: '', location: '', timezone: 'UTC', status: 'active' },
        },

        init() { this.load(); },

        authHeaders() {
            return {
                'Accept':        'application/json',
                'Content-Type':  'application/json',
                'X-CSRF-TOKEN':  document.querySelector('meta[name="csrf-token"]').content,
                'Authorization': apiToken ? `Bearer ${apiToken}` : '',
            };
        },

        async load() {
            this.loading = true;
            try {
                const res  = await fetch('/api/v1/factories?per_page=100', { headers: this.authHeaders() });
                const data = await res.json();
                this.factories = Array.isArray(data.data) ? data.data : (Array.isArray(data) ? data : []);
            } catch (e) {
                this.setFlash('error', 'Failed to load factories.');
            } finally {
                this.loading = false;
            }
        },

        openCreate() {
            this.modal.open    = true;
            this.modal.mode    = 'create';
            this.modal.saving  = false;
            this.modal.factory = null;
            this.modal.error   = null;
            this.modal.errors  = {};
            this.modal.form    = { name: '', code: '', location: '', timezone: 'UTC', status: 'active' };
        },

        openEdit(f) {
            this.modal.open    = true;
            this.modal.mode    = 'edit';
            this.modal.saving  = false;
            this.modal.factory = f;
            this.modal.error   = null;
            this.modal.errors  = {};
            this.modal.form    = { name: f.name, code: f.code, location: f.location || '', timezone: f.timezone || 'UTC', status: f.status };
        },

        async submitModal() {
            this.modal.saving = true;
            this.modal.error  = null;
            this.modal.errors = {};
            try {
                const isCreate = this.modal.mode === 'create';
                const url    = isCreate ? '/api/v1/factories' : `/api/v1/factories/${this.modal.factory.id}`;
                const method = isCreate ? 'POST' : 'PUT';
                const res    = await fetch(url, { method, headers: this.authHeaders(), body: JSON.stringify(this.modal.form) });
                const data   = await res.json();

                if (res.status === 422) {
                    this.modal.errors = data.errors ?? {};
                } else if (!res.ok) {
                    this.modal.error = data.message ?? 'Failed to save factory.';
                } else {
                    this.setFlash('success', data.message ?? (isCreate ? 'Factory created.' : 'Factory updated.'));
                    this.modal.open = false;
                    this.load();
                }
            } catch (e) {
                this.modal.error = 'Network error. Please retry.';
            } finally {
                this.modal.saving = false;
            }
        },

        async deactivate(f) {
            if (!confirm(`Deactivate "${f.name}"? This will prevent new assignments to this factory.`)) return;
            try {
                const res  = await fetch(`/api/v1/factories/${f.id}`, { method: 'DELETE', headers: this.authHeaders() });
                const data = await res.json();
                if (!res.ok) {
                    this.setFlash('error', data.message ?? 'Failed to deactivate factory.');
                } else {
                    this.setFlash('success', data.message ?? 'Factory deactivated.');
                    this.load();
                }
            } catch (e) {
                this.setFlash('error', 'Network error.');
            }
        },

        async reactivate(f) {
            try {
                const res  = await fetch(`/api/v1/factories/${f.id}`, {
                    method: 'PUT', headers: this.authHeaders(),
                    body: JSON.stringify({ name: f.name, code: f.code, location: f.location || '', timezone: f.timezone || 'UTC', status: 'active' }),
                });
                const data = await res.json();
                if (!res.ok) {
                    this.setFlash('error', data.message ?? 'Failed to reactivate factory.');
                } else {
                    this.setFlash('success', data.message ?? 'Factory reactivated.');
                    this.load();
                }
            } catch (e) {
                this.setFlash('error', 'Network error.');
            }
        },

        setFlash(type, message) {
            this.flash = { type, message };
            setTimeout(() => { this.flash = { type: '', message: '' }; }, 4000);
        },
    };
}
</script>
@endpush

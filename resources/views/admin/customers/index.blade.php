{{--
    Customers — Full CRUD
    ======================
    Create, edit, and deactivate customers via Alpine.js → /api/v1/customers.
--}}
@extends('admin.layouts.app')

@section('title', 'Customers')

@section('header-actions')
<button onclick="window.dispatchEvent(new CustomEvent('open-create-customer'))"
        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white
               hover:bg-indigo-700 transition-colors">
    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
    </svg>
    New Customer
</button>
@endsection

@section('content')

<div x-data="customersPage(
        {{ json_encode($apiToken) }},
        {{ json_encode($factoryId ?? null) }},
        {{ json_encode($factories->map(fn($f) => ['id' => $f->id, 'name' => $f->name])->values()->all()) }}
    )" x-init="init()"
     @open-create-customer.window="openCreate()">

    {{-- ── Notification ─────────────────────────────────────── --}}
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
               placeholder="Search name, code, contact…"
               class="w-full max-w-xs rounded-lg border border-gray-200 px-3 py-2 text-sm
                      focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">

        <select x-model="filterStatus" @change="load(1)"
                class="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700
                       focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
            <option value="">All Statuses</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
        </select>

        <span class="ml-auto text-sm text-gray-400">
            <span x-text="total"></span> customers
        </span>
    </div>

    {{-- ── Table Card ───────────────────────────────────────── --}}
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200">

        {{-- Skeleton --}}
        <template x-if="loading && customers.length === 0">
            <div class="space-y-px p-4">
                <template x-for="i in 5" :key="i">
                    <div class="flex items-center gap-4 py-3">
                        <div class="h-4 w-20 animate-pulse rounded bg-gray-100"></div>
                        <div class="h-4 w-40 animate-pulse rounded bg-gray-100"></div>
                        <div class="ml-auto h-7 w-20 animate-pulse rounded bg-gray-100"></div>
                    </div>
                </template>
            </div>
        </template>

        {{-- Error --}}
        <template x-if="error">
            <div class="px-5 py-4 text-sm text-red-600" x-text="error"></div>
        </template>

        {{-- Table --}}
        <template x-if="customers.length > 0">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-gray-100 bg-gray-50">
                        <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                            <th class="px-5 py-3">Code</th>
                            <th class="px-5 py-3">Name</th>
                            <th class="px-5 py-3">Contact</th>
                            <th class="px-5 py-3">Email</th>
                            <th class="px-5 py-3 text-center">Parts</th>
                            <th class="px-5 py-3 text-center">Status</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <template x-for="c in customers" :key="c.id">
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-3 font-mono text-xs font-medium text-gray-700"
                                    x-text="c.code"></td>
                                <td class="px-5 py-3 font-medium text-gray-900" x-text="c.name"></td>
                                <td class="px-5 py-3 text-gray-500 text-xs" x-text="c.contact_person ?? '—'"></td>
                                <td class="px-5 py-3 text-gray-500 text-xs" x-text="c.email ?? '—'"></td>
                                <td class="px-5 py-3 text-center">
                                    <span class="text-xs text-gray-600"
                                          x-text="(c.parts_count ?? 0) + ' / ' + (c.active_parts_count ?? 0) + ' active'"></span>
                                </td>
                                <td class="px-5 py-3 text-center">
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                                          :class="c.status === 'active'
                                              ? 'bg-green-100 text-green-700'
                                              : 'bg-gray-100 text-gray-500'"
                                          x-text="c.status"></span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button @click="openEdit(c)"
                                                class="rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700
                                                       hover:bg-indigo-100 transition-colors">
                                            Edit
                                        </button>
                                        <button @click="confirmDeactivate(c)"
                                                x-show="c.status === 'active'"
                                                class="rounded-lg bg-red-50 px-3 py-1.5 text-xs font-medium text-red-600
                                                       hover:bg-red-100 transition-colors">
                                            Deactivate
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
        <template x-if="!loading && customers.length === 0 && !error">
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <svg class="h-12 w-12 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <p class="mt-3 text-sm font-medium text-gray-500">No customers found</p>
                <p class="mt-1 text-xs text-gray-400">
                    <template x-if="search || filterStatus">
                        <span>Try adjusting your search or filter.</span>
                    </template>
                    <template x-if="!search && !filterStatus">
                        <span>Add your first customer using the button above.</span>
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
                    <h3 class="text-base font-semibold text-gray-900">New Customer</h3>
                    <button @click="showCreate = false" class="text-gray-400 hover:text-gray-600">&times;</button>
                </div>
                <form @submit.prevent="submitCreate()">
                    <div class="space-y-4 px-6 py-5">
                        <div class="grid grid-cols-2 gap-4">
                            <template x-if="factories.length > 0">
                                <div class="col-span-2">
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Factory <span class="text-red-500">*</span></label>
                                    <select x-model="form.factory_id" required
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
                                <label class="block text-xs font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                                <input x-model="form.name" type="text" required maxlength="120"
                                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm
                                              focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Code <span class="text-red-500">*</span></label>
                                <input x-model="form.code" type="text" required maxlength="30"
                                       placeholder="e.g. ACME"
                                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm uppercase
                                              focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Contact Person</label>
                                <input x-model="form.contact_person" type="text" maxlength="100"
                                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm
                                              focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Email</label>
                                <input x-model="form.email" type="email" maxlength="100"
                                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm
                                              focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Phone</label>
                                <input x-model="form.phone" type="text" maxlength="30"
                                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm
                                              focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                            </div>
                            <div class="col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Address</label>
                                <textarea x-model="form.address" rows="2" maxlength="255"
                                          class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm
                                                 focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300"></textarea>
                            </div>
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
                            <span x-text="saving ? 'Saving…' : 'Create Customer'"></span>
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
                    <h3 class="text-base font-semibold text-gray-900">Edit Customer — <span x-text="editTarget?.code"></span></h3>
                    <button @click="showEdit = false" class="text-gray-400 hover:text-gray-600">&times;</button>
                </div>
                <form @submit.prevent="submitEdit()">
                    <div class="space-y-4 px-6 py-5">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                                <input x-model="form.name" type="text" required maxlength="120"
                                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm
                                              focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Code <span class="text-red-500">*</span></label>
                                <input x-model="form.code" type="text" required maxlength="30"
                                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm uppercase
                                              focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                                <select x-model="form.status"
                                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm
                                               focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Contact Person</label>
                                <input x-model="form.contact_person" type="text" maxlength="100"
                                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm
                                              focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Email</label>
                                <input x-model="form.email" type="email" maxlength="100"
                                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm
                                              focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Phone</label>
                                <input x-model="form.phone" type="text" maxlength="30"
                                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm
                                              focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300">
                            </div>
                            <div class="col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Address</label>
                                <textarea x-model="form.address" rows="2" maxlength="255"
                                          class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm
                                                 focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300"></textarea>
                            </div>
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
    </div>

    {{-- ════════════════════════════════════════════════════════
         DEACTIVATE CONFIRM MODAL
    ════════════════════════════════════════════════════════ --}}
    <div x-show="showDeactivate" style="display:none"
         class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4"
         @click.self="showDeactivate = false">
        <div class="w-full max-w-sm rounded-2xl bg-white shadow-xl">
                <div class="px-6 py-5">
                    <h3 class="text-base font-semibold text-gray-900">Deactivate Customer?</h3>
                    <p class="mt-2 text-sm text-gray-500">
                        <strong x-text="deactivateTarget?.name"></strong>
                        (<span x-text="deactivateTarget?.code"></span>) will be marked inactive.
                        This fails if the customer has active parts.
                    </p>
                    <template x-if="formError">
                        <p class="mt-3 text-xs text-red-600" x-text="formError"></p>
                    </template>
                </div>
                <div class="flex justify-end gap-3 border-t border-gray-100 px-6 py-4">
                    <button @click="showDeactivate = false"
                            class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 transition-colors">
                        Cancel
                    </button>
                    <button @click="submitDeactivate()" :disabled="saving"
                            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white
                                   hover:bg-red-700 disabled:opacity-60 transition-colors">
                        <span x-text="saving ? 'Deactivating…' : 'Deactivate'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script>
function customersPage(apiToken, factoryId, factories) {
    return {
        // ── List state
        customers:    [],
        loading:      false,
        error:        null,
        search:       '',
        filterStatus: '',
        total:        0,
        currentPage:  1,
        lastPage:     1,

        // ── Factory (super-admin)
        factories:    factories ?? [],

        // ── Modal state
        showCreate:       false,
        showEdit:         false,
        showDeactivate:   false,
        editTarget:       null,
        deactivateTarget: null,
        form:             {},
        saving:           false,
        formError:        null,

        // ── Toast
        toast: { show: false, message: '', type: 'success' },

        get headers() {
            return {
                'Accept':       'application/json',
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${apiToken}`,
                'X-CSRF-TOKEN':  document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            };
        },

        init() {
            this.load(1);
        },

        async load(page = 1) {
            this.loading = true;
            this.error   = null;
            const p = new URLSearchParams({ page, per_page: 25 });
            if (this.search)       p.set('search', this.search);
            if (this.filterStatus) p.set('status', this.filterStatus);

            try {
                const res  = await fetch(`/api/v1/customers?${p}`, { headers: this.headers });
                const data = await res.json();
                if (!res.ok) { this.error = data.message ?? 'Failed to load.'; return; }
                this.customers   = data.data ?? data;
                this.total       = data.total ?? this.customers.length;
                this.currentPage = data.current_page ?? 1;
                this.lastPage    = data.last_page ?? 1;
            } catch {
                this.error = 'Network error loading customers.';
            } finally {
                this.loading = false;
            }
        },

        // ── Create
        openCreate() {
            this.form = {
                factory_id: factoryId ?? (this.factories[0]?.id ?? ''),
                name: '', code: '', contact_person: '', email: '', phone: '', address: '',
            };
            this.formError  = null;
            this.showCreate = true;
        },

        async submitCreate() {
            this.saving    = true;
            this.formError = null;
            try {
                const res  = await fetch('/api/v1/customers', {
                    method: 'POST', headers: this.headers,
                    body: JSON.stringify(this.form),
                });
                const data = await res.json();
                if (!res.ok) {
                    this.formError = this.extractError(data);
                    return;
                }
                this.showCreate = false;
                this.showToast('Customer created successfully.', 'success');
                this.load(1);
            } catch {
                this.formError = 'Network error. Please try again.';
            } finally {
                this.saving = false;
            }
        },

        // ── Edit
        openEdit(c) {
            this.editTarget = c;
            this.form = {
                name: c.name, code: c.code, status: c.status,
                contact_person: c.contact_person ?? '',
                email:          c.email          ?? '',
                phone:          c.phone          ?? '',
                address:        c.address        ?? '',
            };
            this.formError = null;
            this.showEdit  = true;
        },

        async submitEdit() {
            this.saving    = true;
            this.formError = null;
            try {
                const res  = await fetch(`/api/v1/customers/${this.editTarget.id}`, {
                    method: 'PUT', headers: this.headers,
                    body: JSON.stringify(this.form),
                });
                const data = await res.json();
                if (!res.ok) {
                    this.formError = this.extractError(data);
                    return;
                }
                this.showEdit = false;
                this.showToast('Customer updated successfully.', 'success');
                this.load(this.currentPage);
            } catch {
                this.formError = 'Network error. Please try again.';
            } finally {
                this.saving = false;
            }
        },

        // ── Deactivate
        confirmDeactivate(c) {
            this.deactivateTarget = c;
            this.formError        = null;
            this.showDeactivate   = true;
        },

        async submitDeactivate() {
            this.saving    = true;
            this.formError = null;
            try {
                const res  = await fetch(`/api/v1/customers/${this.deactivateTarget.id}`, {
                    method: 'DELETE', headers: this.headers,
                });
                const data = await res.json();
                if (!res.ok) {
                    this.formError = data.message ?? 'Deactivation failed.';
                    return;
                }
                this.showDeactivate = false;
                this.showToast('Customer deactivated.', 'success');
                this.load(this.currentPage);
            } catch {
                this.formError = 'Network error. Please try again.';
            } finally {
                this.saving = false;
            }
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

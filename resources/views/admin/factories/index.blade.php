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
                                    <button @click="openSettings(f)"
                                            class="rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100 transition-colors">
                                        Settings
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
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @keydown.escape.window="modal.open = false">
        <div class="absolute inset-0 bg-black/40" @click="modal.open = false"></div>
        <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl overflow-y-auto" style="max-height:92vh">
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

    {{-- Settings Modal (Week Off + Holidays) --}}
    <div x-show="settingsModal.open" style="display:none"
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @keydown.escape.window="settingsModal.open = false">
        <div class="absolute inset-0 bg-black/40" @click="settingsModal.open = false"></div>
        <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl flex flex-col" style="max-height:92vh">

            {{-- Header --}}
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 flex-shrink-0">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">Factory Settings</h3>
                    <p class="text-xs text-gray-400 mt-0.5" x-text="settingsModal.factory?.name"></p>
                </div>
                <button @click="settingsModal.open = false" class="rounded-md p-1 text-gray-400 hover:text-gray-600 hover:bg-gray-100">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="flex-1 min-h-0 overflow-y-auto px-6 py-5 space-y-6">

                {{-- Week Off --}}
                <div>
                    <label class="text-xs font-semibold text-gray-700 block mb-1">Week Off Days</label>
                    <p class="text-xs text-gray-400 mb-3">Click a day to toggle. Selected days (red) appear blocked on the Production Planning calendar.</p>
                    <div class="flex flex-wrap gap-2 mb-3">
                        <template x-for="day in weekDayOptions" :key="day.value">
                            <button type="button"
                                    @click="toggleWeekOff(day.value)"
                                    :class="isWeekOffSelected(day.value)
                                        ? 'bg-red-100 text-red-700 ring-2 ring-red-400 font-semibold shadow-sm'
                                        : 'bg-gray-100 text-gray-500 hover:bg-gray-200'"
                                    class="px-4 py-2 rounded-lg text-xs transition-colors select-none"
                                    x-text="day.label">
                            </button>
                        </template>
                    </div>
                    {{-- inline feedback --}}
                    <div x-show="settingsModal.weekOffMsg" style="display:none"
                         class="mb-2 rounded-lg px-3 py-1.5 text-xs font-medium"
                         :class="settingsModal.weekOffMsgType === 'success'
                             ? 'bg-green-50 text-green-700 ring-1 ring-green-200'
                             : 'bg-red-50 text-red-700 ring-1 ring-red-200'"
                         x-text="settingsModal.weekOffMsg"></div>
                </div>

                <hr class="border-gray-100">

                {{-- Holidays --}}
                <div>
                    <label class="text-xs font-semibold text-gray-700 block mb-1">Public Holidays</label>
                    <p class="text-xs text-gray-400 mb-3">Add dates with a label. These appear red on the Production Planning calendar.</p>

                    {{-- Pending (new) rows --}}
                    <div class="space-y-2 mb-3">
                        <template x-for="(row, idx) in settingsModal.pendingRows" :key="idx">
                            <div class="flex gap-2 items-center">
                                <input x-model="row.date" type="date"
                                       class="rounded-lg border border-gray-300 px-3 py-2 text-xs focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                                <input x-model="row.name" type="text" placeholder="e.g. Republic Day"
                                       class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-xs focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-400">
                                <button @click="settingsModal.pendingRows = settingsModal.pendingRows.filter((_, i) => i !== idx)"
                                        x-show="settingsModal.pendingRows.length > 1"
                                        class="rounded-lg p-2 text-gray-300 hover:text-red-500 hover:bg-red-50 transition-colors flex-shrink-0">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </template>
                    </div>

                    {{-- Action buttons --}}
                    <div class="flex items-center gap-2 mb-3">
                        <button @click="addMoreHolidayRow()"
                                class="inline-flex items-center gap-1.5 rounded-lg border-2 border-dashed border-gray-300 px-4 py-2 text-xs font-medium text-gray-500 hover:bg-gray-50 hover:border-gray-400 transition-colors">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                            </svg>
                            Add More
                        </button>
                    </div>

                    {{-- Error / Success --}}
                    <div x-show="settingsModal.holidayError" style="display:none"
                         class="mb-2 rounded-lg bg-red-50 border border-red-200 px-3 py-1.5 text-xs text-red-700"
                         x-text="settingsModal.holidayError"></div>
                    <div x-show="settingsModal.holidaySuccessMsg" style="display:none"
                         class="mb-2 rounded-lg bg-green-50 border border-green-200 px-3 py-1.5 text-xs text-green-700"
                         x-text="settingsModal.holidaySuccessMsg"></div>

                    {{-- Saved Holiday List --}}
                    <div class="rounded-lg border border-gray-100 overflow-hidden">
                        <div x-show="settingsModal.loadingHolidays" class="px-4 py-6 text-center text-xs text-gray-400">Loading…</div>
                        <div x-show="!settingsModal.loadingHolidays && settingsModal.holidays.length === 0"
                             class="px-4 py-8 text-center text-xs text-gray-400">No holidays saved yet. Fill in the form above and click Save Holidays.</div>
                        <template x-for="h in settingsModal.holidays" :key="h.id">
                            <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-50 last:border-b-0 hover:bg-gray-50">
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-mono font-medium bg-red-50 text-red-700" x-text="h.holiday_date"></span>
                                    <span class="text-xs text-gray-700" x-text="h.name"></span>
                                </div>
                                <button @click="removeHoliday(h)"
                                        class="rounded p-1 text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-gray-100 px-6 py-4 flex-shrink-0">
                <button @click="settingsModal.open = false"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button @click="saveAllSettings()"
                        :disabled="settingsModal.savingWeekOff || settingsModal.savingHolidays"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold text-white hover:bg-indigo-700 disabled:opacity-60 disabled:cursor-not-allowed transition-colors">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span x-text="(settingsModal.savingWeekOff || settingsModal.savingHolidays) ? 'Saving…' : 'Save Settings'"></span>
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

        // Settings modal state
        settingsModal: {
            open: false, factory: null,
            weekOff: [],        savingWeekOff: false,
            weekOffMsg: '',     weekOffMsgType: 'success',
            holidays: [],       loadingHolidays: false,
            pendingRows: [{ date: '', name: '' }],
            savingHolidays: false, holidayError: null,
            holidaySuccessMsg: '',
        },

        weekDayOptions: [
            { value: 0, label: 'Sunday' },
            { value: 1, label: 'Monday' },
            { value: 2, label: 'Tuesday' },
            { value: 3, label: 'Wednesday' },
            { value: 4, label: 'Thursday' },
            { value: 5, label: 'Friday' },
            { value: 6, label: 'Saturday' },
        ],

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

        async openSettings(f) {
            this.settingsModal.factory          = f;
            this.settingsModal.open             = true;
            this.settingsModal.weekOff          = (f.week_off_days || []).map(Number);
            this.settingsModal.weekOffMsg       = '';
            this.settingsModal.holidays         = [];
            this.settingsModal.loadingHolidays  = true;
            this.settingsModal.pendingRows      = [{ date: '', name: '' }];
            this.settingsModal.holidayError     = null;
            this.settingsModal.holidaySuccessMsg = '';

            try {
                const res  = await fetch(`/api/v1/factories/${f.id}/holidays`, { headers: this.authHeaders() });
                const data = await res.json();
                this.settingsModal.holidays = Array.isArray(data) ? data : [];
            } catch {
                this.settingsModal.holidays = [];
            } finally {
                this.settingsModal.loadingHolidays = false;
            }
        },

        isWeekOffSelected(dayVal) {
            return this.settingsModal.weekOff.includes(Number(dayVal));
        },

        toggleWeekOff(dayVal) {
            const val = Number(dayVal);
            const current = this.settingsModal.weekOff;
            const idx = current.indexOf(val);
            // Reassign array so Alpine detects the change reliably
            if (idx === -1) {
                this.settingsModal.weekOff = [...current, val];
            } else {
                this.settingsModal.weekOff = current.filter(v => v !== val);
            }
        },

        showWeekOffMsg(type, msg) {
            this.settingsModal.weekOffMsgType = type;
            this.settingsModal.weekOffMsg = msg;
            setTimeout(() => { this.settingsModal.weekOffMsg = ''; }, 3000);
        },

        async saveAllSettings() {
            // Save week-off days (always)
            await this.saveWeekOff();
            // Save pending holiday rows only if at least one row is filled
            const rows = this.settingsModal.pendingRows.filter(r => r.date && r.name.trim());
            if (rows.length) {
                await this.savePendingHolidays();
            }
        },

        async saveWeekOff() {
            this.settingsModal.savingWeekOff = true;
            this.settingsModal.weekOffMsg = '';
            try {
                const res = await fetch(`/api/v1/factories/${this.settingsModal.factory.id}/week-off`, {
                    method: 'PATCH',
                    headers: this.authHeaders(),
                    body: JSON.stringify({ week_off_days: this.settingsModal.weekOff.map(Number) }),
                });
                const data = await res.json();
                if (res.ok) {
                    const f = this.factories.find(f => f.id === this.settingsModal.factory.id);
                    if (f) f.week_off_days = (data.week_off_days || []).map(Number);
                    const days = (data.week_off_days || []).length;
                    this.showWeekOffMsg('success', days ? `Saved! ${days} day(s) marked as week off.` : 'Saved — no week off days set.');
                } else {
                    this.showWeekOffMsg('error', data.message ?? 'Failed to save week-off days.');
                }
            } catch {
                this.showWeekOffMsg('error', 'Network error. Please retry.');
            } finally {
                this.settingsModal.savingWeekOff = false;
            }
        },

        addMoreHolidayRow() {
            this.settingsModal.pendingRows.push({ date: '', name: '' });
        },

        async savePendingHolidays() {
            this.settingsModal.holidayError = null;
            this.settingsModal.holidaySuccessMsg = '';
            const rows = this.settingsModal.pendingRows.filter(r => r.date && r.name.trim());
            if (!rows.length) {
                return;
            }
            this.settingsModal.savingHolidays = true;
            try {
                const results = await Promise.all(rows.map(row =>
                    fetch(`/api/v1/factories/${this.settingsModal.factory.id}/holidays`, {
                        method: 'POST',
                        headers: this.authHeaders(),
                        body: JSON.stringify({ holiday_date: row.date, name: row.name.trim() }),
                    }).then(r => r.json())
                ));
                // Merge into holidays list (reassign for Alpine reactivity)
                let list = [...this.settingsModal.holidays];
                results.forEach(data => {
                    if (!data.id) return;
                    const idx = list.findIndex(h => h.holiday_date === data.holiday_date);
                    if (idx >= 0) list[idx] = data;
                    else list.push(data);
                });
                list.sort((a, b) => a.holiday_date.localeCompare(b.holiday_date));
                this.settingsModal.holidays = list;
                this.settingsModal.pendingRows = [{ date: '', name: '' }];
                this.settingsModal.holidaySuccessMsg = `${rows.length} holiday(s) saved successfully.`;
                setTimeout(() => { this.settingsModal.holidaySuccessMsg = ''; }, 3000);
            } catch {
                this.settingsModal.holidayError = 'Network error. Please retry.';
            } finally {
                this.settingsModal.savingHolidays = false;
            }
        },

        async removeHoliday(h) {
            try {
                const res = await fetch(`/api/v1/factories/${this.settingsModal.factory.id}/holidays/${h.id}`, {
                    method: 'DELETE',
                    headers: this.authHeaders(),
                });
                if (res.ok) {
                    this.settingsModal.holidays = this.settingsModal.holidays.filter(x => x.id !== h.id);
                } else {
                    const data = await res.json();
                    this.setFlash('error', data.message ?? 'Failed to remove holiday.');
                }
            } catch {
                this.setFlash('error', 'Network error.');
            }
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

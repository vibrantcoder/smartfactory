/**
 * routing-builder.js
 *
 * Alpine.js component for the drag-and-drop process routing builder.
 *
 * DEPENDENCIES (include in your layout):
 *   <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
 *   <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
 *
 * UNIT CONVENTION: all cycle times are in MINUTES throughout this component.
 *
 * USAGE:
 *   <div x-data="routingBuilder({ partId: {{ $part->id }}, token: '{{ $apiToken }}' })">
 *     …
 *   </div>
 *
 * @param {Object} config
 * @param {number} config.partId     - The Part ID being edited
 * @param {string} config.token      - Sanctum API token (or use cookie auth)
 * @param {Array}  config.initial    - Initial processes from server (optional pre-load)
 * @param {Array}  config.palette    - Available ProcessMasters from server (optional pre-load)
 */
export function routingBuilder(config = {}) {
    return {
        // ── State ──────────────────────────────────────────────

        partId:         config.partId,
        apiToken:       config.token ?? null,
        csrfToken:      document.querySelector('meta[name="csrf-token"]')?.content ?? '',

        /** Currently-assigned routing steps (ordered array) */
        steps: (config.initial ?? []).map((s, i) => ({
            _key:                   s.id ?? `new-${i}`,   // local React-style key
            processMasterId:        s.process_master_id,
            processMasterName:      s.process_master_name ?? s.processMaster?.name ?? '',
            processMasterCode:      s.process_master_code ?? s.processMaster?.code ?? '',
            machineTypeDefault:     s.machine_type_default ?? s.processMaster?.machine_type_default ?? null,
            defaultCycleTime:       parseFloat(s.default_cycle_time ?? 0),
            overrideCycleTime:      s.standard_cycle_time ? String(s.standard_cycle_time) : '',
            machineTypeRequired:    s.machine_type_required ?? null,
            notes:                  s.notes ?? null,
            sequenceOrder:          i + 1,
        })),

        /** Available process masters for the left palette */
        palette: (config.palette ?? []).map(pm => ({
            id:               pm.id,
            name:             pm.name,
            code:             pm.code,
            machineType:      pm.machine_type_default ?? null,
            description:      pm.description ?? null,
        })),

        paletteSearch:  '',
        loading:        false,
        saving:         false,
        previewing:     false,
        saved:          false,
        errorMessage:   null,
        previewResult:  null,

        // ── Computed ───────────────────────────────────────────

        /**
         * Total cycle time — live calculation from local state.
         * Uses override if set, else processMaster.standardTime.
         * Always up to date without a network call.
         */
        get totalCycleTime() {
            return this.steps.reduce((sum, step) => {
                const override = parseFloat(step.overrideCycleTime);
                const minutes  = !isNaN(override) && step.overrideCycleTime !== ''
                    ? override
                    : step.defaultCycleTime;
                return sum + (isNaN(minutes) ? 0 : minutes);
            }, 0);
        },

        get totalCycleTimeFormatted() {
            const t = this.totalCycleTime;
            const h = Math.floor(t / 60);
            const m = (t % 60).toFixed(1);
            return h > 0 ? `${h}h ${m}min` : `${m} min`;
        },

        get filteredPalette() {
            if (!this.paletteSearch) return this.palette;
            const q = this.paletteSearch.toLowerCase();
            return this.palette.filter(pm =>
                pm.name.toLowerCase().includes(q) ||
                pm.code.toLowerCase().includes(q) ||
                (pm.machineType ?? '').toLowerCase().includes(q)
            );
        },

        /** Returns true if the step has an override different from the default */
        hasOverride(step) {
            const v = parseFloat(step.overrideCycleTime);
            return !isNaN(v) && step.overrideCycleTime !== '' && v !== step.defaultCycleTime;
        },

        effectiveCycleTime(step) {
            const v = parseFloat(step.overrideCycleTime);
            return (!isNaN(v) && step.overrideCycleTime !== '') ? v : step.defaultCycleTime;
        },

        // ── Lifecycle ──────────────────────────────────────────

        init() {
            this.$nextTick(() => this._initSortable());

            // Load palette from API if not pre-loaded
            if (this.palette.length === 0) {
                this.loadPalette();
            }
        },

        _initSortable() {
            const listEl = this.$refs.stepList;
            if (!listEl || typeof Sortable === 'undefined') return;

            Sortable.create(listEl, {
                animation:        150,
                handle:           '.drag-handle',
                ghostClass:       'opacity-40',
                dragClass:        'shadow-xl',
                onEnd: (evt) => {
                    if (evt.oldIndex === evt.newIndex) return;

                    // Splice item in Alpine array to match DOM reorder
                    const moved = this.steps.splice(evt.oldIndex, 1)[0];
                    this.steps.splice(evt.newIndex, 0, moved);

                    // Re-number sequence_order
                    this._renumber();
                },
            });
        },

        _renumber() {
            this.steps.forEach((step, i) => { step.sequenceOrder = i + 1; });
        },

        _uniqueKey() {
            return `new-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`;
        },

        // ── Palette Actions ────────────────────────────────────

        async loadPalette() {
            this.loading = true;
            try {
                const res = await this._fetch('GET', '/api/v1/process-masters/palette');
                this.palette = (res.data ?? []).map(pm => ({
                    id:           pm.id,
                    name:         pm.name,
                    code:         pm.code,
                    machineType:  pm.machine_type_default ?? null,
                    description:  pm.description ?? null,
                }));
            } catch (e) {
                this.errorMessage = 'Could not load process library.';
            } finally {
                this.loading = false;
            }
        },

        /**
         * Add a process master from the palette to the routing steps.
         * Double-click or drag-to-list triggers this.
         */
        addStep(pm) {
            this.steps.push({
                _key:               this._uniqueKey(),
                processMasterId:    pm.id,
                processMasterName:  pm.name,
                processMasterCode:  pm.code,
                machineTypeDefault: pm.machineType,
                defaultCycleTime:   pm.standardTime,
                overrideCycleTime:  '',
                machineTypeRequired: pm.machineType,
                notes:              null,
                sequenceOrder:      this.steps.length + 1,
            });
        },

        removeStep(index) {
            this.steps.splice(index, 1);
            this._renumber();
        },

        clearOverride(step) {
            step.overrideCycleTime = '';
        },

        // ── AJAX: Preview (no save) ────────────────────────────

        /**
         * Server-confirmed cycle time preview — validates IDs + gets exact totals.
         * Useful before saving to show the user the confirmed calculation.
         *
         * POST /api/v1/process-masters/preview-cycle-time
         */
        async previewCycleTime() {
            if (this.steps.length === 0) return;

            this.previewing    = true;
            this.errorMessage  = null;
            this.previewResult = null;

            try {
                const payload = {
                    steps: this.steps.map(s => ({
                        process_master_id:   s.processMasterId,
                        standard_cycle_time: s.overrideCycleTime !== ''
                            ? parseFloat(s.overrideCycleTime)
                            : null,
                    })),
                };

                this.previewResult = await this._fetch(
                    'POST',
                    '/api/v1/process-masters/preview-cycle-time',
                    payload
                );
            } catch (e) {
                this.errorMessage = e.message ?? 'Preview failed.';
            } finally {
                this.previewing = false;
            }
        },

        // ── AJAX: Save ────────────────────────────────────────

        /**
         * Persist the routing to the server.
         * PUT /api/v1/parts/{partId}/processes
         *
         * On success:
         *   - Updates steps from server response (confirms sequence + total)
         *   - Fires 'routing-saved' custom event with the updated part data
         *   - Shows a transient "Saved" indicator
         */
        async save() {
            if (this.steps.length === 0) {
                this.errorMessage = 'Add at least one process step before saving.';
                return;
            }

            this.saving       = true;
            this.errorMessage = null;
            this.saved        = false;

            try {
                const payload = {
                    processes: this.steps.map(s => ({
                        process_master_id:    s.processMasterId,
                        machine_type_required: s.machineTypeRequired ?? null,
                        standard_cycle_time:  s.overrideCycleTime !== ''
                            ? parseFloat(s.overrideCycleTime)
                            : null,
                        notes:                s.notes ?? null,
                    })),
                };

                const res = await this._fetch(
                    'PUT',
                    `/api/v1/parts/${this.partId}/processes`,
                    payload
                );

                // Sync local state from server response
                const serverPart = res.data;
                if (serverPart?.processes) {
                    this.steps = serverPart.processes.map((p, i) => ({
                        _key:               `srv-${p.id ?? i}`,
                        processMasterId:    p.process_master?.id ?? p.process_master_id,
                        processMasterName:  p.process_master?.name ?? '',
                        processMasterCode:  p.process_master?.code ?? '',
                        machineTypeDefault: p.process_master?.machine_type_default ?? null,
                        defaultCycleTime:   0,
                        overrideCycleTime:  p.standard_cycle_time != null ? String(p.standard_cycle_time) : '',
                        machineTypeRequired: p.machine_type_required ?? null,
                        notes:              p.notes ?? null,
                        sequenceOrder:      p.sequence_order,
                    }));
                }

                this.saved = true;
                setTimeout(() => { this.saved = false; }, 3000);

                // Notify parent components
                this.$dispatch('routing-saved', { part: serverPart });

            } catch (e) {
                this.errorMessage = e.message ?? 'Save failed. Please try again.';
            } finally {
                this.saving = false;
            }
        },

        // ── HTTP helper ───────────────────────────────────────

        async _fetch(method, url, body = null) {
            const headers = {
                'Content-Type':  'application/json',
                'Accept':        'application/json',
                'X-CSRF-TOKEN':  this.csrfToken,
            };

            if (this.apiToken) {
                headers['Authorization'] = `Bearer ${this.apiToken}`;
            }

            const res = await fetch(url, {
                method,
                headers,
                body: body ? JSON.stringify(body) : undefined,
            });

            const json = await res.json();

            if (!res.ok) {
                // Laravel validation error (422)
                if (res.status === 422 && json.errors) {
                    const firstError = Object.values(json.errors)[0]?.[0];
                    throw new Error(firstError ?? json.message ?? 'Validation error.');
                }
                throw new Error(json.message ?? `Request failed (${res.status}).`);
            }

            return json;
        },
    };
}

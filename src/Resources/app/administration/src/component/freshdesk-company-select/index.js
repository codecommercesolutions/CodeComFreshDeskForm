import template from './freshdesk-company-select.html.twig';
import './freshdesk-company-select.scss';

const { Component } = Shopware;

/**
 * freshdesk-company-select
 *
 * Fixes in this version:
 *
 *  1. Shows company NAME instead of "Company #id":
 *     - First tries to match the ID in the pre-loaded options list.
 *     - If not found (options not yet loaded, or company not in cache),
 *       calls GET /api/_action/freshdesk/company/{id} which checks the
 *       DB cache then falls back to the live Freshdesk API.
 *
 *  2. Search shows existing companies:
 *     - On focus: calls search with empty query → gets first 20 companies.
 *     - On type: debounced search by name.
 *     - Both show existing companies in the dropdown.
 *
 *  3. @update:value (not @input) for sw-text-field in SW 6.7 / Vue 3.
 *
 *  4. _settingValue guard prevents search triggering while the component
 *     itself is writing the company name into the text field.
 */
Component.register('freshdesk-company-select', {
    template,

    inject: ['loginService'],

    props: {
        /** Current Freshdesk company ID (stringified int) */
        value: { type: String, default: null },
        /** Submission email — used when resolving/updating the contact */
        email: { type: String, default: '' },
        /** Pre-loaded [{value, label}] from parent */
        options: { type: Array, default: () => [] },
        label: { type: String, default: '' },
        disabled: { type: Boolean, default: false },
    },

    data() {
        return {
            inputValue:      '',   // text shown in the field
            filteredOptions: [],   // dropdown results
            showDropdown:    false,
            isSearching:     false,
            selectedId:      null, // committed company ID
            debounceTimer:   null,
            statusMessage:   '',
            statusType:      'info',
            _settingValue:   false, // guard: skip search during programmatic writes
        };
    },

    watch: {
        /**
         * When parent sets a value:
         *   1. Try to match in options (fast, no API call).
         *   2. If not matched yet, fetch the company name from the API.
         */
        value: {
            immediate: true,
            handler(newVal) {
                if (!newVal) {
                    this.selectedId = null;
                    this._setInput('');
                    return;
                }

                this.selectedId = String(newVal);

                const match = this.options.find(o => String(o.value) === String(newVal));
                if (match) {
                    this._setInput(match.label);
                } else {
                    // Options may load later — show placeholder and fetch name
                    this._setInput('');
                    this._fetchCompanyName(newVal);
                }
            },
        },

        /**
         * When the pre-loaded options array arrives / updates, re-resolve.
         */
        options(newOptions) {
            if (!this.selectedId) return;
            const match = newOptions.find(o => String(o.value) === String(this.selectedId));
            if (match) this._setInput(match.label);
        },
    },

    methods: {

        // ── Helpers ───────────────────────────────────────────────────────────

        /** Write to the text field without triggering a search */
        _setInput(text) {
            this._settingValue = true;
            this.inputValue    = String(text ?? '');
            this._settingValue = false;
        },

        _headers() {
            return {
                'Authorization': `Bearer ${this.loginService.getToken()}`,
                'Content-Type':  'application/json',
            };
        },

        // ── Fetch company name by ID ──────────────────────────────────────────

        /**
         * Called when we have an ID but no name yet.
         * Checks DB cache first (server-side), falls back to live Freshdesk API.
         */
        async _fetchCompanyName(companyId) {
            if (!companyId) return;

            this.isSearching    = true;
            this.statusMessage  = this.$tc('freshdesk.companySelect.resolving') || 'Resolving…';
            this.statusType     = 'info';

            try {
                const res = await fetch(
                    `${Shopware.Context.api.apiPath}/_action/freshdesk/company/${encodeURIComponent(companyId)}`,
                    { headers: this._headers() }
                );

                if (res.ok) {
                    const data = await res.json();
                    if (data.name) {
                        this._setInput(data.name);
                        this.statusMessage = '';
                    }
                }
            } catch {
                // non-fatal — user can still clear and re-select
            } finally {
                this.isSearching = false;
                if (this.statusMessage === (this.$tc('freshdesk.companySelect.resolving') || 'Resolving…')) {
                    this.statusMessage = '';
                }
            }
        },

        // ── Text-field events ─────────────────────────────────────────────────

        /** @update:value fires on every keystroke in sw-text-field (SW 6.7 / Vue 3) */
        onInputChange(value) {
            if (this._settingValue) return;

            this.inputValue = value;

            // Typing clears the committed selection
            if (this.selectedId) {
                this.selectedId = null;
                this.statusMessage = '';
                this.$emit('update:value',       null);
                this.$emit('update:companyName', null);
            }

            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => {
                this._searchCompanies(value ? value.trim() : '');
            }, 300);
        },

        onFocus() {
            // Load first 20 companies immediately so the dropdown feels instant
            if (this.filteredOptions.length === 0 && !this.selectedId) {
                this._searchCompanies('');
            }
            this.showDropdown = true;
        },

        onBlur() {
            setTimeout(() => { this.showDropdown = false; }, 250);
        },

        // ── Search ────────────────────────────────────────────────────────────

        async _searchCompanies(query) {
            this.isSearching  = true;
            this.showDropdown = true;
            try {
                const url = `${Shopware.Context.api.apiPath}/_action/freshdesk/search-companies?q=${encodeURIComponent(query)}`;
                const res = await fetch(url, { headers: this._headers() });
                if (!res.ok) { this.filteredOptions = []; return; }

                const results = await res.json();
                this.filteredOptions = results.map(c => ({
                    value: String(c.id),
                    label: c.name,
                }));
                this.showDropdown = true;
            } catch {
                this.filteredOptions = [];
            } finally {
                this.isSearching = false;
            }
        },

        // ── Selection ─────────────────────────────────────────────────────────

        selectExisting(option) {
            this.selectedId      = option.value;
            this.filteredOptions = [];
            this.showDropdown    = false;
            this.statusMessage   = '';
            this._setInput(option.label); // ← company name in the text field

            this.$emit('update:value',       option.value);
            this.$emit('update:companyName', option.label);

            this._resolveCompany(option.value, null);
        },

        async createNew(name) {
            this.showDropdown    = false;
            this.filteredOptions = [];
            this.isSearching     = true;
            this.statusMessage   = this.$tc('freshdesk.companySelect.creating', 0, { name });
            this.statusType      = 'info';
            await this._resolveCompany(null, name);
        },

        clearSelection() {
            this.selectedId      = null;
            this.filteredOptions = [];
            this.showDropdown    = false;
            this.statusMessage   = '';
            this._setInput('');

            this.$emit('update:value',       null);
            this.$emit('update:companyName', null);
        },

        // ── API: resolve-company (create if new + update contact) ─────────────

        async _resolveCompany(companyId, companyName) {
            if (!this.email) { this.isSearching = false; return; }

            try {
                const res = await fetch(`${Shopware.Context.api.apiPath}/_action/freshdesk/resolve-company`, {
                    method:  'POST',
                    headers: this._headers(),
                    body: JSON.stringify({
                        email:       this.email,
                        companyId:   companyId   ?? null,
                        companyName: companyName ?? null,
                    }),
                });

                const result = await res.json();

                if (result.success && result.companyId) {
                    this.selectedId = String(result.companyId);
                    const name      = result.companyName ?? companyName;
                    this._setInput(name);

                    this.statusMessage = this.$tc('freshdesk.companySelect.resolved');
                    this.statusType    = 'success';

                    this.$emit('update:value',       this.selectedId);
                    this.$emit('update:companyName', name);

                    setTimeout(() => { this.statusMessage = ''; }, 3000);
                } else {
                    this.statusMessage = result.message ?? this.$tc('freshdesk.companySelect.error');
                    this.statusType    = 'error';
                }
            } catch {
                this.statusMessage = this.$tc('freshdesk.companySelect.error');
                this.statusType    = 'error';
            } finally {
                this.isSearching = false;
            }
        },
    },
});

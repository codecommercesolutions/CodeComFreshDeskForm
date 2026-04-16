import Plugin from 'src/plugin-system/plugin.class';
import ButtonLoadingIndicator from 'src/utility/loading-indicator/button-loading-indicator.util';

/**
 * FreshdeskFormPlugin
 *
 * Mirrors the Shopware core FormHandler pattern (SW 6.7):
 *  - Inline Bootstrap alert appended BELOW the form (no alert() popup)
 *  - Loading spinner on the submit button while AJAX is in-flight
 *  - Button re-enabled after every response so the user can try again
 *  - No page reload on success — form is reset and success message shown
 *
 * All select-option data is rendered server-side by Twig from the
 * freshdesk_form_api_data repository. Zero live Freshdesk API calls here.
 */
export default class FreshdeskFormPlugin extends Plugin {

    static options = {
        /**
         * URL query parameter whose value is auto-filled into the form field
         * marked with data-url-number-field="true".
         * The admin maps a specific Freshdesk custom field to this param via
         * Plugin Settings → "URL ?number= → Ticket Field (API name)".
         */
        urlNumberParam: 'number',
        /** Position of the button spinner: 'before' | 'after' | 'inner' */
        loadingIndicatorPosition: 'before',
        /** Selector for the response-message container (sibling of the form) */
        responseContainerSelector: '.freshdesk-form-response',
    };

    init() {
        this._submitButton      = this.el.querySelector('[type="submit"]');
        this._responseContainer = this.el.closest('.cms-element-freshdesk-standard-form')
                                        ?.querySelector(this.options.responseContainerSelector)
                                 ?? null;
        this._buttonLoader = null;

        this._fillFromUrlParams();
        this._registerEvents();
    }

    // ── URL param pre-fill ────────────────────────────────────────────────────

    /**
     * Reads the configured URL params and fills matching form fields.
     *
     * ?number=VALUE  →  the field marked with data-url-number-field="true"
     *                   (Twig sets this attribute on the custom field whose
     *                    Freshdesk API name the admin entered in plugin settings)
     */
    _fillFromUrlParams() {
        const params = new URLSearchParams(window.location.search);

        // Fill the field that the admin mapped to the ?number= URL param
        const numberValue = params.get(this.options.urlNumberParam);
        if (numberValue) {
            const target = this.el.querySelector('[data-url-number-field="true"]');
            if (target) {
                target.value = numberValue;
            }
        }
    }

    // ── Events ────────────────────────────────────────────────────────────────

    _registerEvents() {
        this.el.addEventListener('submit', this._onSubmit.bind(this));
    }

    // ── Submit ────────────────────────────────────────────────────────────────

    _onSubmit(event) {
        event.preventDefault();

        // Clear any previous response message
        this._clearResponse();

        if (!this.el.checkValidity()) {
            this._showValidationErrors();
            return;
        }

        // Disable button + show spinner (Shopware ButtonLoadingIndicator pattern)
        this._addLoadingIndicator();

        // Build a properly nested data object.
        // FormData entries with bracket notation like "custom_fields[cf_name]"
        // produce flat string keys — we parse them into a real nested object here.
        const data = this._buildFormData(new FormData(this.el));

        fetch(this.el.action, {
            method: 'POST',
            headers: {
                'Content-Type':    'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(data),
        })
            .then(r => r.json())
            .then(result => {
                this._removeLoadingIndicator();
                this._handleResponse(result);
            })
            .catch(error => {
                this._removeLoadingIndicator();
                this._handleError(error);
            });
    }

    /**
     * Convert a FormData instance into a plain object, correctly handling
     * bracket-notation field names such as custom_fields[cf_reference_number].
     *
     * e.g. input:  "custom_fields[cf_foo]" => "bar"
     *      output: { custom_fields: { cf_foo: "bar" } }
     *
     * @param {FormData} formData
     * @returns {Object}
     */
    _buildFormData(formData) {
        const result = {};

        for (const [key, value] of formData.entries()) {
            // Match bracket notation: parentKey[childKey]
            const bracketMatch = key.match(/^([^\[]+)\[([^\]]+)\]$/);

            if (bracketMatch) {
                const parentKey = bracketMatch[1];
                const childKey  = bracketMatch[2];

                if (!result[parentKey] || typeof result[parentKey] !== 'object') {
                    result[parentKey] = {};
                }
                result[parentKey][childKey] = value;
            } else {
                result[key] = value;
            }
        }

        return result;
    }

    // ── Loading indicator (mirrors Shopware FormHandler.addLoadingIndicator) ──

    _addLoadingIndicator() {
        if (!this._submitButton) return;
        this._buttonLoader = new ButtonLoadingIndicator(
            this._submitButton,
            this.options.loadingIndicatorPosition
        );
        this._buttonLoader.create();
    }

    _removeLoadingIndicator() {
        if (this._buttonLoader) {
            this._buttonLoader.remove();
            this._buttonLoader = null;
        }
    }

    // ── Response — inline alert below the form (no alert() popup) ────────────

    /**
     * Renders a Bootstrap alert inside .freshdesk-form-response
     * and scrolls it into view.  On success the form fields are also reset.
     */
    _handleResponse(result) {
        const isSuccess = (result.type === 'success');

        this._showResponse(
            result.message ?? (isSuccess ? 'Submitted successfully.' : 'An error occurred.'),
            isSuccess ? 'success' : 'danger'
        );

        if (isSuccess) {
            this.el.reset();
        }
    }

    _handleError(error) {
        console.error('FreshdeskFormPlugin error:', error);
        this._showResponse('An error occurred. Please try again.', 'danger');
    }

    /**
     * Injects a Shopware-compatible Bootstrap alert into the response container.
     *
     * @param {string} message
     * @param {'success'|'danger'} type
     */
    _showResponse(message, type) {
        if (!this._responseContainer) return;

        // Icon names match Shopware's icon set
        const iconName = type === 'success' ? 'checkmark-circle' : 'times-circle';

        this._responseContainer.innerHTML = `
            <div class="alert alert-${type} alert-has-icon freshdesk-alert" role="alert" aria-live="polite">
                <span class="icon icon-${iconName}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">
                        ${type === 'success'
                            ? '<path d="M12 2a10 10 0 1 0 0 20A10 10 0 0 0 12 2zm-1.5 14.4L6 11.9l1.4-1.4 3.1 3.1 6.1-6.1 1.4 1.4-7.5 7.5z"/>'
                            : '<path d="M12 2a10 10 0 1 0 0 20A10 10 0 0 0 12 2zm1 14h-2v-2h2v2zm0-4h-2V7h2v5z"/>'
                        }
                    </svg>
                </span>
                <div class="alert-content-container">
                    <div class="alert-content">${this._escapeHtml(message)}</div>
                </div>
            </div>`;

        // Scroll into view so the user always sees the feedback
        this._responseContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    _clearResponse() {
        if (this._responseContainer) {
            this._responseContainer.innerHTML = '';
        }
    }

    // ── Field-level validation ────────────────────────────────────────────────

    _showValidationErrors() {
        const required = this.el.querySelectorAll('input[required], textarea[required], select[required]');
        let firstInvalid = null;

        required.forEach(input => {
            if (!input.validity.valid) {
                this._showFieldError(input, input.validationMessage || 'Input should not be empty.');
                if (!firstInvalid) firstInvalid = input;
            }
        });

        // Focus first invalid field so the page scrolls there automatically
        if (firstInvalid) firstInvalid.focus();
    }

    _showFieldError(input, message) {
        const existing = input.parentElement.querySelector('.invalid-feedback');
        if (existing) existing.remove();

        input.classList.add('is-invalid');

        const div = document.createElement('div');
        div.className  = 'invalid-feedback d-block';
        div.textContent = message;
        input.parentElement.appendChild(div);

        input.addEventListener('input', () => {
            input.classList.remove('is-invalid');
            input.parentElement.querySelector('.invalid-feedback')?.remove();
        }, { once: true });
    }

    // ── Utility ───────────────────────────────────────────────────────────────

    /** Prevent XSS when inserting server message into innerHTML */
    _escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
}

import template from './freshdesk-submissions-detail.html.twig';
import './freshdesk-submissions-detail.scss';

const { Component, Mixin } = Shopware;
const { mapPageErrors } = Shopware.Component.getComponentHelper();
Component.register('freshdesk-submissions-detail', {
    template,

    inject: ['repositoryFactory', 'systemConfigService', 'freshdeskLookupApiService'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder')
    ],

    data() {
        return {
            submission: null,
            isLoading: false,
            isSaveSuccessful: false,
            freshdeskGroups: [],
            freshdeskProducts: [],
            freshdeskAgents: [],
            freshdeskEmailConfigs: [],
            freshdeskCompanies: [],
            ticketFields: {},
            /** Custom (default=false) Freshdesk ticket field definitions */
            customFieldDefs: [],
        };
    },

    computed: {
        repository() {
            return this.repositoryFactory.create('freshdesk_form_submission');
        },

        isCreateMode() {
            return this.$route.name === 'freshdesk.submissions.create';
        },

        typeOptions() {
            return this.ticketFields.type || [];
        },

        statusOptions() {
            return this.ticketFields.status || [];
        },

        sourceOptions() {
            return this.ticketFields.source || [];
        },

        priorityOptions() {
            return this.ticketFields.priority || [];
        },

        groupOptions() {
            if (!Array.isArray(this.freshdeskGroups)) {
                return [];
            }
            return this.freshdeskGroups.map(group => ({
                value: String(group.id),
                label: group.name
            }));
        },

        productOptions() {
            if (!Array.isArray(this.freshdeskProducts)) {
                return [];
            }
            return this.freshdeskProducts.map(product => ({
                value: String(product.id),
                label: product.name
            }));
        },

        // ── New computed options ──────────────────────────────────────────────
        agentOptions() {
            if (!Array.isArray(this.freshdeskAgents)) {
                return [];
            }
            return this.freshdeskAgents.map(agent => ({
                value: String(agent.id),
                label: agent.contact?.name ?? `Agent #${agent.id}`
            }));
        },

        emailConfigOptions() {
            if (!Array.isArray(this.freshdeskEmailConfigs)) {
                return [];
            }
            return this.freshdeskEmailConfigs.map(cfg => ({
                value: String(cfg.id),
                label: cfg.name ?? `Email Config #${cfg.id}`
            }));
        },

        companyOptions() {
            if (!Array.isArray(this.freshdeskCompanies)) {
                return [];
            }
            return this.freshdeskCompanies.map(company => ({
                value: String(company.id),
                label: company.name ?? `Company #${company.id}`
            }));
        },

        // ── Extra / custom fields ─────────────────────────────────────────────

        /** True when the submission has saved extra field values */
        hasExtraFields() {
            const ef = this.submission?.extraFields;
            return ef !== null && ef !== undefined && typeof ef === 'object' && Object.keys(ef).length > 0;
        },

        /** Pretty-printed JSON of saved extra field values */
        extraFieldsJson() {
            try {
                return JSON.stringify(this.submission?.extraFields ?? {}, null, 2);
            } catch {
                return '{}';
            }
        },
        // ─────────────────────────────────────────────────────────────────────

        ...mapPageErrors({
            'firstName':     'submission.firstName',
            'lastName':      'submission.lastName',
            'email':         'submission.email',
            'phone':         'submission.phone',
            'subject':       'submission.subject',
            'message':       'submission.message',
            'type':          'submission.type'
        })
    },

    created() {
        this.loadEntity();
        this.loadFreshdeskData();
    },

    methods: {
        loadEntity() {
            if (this.isCreateMode) {
                this.submission = this.repository.create(Shopware.Context.api);
            } else {
                this.isLoading = true;
                this.repository.get(this.$route.params.id, Shopware.Context.api).then((entity) => {
                    this.submission = entity;

                    // Convert IDs to strings for dropdown matching
                    // Convert numeric ID fields to strings for dropdown matching
                    ['productId', 'groupId', 'responderId', 'emailConfigId', 'companyId', 'requesterId'].forEach(field => {
                        if (this.submission[field] !== null && this.submission[field] !== undefined) {
                            this.submission[field] = String(this.submission[field]);
                        }
                    });

                    // Initialize identity text fields to empty string when null
                    // so sw-text-field v-model renders them correctly (null → not rendered)
                    ['facebookId', 'twitterId', 'uniqueExternalId'].forEach(field => {
                        if (this.submission[field] === null || this.submission[field] === undefined) {
                            this.submission[field] = '';
                        }
                    });

                    this.isLoading = false;
                });
            }
        },

        async loadFreshdeskData() {
            try {
                const metadata = await this.freshdeskLookupApiService.getAllMetadata();

                this.freshdeskGroups      = metadata.groups      || [];
                this.freshdeskProducts    = metadata.products    || [];
                this.freshdeskAgents      = metadata.agents      || [];
                this.freshdeskEmailConfigs = metadata.emailConfigs || [];
                this.freshdeskCompanies   = metadata.companies   || [];

                const ticketFieldsArray = metadata.ticketFields || [];
                this.ticketFields = {};

                // ── Parse standard fields (choices) ───────────────────────────
                ticketFieldsArray.forEach(field => {
                    try {
                        let fieldName = field.name;

                        if (field.name === 'ticket_type' || field.label === 'Type') {
                            fieldName = 'type';
                        } else if (field.name === 'status'   || field.label === 'Status')   { fieldName = 'status'; }
                        else if (field.name === 'priority' || field.label === 'Priority') { fieldName = 'priority'; }
                        else if (field.name === 'source'   || field.label === 'Source')   { fieldName = 'source'; }

                        if (field.choices) {
                            let formattedChoices = [];

                            if (typeof field.choices === 'object' && !Array.isArray(field.choices)) {
                                const firstValue = Object.values(field.choices)[0];
                                if (typeof firstValue === 'number') {
                                    formattedChoices = Object.keys(field.choices).map(label => ({
                                        value: field.choices[label],
                                        label: String(label)
                                    }));
                                } else {
                                    formattedChoices = Object.entries(field.choices).map(([key, val]) => ({
                                        value: parseInt(key),
                                        label: String(Array.isArray(val) ? val[0] : val)
                                    }));
                                }
                            } else if (Array.isArray(field.choices)) {
                                formattedChoices = field.choices.map(choice => {
                                    if (typeof choice === 'string') return { value: choice, label: choice };
                                    if (choice && typeof choice === 'object') return { value: choice.value, label: choice.label || choice.value };
                                    return { value: choice, label: String(choice) };
                                });
                            }

                            if (formattedChoices.length > 0) {
                                this.ticketFields[fieldName] = formattedChoices;
                            }
                        }
                    } catch (err) {
                        console.error('Error processing field:', field.name, err);
                    }
                });

                // ── Extract custom (default=false) field definitions ──────────
                this.customFieldDefs = ticketFieldsArray
                    .filter(f => f.default === false && f.displayed_to_customers === true)
                    .map(f => {
                        const choices = [];
                        if (Array.isArray(f.choices)) {
                            f.choices.forEach(c => {
                                if (typeof c === 'string') choices.push({ value: c, label: c });
                                else if (c && typeof c === 'object') choices.push({ value: c.value, label: c.label || c.value });
                            });
                        }
                        return {
                            name:     f.name,
                            label:    f.label_for_customers || f.label || f.name,
                            type:     this.normalizeCustomFieldType(f),
                            required: f.required_for_customers === true,
                            choices,
                        };
                    });
            } catch (error) {
                console.error('Failed to load Freshdesk data:', error);
            }
        },

        /**
         * Called by freshdesk-company-select when the resolved company name is known.
         */
        onCompanyNameUpdate(name) {
            this._resolvedCompanyName = name ?? null;
        },

        normalizeCustomFieldType(field) {
            return field?.type || 'custom_text';
        },

        // ── Extra / custom field helpers ──────────────────────────────────────

        /** Get a single custom field value from the submission's extraFields object */
        getExtraField(fieldName) {
            return this.submission?.extraFields?.[fieldName] ?? null;
        },

        /** Set a single custom field value; creates extraFields object if needed */
        setExtraField(fieldName, value) {
            if (!this.submission) return;
            if (!this.submission.extraFields || typeof this.submission.extraFields !== 'object') {
                this.submission.extraFields = {};
            }
            this.submission.extraFields = {
                ...this.submission.extraFields,
                [fieldName]: value,
            };
        },

        /** Convert [{value, label}] choices to sw-single-select options format */
        fieldChoicesToOptions(choices) {
            if (!Array.isArray(choices)) return [];
            return choices.map(c => ({ value: c.value, label: c.label }));
        },
        // ─────────────────────────────────────────────────────────────────────

        onSave() {
            this.isLoading = true;
            this.isSaveSuccessful = false;

            /**
             * Shopware 6.7 / Vue 3 fix for WRITE_TYPE_INTEND_ERROR:
             *
             * repository.create(context) returns an entity with _isNew=true.
             * Vue 3's reactive proxy can lose or shadow that flag, causing
             * repository.save() to dispatch a PATCH (UpdateCommand) instead of
             * a POST (InsertCommand) for brand-new entities.
             *
             * Fix: explicitly force _isNew=true before save() in create mode
             * so the DAL always sends an InsertCommand for new submissions.
             */
            if (this.isCreateMode && this.submission) {
                this.submission._isNew = true;
            }

            this.repository.save(this.submission, Shopware.Context.api)
                .then(() => {
                    this.isLoading = false;
                    this.isSaveSuccessful = true;
                    this.createNotificationSuccess({
                        message: this.$tc('freshdesk.detail.messageSaveSuccess')
                    });
                })
                .catch((error) => {
                    this.isLoading = false;
                    this.createNotificationError({
                        message: this.$tc('freshdesk.detail.messageSaveError')
                    });
                    console.error(error);
                });
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.loadEntity();
        }
    }
});

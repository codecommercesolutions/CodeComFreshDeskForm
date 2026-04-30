const { Mixin } = Shopware;

Shopware.Component.register('freshdesk-custom-field-select', {
    template: `
        <sw-multi-select
            :name="name"
            :label="label"
            :help-text="helpText"
            :placeholder="placeholder"
            :options="options"
            labelProperty="label"
            valueProperty="value"
            :value="value || []"
            :disabled="disabled || isLoading"
            @update:value="onChange"
        />
    `,

    inject: ['freshdeskLookupApiService'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    emits: ['update:value'],

    props: {
        value: {
            type: [Array, String, Object],
            required: false,
            default() {
                return [];
            },
        },
        name: {
            type: String,
            required: false,
            default: '',
        },
        label: {
            type: String,
            required: false,
            default: '',
        },
        helpText: {
            type: String,
            required: false,
            default: '',
        },
        placeholder: {
            type: String,
            required: false,
            default: '',
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            options: [],
            isLoading: false,
            salesChannelUnwatch: null,
        };
    },

    created() {
        this.loadOptions();
    },

    mounted() {
        const systemConfigParent = this.findSystemConfigParent();
        if (systemConfigParent && typeof systemConfigParent.$watch === 'function') {
            this.salesChannelUnwatch = systemConfigParent.$watch('currentSalesChannelId', () => {
                this.loadOptions();
            });
        }
    },

    beforeUnmount() {
        if (typeof this.salesChannelUnwatch === 'function') {
            this.salesChannelUnwatch();
            this.salesChannelUnwatch = null;
        }
    },

    methods: {
        onChange(value) {
            this.$emit('update:value', value || []);
        },

        async loadOptions() {
            this.isLoading = true;

            try {
                const salesChannelId = this.resolveSalesChannelId();
                const metadata = await this.freshdeskLookupApiService.getAllMetadata(salesChannelId);
                const ticketFields = metadata?.ticketFields || [];

                this.options = ticketFields
                    .filter(field => field.default === false)
                    .map(field => ({
                        value: field.name,
                        label: field.label_for_customers || field.label || field.name,
                    }));
            } catch (error) {
                this.options = [];

                this.createNotificationError({
                    title: 'Freshdesk',
                    message: 'Failed to load Freshdesk custom fields.',
                });
            } finally {
                this.isLoading = false;
            }
        },

        resolveSalesChannelId() {
            const parent = this.findSystemConfigParent();
            if (parent && typeof parent.currentSalesChannelId === 'string' && parent.currentSalesChannelId !== '') {
                return parent.currentSalesChannelId;
            }

            const routeSalesChannel = this.$route?.query?.salesChannelId;
            if (typeof routeSalesChannel === 'string' && routeSalesChannel !== '') {
                return routeSalesChannel;
            }

            return null;
        },

        findSystemConfigParent() {
            let current = this.$parent;
            while (current) {
                if (Object.prototype.hasOwnProperty.call(current, 'currentSalesChannelId')) {
                    return current;
                }

                current = current.$parent;
            }

            return null;
        },
    },
});
const { Mixin } = Shopware;

Shopware.Component.register('freshdesk-product-select', {
    template: `
        <sw-single-select
            :name="name"
            :label="label"
            :help-text="helpText"
            :placeholder="placeholder"
            :options="options"
            labelProperty="label"
            valueProperty="value"
            :value="value"
            :error="error"
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
            type: [Number, String],
            required: false,
            default: null,
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
        error: {
            type: Object,
            required: false,
            default: null,
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
            if (value === null || value === '') {
                this.$emit('update:value', null);
                return;
            }

            this.$emit('update:value', Number(value));
        },

        async loadOptions() {
            this.isLoading = true;

            try {
                const salesChannelId = this.resolveSalesChannelId();
                const metadata = await this.freshdeskLookupApiService.getAllMetadata(salesChannelId);
                let products = metadata?.products || [];

                if (!Array.isArray(products) || products.length === 0) {
                    products = await this.freshdeskLookupApiService.getProducts(salesChannelId);
                }

                this.options = Array.isArray(products)
                    ? products
                        .filter((product) => Number(product?.id) > 0)
                        .map((product) => ({
                            value: Number(product.id),
                            label: `${product.name} (#${product.id})`,
                        }))
                    : [];
            } catch (error) {
                this.options = [];

                this.createNotificationError({
                    title: 'Freshdesk',
                    message: 'Failed to load Freshdesk products. Please check API URL/API key for this sales channel.',
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

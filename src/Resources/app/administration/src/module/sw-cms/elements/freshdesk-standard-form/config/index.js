import template from './index.html.twig';
import './index.scss';

const { Mixin, Component } = Shopware;

Component.register('sw-cms-el-config-freshdesk-standard-form', {
    template,

    mixins: [Mixin.getByName('cms-element')],
    
    computed: {
        typeOptions() {
            return [
                { value: 'Request', label: 'Request' },
                { value: 'Offer', label: 'Offer' },
                { value: 'Extent', label: 'Extent' },
                { value: 'Order', label: 'Order' },
                { value: 'Spare part', label: 'Spare part' },
                { value: 'Complaint', label: 'Complaint' },
                { value: 'Repair', label: 'Repair' },
                { value: 'Spam', label: 'Spam' }
            ];
        }
    },
    
    created() {
        this.createdComponent();
    },
    
    methods: {
        createdComponent() {
            this.initElementConfig('freshdesk-standard-form');
        }
    }
});

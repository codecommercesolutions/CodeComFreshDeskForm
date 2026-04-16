import template from './freshdesk-submissions-list.html.twig';
import './freshdesk-submissions-list.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('freshdesk-submissions-list', {
    template,

    inject: ['repositoryFactory'],

    mixins: [Mixin.getByName('listing')],

    data() {
        return {
            submissions: null,
            isLoading: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        repository() {
            return this.repositoryFactory.create('freshdesk_form_submission');
        },

        columns() {
            return [
                {
                    property: 'firstName',
                    label: this.$tc('freshdesk.detail.labelFirstName'),
                    primary: true
                },
                {
                    property: 'lastName',
                    label: this.$tc('freshdesk.detail.labelLastName')
                },
                {
                    property: 'email',
                    label: this.$tc('freshdesk.detail.labelEmail')
                },
                {
                    property: 'subject',
                    label: this.$tc('freshdesk.detail.labelSubject'),
                    allowResize: true
                },
                {
                    property: 'responderId',
                    label: this.$tc('freshdesk.detail.labelResponderId'),
                    allowResize: true
                },
                {
                    property: 'emailConfigId',
                    label: this.$tc('freshdesk.detail.labelEmailConfigId'),
                    allowResize: true
                },
                {
                    property: 'companyId',
                    label: this.$tc('freshdesk.detail.labelCompanyId'),
                    allowResize: true
                },
                {
                    property: 'createdAt',
                    label: 'Created',
                    allowResize: true
                }
            ];
        }
    },

    created() {
        this.getList();
    },

    methods: {
        getList() {
            this.isLoading = true;
            const criteria = new Criteria(this.page, this.limit);
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

            this.repository.search(criteria, Shopware.Context.api).then((result) => {
                this.submissions = result;
                this.total = result.total;
                this.isLoading = false;
            });
        },

        onDelete(id) {
            this.repository.delete(id, Shopware.Context.api).then(() => {
                this.getList();
            });
        },

        onCreate() {
            this.$router.push({ name: 'freshdesk.submissions.create' });
        }
    }
});

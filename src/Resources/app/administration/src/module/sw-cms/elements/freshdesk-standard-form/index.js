import './component';
import './config';
import './preview';

Shopware.Service('cmsService').registerCmsElement({
    name: 'freshdesk-standard-form',
    label: 'sw-cms.elements.freshdeskStandardForm.label',
    component: 'sw-cms-el-freshdesk-standard-form',
    configComponent: 'sw-cms-el-config-freshdesk-standard-form',
    previewComponent: 'sw-cms-el-preview-freshdesk-standard-form',
    defaultConfig: {
        firstNameLabel: {
            source: 'static',
            value: 'First Name'
        },
        lastNameLabel: {
            source: 'static',
            value: 'Last Name'
        },
        emailLabel: {
            source: 'static',
            value: 'Email'
        },
        phoneLabel: {
            source: 'static',
            value: 'Phone'
        },
        typeLabel: {
            source: 'static',
            value: 'Type'
        },
        subjectLabel: {
            source: 'static',
            value: 'Subject'
        },
        descriptionLabel: {
            source: 'static',
            value: 'Description'
        },
        formTitle: {
            source: 'static',
            value: 'Freshdesk Form'
        },
        defaultSubject: {
            source: 'static',
            value: null
        },
        groupId: {
            source: 'static',
            value: null
        },
        productId: {
            source: 'static',
            value: null
        },
        responderId: {
            source: 'static',
            value: null
        },
        emailConfigId: {
            source: 'static',
            value: null
        },
        companyId: {
            source: 'static',
            value: null
        }
    }
});

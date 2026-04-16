import './component';
import './preview';

Shopware.Service('cmsService').registerCmsBlock({
    name: 'freshdesk-standard-form',
    label: 'sw-cms.blocks.freshdeskStandardForm.label',
    category: 'form',
    component: 'sw-cms-block-freshdesk-standard-form',
    previewComponent: 'sw-cms-preview-freshdesk-standard-form',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed'
    },
    slots: {
        content: {
            type: 'freshdesk-standard-form'
        }
    }
});

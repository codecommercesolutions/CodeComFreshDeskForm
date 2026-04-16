import './page/freshdesk-submissions-list';
import './page/freshdesk-submissions-detail';

Shopware.Module.register('freshdesk-submissions', {
    type: 'plugin',
    name: 'FreshdeskStandardForm',
    title: 'freshdesk.general.title',
    description: 'freshdesk.general.description',
    color: '#ff3d58',
    icon: 'regular-content',

    routes: {
        list: {
            component: 'freshdesk-submissions-list',
            path: 'list'
        },
        detail: {
            component: 'freshdesk-submissions-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'freshdesk.submissions.list'
            }
        },
        create: {
            component: 'freshdesk-submissions-detail',
            path: 'create',
            meta: {
                parentPath: 'freshdesk.submissions.list'
            }
        }
    },

    navigation: [{
        label: 'freshdesk.general.title',
        color: '#ff3d58',
        path: 'freshdesk.submissions.list',
        parent: 'sw-customer',
        icon: 'regular-content',
        position: 100
    }]
});

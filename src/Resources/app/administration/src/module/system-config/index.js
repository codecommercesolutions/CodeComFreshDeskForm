import FreshdeskLookupApiService from './service/freshdesk-lookup.api.service';

const { Application } = Shopware;
Application.addServiceProvider('freshdeskLookupApiService', (container) => {
    const initContainer = Application.getContainer('init');
    return new FreshdeskLookupApiService(
        initContainer.httpClient,
        Shopware.Service('loginService')
    );
});

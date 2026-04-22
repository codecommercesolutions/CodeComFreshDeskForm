import template from './api-test-button.html.twig';
import './snippet/en-GB.json';
import './snippet/de-DE.json';

const { Component, Mixin } = Shopware;

/**
 * "Test API Connection" button (admin system-config card).
 *
 * On click it does two things:
 *  1. Tests the Freshdesk API connection (existing behaviour).
 *  2. On success: immediately runs the same sync logic as the 24-hour
 *     FreshdeskApiSyncTask cron — so all dropdown data (agents, groups,
 *     products, ticket_fields, email_configs, companies) is refreshed
 *     right away instead of waiting for the next scheduled run.
 */
Component.register('api-test-button', {
    template,

    inject: ['systemConfigApiService', 'loginService'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            isLoading:        false,
            isSaveSuccessful: false,
        };
    },

    methods: {
        saveFinish() {
            this.isSaveSuccessful = false;
            this.isLoading        = false;
        },

        async checkApi() {
            this.isLoading        = true;
            this.isSaveSuccessful = false;

            try {
                // ── Step 1: validate config fields ──────────────────────────
                const config = await this.systemConfigApiService.getValues('CodeComFreshdeskForm.config');

                if (!config || !config['CodeComFreshdeskForm.config.enabled']) {
                    this._notifyError('Please enable Freshdesk integration first.');
                    return;
                }

                if (!config['CodeComFreshdeskForm.config.apiUrl'] || !config['CodeComFreshdeskForm.config.apiKey']) {
                    this._notifyError(this.$tc('api-test-button.error'));
                    return;
                }

                const headers = {
                    'Content-Type':  'application/json',
                    'Authorization': `Bearer ${this.loginService.getToken()}`,
                };

                // ── Step 2: test the live Freshdesk API connection ───────────
                const testResponse = await fetch(`${Shopware.Context.api.apiPath}/_action/freshdesk/test-connection`, {
                    method:  'POST',
                    headers,
                });
                const testResult = await testResponse.json();

                if (!testResult.success) {
                    this._notifyError(testResult.message ?? this.$tc('api-test-button.error'));
                    return;
                }

                // ── Step 3: run the full sync (same as the 24-hour cron) ─────
                // This ensures the storefront drop-downs are immediately up-to-date
                // after the admin saves new credentials.
                this._notifyInfo(this.$tc('api-test-button.syncing'));

                const syncResponse = await fetch(`${Shopware.Context.api.apiPath}/_action/freshdesk/sync`, {
                    method:  'POST',
                    headers,
                });
                const syncResult = await syncResponse.json();

                if (syncResult.success) {
                    const c = syncResult.counts ?? {};
                    this.isSaveSuccessful = true;
                    this.createNotificationSuccess({
                        title:   this.$tc('api-test-button.title'),
                        message: this.$tc('api-test-button.successWithSync', 0, {
                            agents:       c.agents       ?? 0,
                            groups:       c.groups       ?? 0,
                            products:     c.products     ?? 0,
                            ticketFields: c.ticketFields ?? 0,
                            emailConfigs: c.emailConfigs ?? 0,
                            companies:    c.companies    ?? 0,
                        }),
                    });
                } else {
                    // Connection OK but sync failed — still show partial success
                    this.isSaveSuccessful = true;
                    this.createNotificationWarning({
                        title:   this.$tc('api-test-button.title'),
                        message: this.$tc('api-test-button.successSyncFailed', 0, {
                            error: syncResult.message ?? '',
                        }),
                    });
                }

                setTimeout(() => { this.isSaveSuccessful = false; }, 3000);

            } catch (error) {
                this._notifyError(this.$tc('api-test-button.error'));
                console.error('FreshdeskApiTestButton error:', error);
            } finally {
                this.isLoading = false;
            }
        },

        _notifyError(message) {
            this.createNotificationError({
                title:   this.$tc('api-test-button.title'),
                message,
            });
            this.isLoading = false;
        },

        _notifyInfo(message) {
            this.createNotificationInfo({
                title:   this.$tc('api-test-button.title'),
                message,
            });
        },
    },
});

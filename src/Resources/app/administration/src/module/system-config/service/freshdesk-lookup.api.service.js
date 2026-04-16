class FreshdeskLookupApiService {
    constructor(httpClient, loginService) {
        this.httpClient = httpClient;
        this.loginService = loginService;
    }

    _headers() {
        return { Authorization: `Bearer ${this.loginService.getToken()}` };
    }

    getGroups(salesChannelId = null) {
        return this.httpClient.get('_action/freshdesk/groups', {
            params: { salesChannelId },
            headers: this._headers()
        }).then(response => response.data);
    }

    getProducts(salesChannelId = null) {
        return this.httpClient.get('_action/freshdesk/products', {
            params: { salesChannelId },
            headers: this._headers()
        }).then(response => response.data);
    }

    getTicketFields(salesChannelId = null) {
        return this.httpClient.get('_action/freshdesk/ticket-fields', {
            params: { salesChannelId },
            headers: this._headers()
        }).then(response => response.data);
    }

    getAgents(salesChannelId = null) {
        return this.httpClient.get('_action/freshdesk/agents', {
            params: { salesChannelId },
            headers: this._headers()
        }).then(response => response.data);
    }

    getEmailConfigs(salesChannelId = null) {
        return this.httpClient.get('_action/freshdesk/email-configs', {
            params: { salesChannelId },
            headers: this._headers()
        }).then(response => response.data);
    }

    getCompanies(salesChannelId = null) {
        return this.httpClient.get('_action/freshdesk/companies', {
            params: { salesChannelId },
            headers: this._headers()
        }).then(response => response.data);
    }

    /**
     * Single call that returns ticketFields, products, groups, agents, emailConfigs, companies.
     */
    getAllMetadata(salesChannelId = null) {
        return this.httpClient.get('_action/freshdesk/metadata', {
            params: { salesChannelId },
            headers: this._headers()
        }).then(response => response.data);
    }
}

export default FreshdeskLookupApiService;

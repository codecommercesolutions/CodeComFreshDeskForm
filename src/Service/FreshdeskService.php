<?php

declare(strict_types=1);

namespace CodeCom\FreshdeskForm\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FreshdeskService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly SystemConfigService $systemConfigService,
        private readonly LoggerInterface $logger
    ) {
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Logging helper
    // Routes plugin diagnostics through Shopware's logger.
    // ─────────────────────────────────────────────────────────────────────────
    private function log(string $message): void
    {
        $this->logger->info($message, ['plugin' => 'CodeComFreshdeskForm']);
    }

    private function normalizeCustomFieldType(string $fieldName, string $fieldType): string
    {
        return $fieldType;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Ticket: UPDATE
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * @param int $ticketId
     * @param array<string, mixed> $formData
     * @param string|null $salesChannelId
     * @return array{success: bool, message?: string, data?: array<string, mixed>, ticket_id?: int}
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function updateTicket(int $ticketId, array $formData, ?string $salesChannelId = null): array
    {
        $this->log("updateTicket() called | ticket_id={$ticketId}");

        $enabled = $this->systemConfigService->get('CodeComFreshdeskForm.config.enabled', $salesChannelId);
        if (! $enabled) {
            $this->log('updateTicket() aborted: integration is disabled');
            return ['success' => false, 'message' => 'Freshdesk integration is disabled'];
        }

        $apiUrl = $this->systemConfigService->get('CodeComFreshdeskForm.config.apiUrl', $salesChannelId);
        $apiKey = $this->systemConfigService->get('CodeComFreshdeskForm.config.apiKey', $salesChannelId);

        if (! $apiUrl || ! $apiKey) {
            $this->log('updateTicket() aborted: API URL or key not configured');
            return ['success' => false, 'message' => 'Freshdesk API not configured'];
        }

        $description = '';
        if (! empty($formData['message'])) {
            $description = $formData['message'];
        }

        $ticketData = [
            'description' => $description,
        ];

        if (! empty($formData['phone'])) {
            $ticketData['phone'] = $formData['phone'];
        }
        if (! empty($formData['subject'])) {
            $ticketData['subject'] = $formData['subject'];
        }
        if (! empty($formData['name'])) {
            $ticketData['name'] = $formData['name'];
        }
        if (! empty($formData['type'])) {
            $ticketData['type'] = $formData['type'];
        }
        if (isset($formData['status']) && is_numeric($formData['status'])) {
            $ticketData['status'] = (int) $formData['status'];
        }
        if (isset($formData['priority']) && is_numeric($formData['priority'])) {
            $ticketData['priority'] = (int) $formData['priority'];
        }

        $requester_id = $this->findContactByEmail($formData['email'], $salesChannelId);
        if (! empty($requester_id['id'])) {
            $ticketData['requester_id'] = $requester_id['id'];
        }
        if (! empty($formData['group_id']) && is_numeric($formData['group_id'])) {
            $ticketData['group_id'] = (int) $formData['group_id'];
        }
        if (! empty($formData['product_id']) && is_numeric($formData['product_id'])) {
            $ticketData['product_id'] = (int) $formData['product_id'];
        }
        if (! empty($formData['source']) && is_numeric($formData['source'])) {
            $validSources = [1, 2, 3, 7, 9, 10, 11, 13, 15, 16, 17, 18, 19, 20, 21, 22];
            $sourceVal    = (int) $formData['source'];
            if (in_array($sourceVal, $validSources, true)) {
                $ticketData['source'] = $sourceVal;
            }
        }
        if (! empty($formData['responder_id']) && is_numeric($formData['responder_id'])) {
            $ticketData['responder_id'] = (int) $formData['responder_id'];
        }
        if (! empty($formData['email_config_id']) && is_numeric($formData['email_config_id'])) {
            $ticketData['email_config_id'] = (int) $formData['email_config_id'];
        }
        if (! empty($formData['company_id']) && is_numeric($formData['company_id'])) {
            $ticketData['company_id'] = (int) $formData['company_id'];
        }

        // Custom fields
        if (! empty($formData['_custom_fields']) && is_array($formData['_custom_fields'])) {
            $fieldTypes   = is_array($formData['_custom_field_types'] ?? null) ? $formData['_custom_field_types'] : [];
            $customFields = [];
            foreach ($formData['_custom_fields'] as $cfName => $cfValue) {
                if (! is_string($cfName) || $cfName === '' || $cfValue === '' || $cfValue === null) {
                    continue;
                }
                $fieldType            = $this->normalizeCustomFieldType($cfName, $fieldTypes[$cfName] ?? 'custom_text');
                $customFields[$cfName] = match (true) {
                    $fieldType === 'custom_number'   => (int)   $cfValue,
                    $fieldType === 'custom_decimal'  => (float) $cfValue,
                    $fieldType === 'custom_checkbox' => ($cfValue === 'true' || $cfValue === true || $cfValue === '1'),
                    default                          => (string) $cfValue,
                };
            }
            if (! empty($customFields)) {
                $ticketData['custom_fields'] = $customFields;
            }
        }

        try {
            $url = rtrim(is_string($apiUrl) ? $apiUrl : '', '/') . '/api/v2/tickets/' . $ticketId;
            $this->log("updateTicket() → PUT {$url} | payload=" . json_encode($ticketData));

            $response     = $this->httpClient->request('PUT', $url, [
                'auth_basic' => [is_string($apiKey) ? $apiKey : '', 'X'],
                'headers'    => ['Content-Type' => 'application/json'],
                'json'       => $ticketData,
            ]);
            $statusCode   = $response->getStatusCode();
            $responseData = $response->toArray(false);

            $this->log("updateTicket() ← HTTP {$statusCode} | response=" . json_encode($responseData));

            if ($statusCode === 200) {
                $this->log("updateTicket() SUCCESS | ticket_id={$ticketId}");
                return [
                    'success'   => true,
                    'data'      => $responseData,
                    'message'   => 'Ticket updated successfully',
                    'ticket_id' => $responseData['id'] ?? null,
                ];
            }

            $this->log("updateTicket() FAILED | HTTP {$statusCode} | ticket_id={$ticketId}");
            return [
                'success'   => false,
                'message'   => 'Failed to update ticket: ' . json_encode($responseData),
                'data_sent' => $ticketData,
            ];
        } catch (\Exception $e) {
            $this->log("updateTicket() EXCEPTION | ticket_id={$ticketId} | " . $e->getMessage());
            return [
                'success'   => false,
                'message'   => 'API Error: ' . $e->getMessage(),
                'data_sent' => $ticketData,
            ];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Ticket: CREATE
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * @param array<string, mixed> $formData
     * @param string|null $salesChannelId
     * @return array{success: bool, message?: string, data?: array<string, mixed>, ticket_id?: int}
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function createTicket(array $formData, ?string $salesChannelId = null): array
    {
        $this->log("createTicket() called | email=" . ($formData['email'] ?? '-'));

        $enabled = $this->systemConfigService->get('CodeComFreshdeskForm.config.enabled', $salesChannelId);
        if (! $enabled) {
            $this->log('createTicket() aborted: integration is disabled');
            return ['success' => false, 'message' => 'Freshdesk integration is disabled'];
        }

        $apiUrl = $this->systemConfigService->get('CodeComFreshdeskForm.config.apiUrl', $salesChannelId);
        $apiKey = $this->systemConfigService->get('CodeComFreshdeskForm.config.apiKey', $salesChannelId);

        if (! $apiUrl || ! $apiKey) {
            $this->log('createTicket() aborted: API URL or key not configured');
            return ['success' => false, 'message' => 'Freshdesk API not configured'];
        }

        $description = ! empty($formData['message']) ? $formData['message'] : '';

        $subject = !empty($formData['subject']) ? $formData['subject'] : $this->systemConfigService->get('CodeComFreshdeskForm.config.defaultSubject', $salesChannelId);
        $type = !empty($formData['type']) ? $formData['type'] : $this->systemConfigService->get('CodeComFreshdeskForm.config.defaultTicketType', $salesChannelId);

        // Map English defaults to German if necessary (Duscholux Freshdesk expects German labels)
        $typeMapping = [
            'Request'    => 'Anfrage',
            'Offer'      => 'Angebot',
            'Extent'     => 'Ausmass',
            'Order'      => 'Bestellung',
            'Spare part' => 'Ersatzteil',
            'Complaint'  => 'Reklamation',
            'Repair'     => 'Reparatur',
            'Spam'       => 'Spam',
        ];

        if (isset($typeMapping[$type])) {
            $type = $typeMapping[$type];
        }

        $ticketData = [
            'email'       => $formData['email']    ?? '',
            'subject'     => $subject ?: 'Freshdesk Webshop Request',
            'description' => $description,
        ];

        if (! empty($formData['phone'])) {
            $ticketData['phone'] = $formData['phone'];
        }
        if (! empty($formData['name'])) {
            $ticketData['name'] = $formData['name'];
        }
        if (! empty($type)) {
            $ticketData['type'] = $type;
        }
        if (isset($formData['status']) && is_numeric($formData['status'])) {
            $ticketData['status'] = (int) $formData['status'];
        }
        if (isset($formData['priority']) && is_numeric($formData['priority'])) {
            $ticketData['priority'] = (int) $formData['priority'];
        }
        if (! empty($formData['group_id']) && is_numeric($formData['group_id'])) {
            $ticketData['group_id'] = (int) $formData['group_id'];
        }
        if (! empty($formData['product_id']) && is_numeric($formData['product_id'])) {
            $ticketData['product_id'] = (int) $formData['product_id'];
        }
        if (! empty($formData['source']) && is_numeric($formData['source'])) {
            $validSources = [1, 2, 3, 7, 9, 10, 11, 13, 15, 16, 17, 18, 19, 20, 21, 22];
            $sourceVal    = (int) $formData['source'];
            if (in_array($sourceVal, $validSources, true)) {
                $ticketData['source'] = $sourceVal;
            }
        }
        if (! empty($formData['responder_id']) && is_numeric($formData['responder_id'])) {
            $ticketData['responder_id'] = (int) $formData['responder_id'];
        }
        if (! empty($formData['email_config_id']) && is_numeric($formData['email_config_id'])) {
            $ticketData['email_config_id'] = (int) $formData['email_config_id'];
        }
        if (! empty($formData['company_id']) && is_numeric($formData['company_id'])) {
            $ticketData['company_id'] = (int) $formData['company_id'];
        }

        // Custom fields
        if (! empty($formData['_custom_fields']) && is_array($formData['_custom_fields'])) {
            $fieldTypes   = is_array($formData['_custom_field_types'] ?? null) ? $formData['_custom_field_types'] : [];
            $customFields = [];
            foreach ($formData['_custom_fields'] as $cfName => $cfValue) {
                if (! is_string($cfName) || $cfName === '' || $cfValue === '' || $cfValue === null) {
                    continue;
                }
                $fieldType            = $this->normalizeCustomFieldType($cfName, $fieldTypes[$cfName] ?? 'custom_text');
                $customFields[$cfName] = match (true) {
                    $fieldType === 'custom_number'   => (int)   $cfValue,
                    $fieldType === 'custom_decimal'  => (float) $cfValue,
                    $fieldType === 'custom_checkbox' => ($cfValue === 'true' || $cfValue === true || $cfValue === '1'),
                    default                          => (string) $cfValue,
                };
            }
            if (! empty($customFields)) {
                $ticketData['custom_fields'] = $customFields;
            }
        }

        try {
            $url = rtrim(is_string($apiUrl) ? $apiUrl : '', '/') . '/api/v2/tickets';
            $this->log("createTicket() → POST {$url} | payload=" . json_encode($ticketData));

            $response     = $this->httpClient->request('POST', $url, [
                'auth_basic' => [is_string($apiKey) ? $apiKey : '', 'X'],
                'headers'    => ['Content-Type' => 'application/json'],
                'json'       => $ticketData,
            ]);
            $statusCode   = $response->getStatusCode();
            $responseData = $response->toArray(false);

            $this->log("createTicket() ← HTTP {$statusCode} | response=" . json_encode($responseData));

            if ($statusCode === 201) {
                $newId = $responseData['id'] ?? null;
                $this->log("createTicket() SUCCESS | new ticket_id={$newId}");
                return [
                    'success'   => true,
                    'data'      => $responseData,
                    'message'   => 'Ticket created successfully',
                    'ticket_id' => $newId,
                ];
            }

            $this->log("createTicket() FAILED | HTTP {$statusCode}");
            return [
                'success'   => false,
                'message'   => 'Failed to create ticket: ' . json_encode($responseData),
                'data_sent' => $ticketData,
            ];
        } catch (\Exception $e) {
            $this->log("createTicket() EXCEPTION | " . $e->getMessage());
            return [
                'success'   => false,
                'message'   => 'API Error: ' . $e->getMessage(),
                'data_sent' => $ticketData,
            ];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Ticket fields / metadata
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get all ticket fields with their choices (status, priority, type, source, etc.)
     * @param string|null $salesChannelId
     * @return array<int, mixed>
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getTicketFields(?string $salesChannelId = null): array
    {
        $this->log('getTicketFields() called');
        $result = $this->fetchFromApi('/api/v2/ticket_fields', $salesChannelId);
        $this->log('getTicketFields() returned ' . count($result) . ' fields');
        return $result;
    }

    /**
     * @param string|null $salesChannelId
     * @return array<int, mixed>
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getAgents(?string $salesChannelId = null): array
    {
        $this->log('getAgents() called');
        $result = $this->fetchFromApi('/api/v2/agents', $salesChannelId);
        $this->log('getAgents() returned ' . count($result) . ' agents');
        return $result;
    }

    /**
     * @param string|null $salesChannelId
     * @return array<int, mixed>
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getEmailConfigs(?string $salesChannelId = null): array
    {
        $this->log('getEmailConfigs() called');
        $result = $this->fetchFromApi('/api/v2/email_configs', $salesChannelId);
        $this->log('getEmailConfigs() returned ' . count($result) . ' configs');
        return $result;
    }

    /**
     * @param string|null $salesChannelId
     * @return array<int, mixed>
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getCompanies(?string $salesChannelId = null): array
    {
        $this->log('getCompanies() called');
        $result = $this->fetchFromApi('/api/v2/companies', $salesChannelId);
        $this->log('getCompanies() returned ' . count($result) . ' companies');
        return $result;
    }

    /**
     * @param string|null $salesChannelId
     * @return array<int, mixed>
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getGroups(?string $salesChannelId = null): array
    {
        $this->log('getGroups() called');
        $result = $this->fetchFromApi('/api/v2/groups', $salesChannelId);
        $this->log('getGroups() returned ' . count($result) . ' groups');
        return $result;
    }

    /**
     * @param string|null $salesChannelId
     * @return array<int, mixed>
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getProducts(?string $salesChannelId = null): array
    {
        $this->log('getProducts() called');
        $result = $this->fetchFromApi('/api/v2/products', $salesChannelId);
        $this->log('getProducts() returned ' . count($result) . ' products');
        return $result;
    }

    /**
     * @param string|null $salesChannelId
     * @return array<int|string, mixed>
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getTicketTypes(?string $salesChannelId = null): array
    {
        $this->log('getTicketTypes() called');
        $fields = $this->fetchFromApi('/api/v2/admin/ticket_fields', $salesChannelId);

        foreach ($fields as $field) {
            if (is_array($field) && isset($field['name'], $field['id']) && $field['name'] === 'ticket_type') {
                $typeField = $this->fetchFromApi('/api/v2/admin/ticket_fields/' . $field['id'], $salesChannelId);
                if (isset($typeField['choices']) && is_array($typeField['choices'])) {
                    $this->log('getTicketTypes() found ' . count($typeField['choices']) . ' types');
                    return $typeField['choices'];
                }
            }
        }

        $this->log('getTicketTypes() no ticket_type field found');
        return [];
    }

    /**
     * Get all metadata in one call (ticket fields, products, groups, agents, email_configs, companies)
     * @param string|null $salesChannelId
     * @return array{ticketFields: array, products: array, groups: array, agents: array, emailConfigs: array, companies: array}
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getAllMetadata(?string $salesChannelId = null): array
    {
        $this->log('getAllMetadata() called');
        return [
            'ticketFields' => $this->getTicketFields($salesChannelId),
            'products'     => $this->getProducts($salesChannelId),
            'groups'       => $this->getGroups($salesChannelId),
            'agents'       => $this->getAgents($salesChannelId),
            'emailConfigs' => $this->getEmailConfigs($salesChannelId),
            'companies'    => $this->getCompanies($salesChannelId),
        ];
    }

    /**
     * @param string|null $salesChannelId
     * @return array<int, mixed>
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getAllTickets(?string $salesChannelId = null): array
    {
        $this->log('getAllTickets() called');
        $result = $this->fetchFromApi('/api/v2/tickets', $salesChannelId);
        $this->log('getAllTickets() returned ' . count($result) . ' tickets');
        return $result;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Connection test
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param string|null $salesChannelId
     * @return array{success: bool, message: string}
     * @throws TransportExceptionInterface
     */
    public function testConnection(?string $salesChannelId = null): array
    {
        $this->log('testConnection() called');

        $apiUrl = $this->systemConfigService->get('CodeComFreshdeskForm.config.apiUrl', $salesChannelId);
        $apiKey = $this->systemConfigService->get('CodeComFreshdeskForm.config.apiKey', $salesChannelId);

        if (! $apiUrl || ! $apiKey) {
            $this->log('testConnection() aborted: credentials not configured');
            return ['success' => false, 'message' => 'API credentials not configured'];
        }

        try {
            $url = rtrim(is_string($apiUrl) ? $apiUrl : '', '/') . '/api/v2/tickets';
            $this->log("testConnection() → GET {$url}");

            $response   = $this->httpClient->request('GET', $url, [
                'auth_basic' => [is_string($apiKey) ? $apiKey : '', 'X'],
            ]);
            $statusCode = $response->getStatusCode();

            $this->log("testConnection() ← HTTP {$statusCode}");

            $success = $statusCode === 200;
            $this->log('testConnection() result: ' . ($success ? 'SUCCESS' : 'FAILED'));

            return [
                'success' => $success,
                'message' => $success ? 'API connection successful' : "API connection failed (HTTP {$statusCode})",
            ];
        } catch (\Exception $e) {
            $this->log('testConnection() EXCEPTION | ' . $e->getMessage());
            return ['success' => false, 'message' => 'API connection failed: ' . $e->getMessage()];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Company helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Fetch a single Freshdesk company by its numeric ID.
     * @param int $companyId
     * @param string|null $salesChannelId
     * @return array<string, mixed>|null
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function getCompanyById(int $companyId, ?string $salesChannelId = null): ?array
    {
        $this->log("getCompanyById() called | company_id={$companyId}");

        $apiUrl = $this->systemConfigService->get('CodeComFreshdeskForm.config.apiUrl', $salesChannelId);
        $apiKey = $this->systemConfigService->get('CodeComFreshdeskForm.config.apiKey', $salesChannelId);

        if (! $apiUrl || ! $apiKey) {
            $this->log('getCompanyById() aborted: API not configured');
            return null;
        }

        try {
            $url = rtrim(is_string($apiUrl) ? $apiUrl : '', '/') . '/api/v2/companies/' . $companyId;
            $this->log("getCompanyById() → GET {$url}");

            $response   = $this->httpClient->request('GET', $url, [
                'auth_basic' => [is_string($apiKey) ? $apiKey : '', 'X'],
            ]);
            $statusCode = $response->getStatusCode();

            $this->log("getCompanyById() ← HTTP {$statusCode}");

            if ($statusCode !== 200) {
                $this->log("getCompanyById() FAILED | HTTP {$statusCode}");
                return null;
            }

            $data = $response->toArray(false);
            if (isset($data['id'], $data['name'])) {
                $this->log("getCompanyById() found: id={$data['id']} name={$data['name']}");
                return ['id' => $data['id'], 'name' => $data['name']];
            }

            $this->log('getCompanyById() response missing id/name');
            return null;
        } catch (\Exception $e) {
            $this->log('getCompanyById() EXCEPTION | ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Search Freshdesk companies by name.
     * @return array<int, mixed>
     */
    public function searchCompaniesByName(string $query, ?string $salesChannelId = null): array
    {
        $this->log("searchCompaniesByName() called | query={$query}");

        if (trim($query) === '') {
            $this->log('searchCompaniesByName() aborted: empty query');
            return [];
        }

        $apiUrl = $this->systemConfigService->get('CodeComFreshdeskForm.config.apiUrl', $salesChannelId);
        $apiKey = $this->systemConfigService->get('CodeComFreshdeskForm.config.apiKey', $salesChannelId);

        if (! $apiUrl || ! $apiKey) {
            $this->log('searchCompaniesByName() aborted: API not configured');
            return [];
        }

        try {
            $url = rtrim(is_string($apiUrl) ? $apiUrl : '', '/') . '/api/v2/search/companies?term=' . urlencode($query);
            $this->log("searchCompaniesByName() → GET {$url}");

            $response = $this->httpClient->request('GET', $url, [
                'auth_basic' => [is_string($apiKey) ? $apiKey : '', 'X'],
            ]);
            $data     = $response->toArray(false);

            $this->log("searchCompaniesByName() ← HTTP " . $response->getStatusCode());

            if (isset($data['results']) && is_array($data['results'])) {
                $this->log('searchCompaniesByName() found ' . count($data['results']) . ' result(s)');
                return $data['results'];
            }
            if (is_array($data) && isset($data[0])) {
                $this->log('searchCompaniesByName() found ' . count($data) . ' result(s) (flat array)');
                return $data;
            }

            $this->log('searchCompaniesByName() no results');
            return [];
        } catch (\Exception $e) {
            $this->log('searchCompaniesByName() EXCEPTION | ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Create a new Freshdesk company.
     * @param string $name
     * @param string|null $salesChannelId
     * @return array{success: bool, id?: int, name?: string, message?: string}
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function createFreshdeskCompany(string $name, ?string $salesChannelId = null): array
    {
        $this->log("createFreshdeskCompany() called | name={$name}");

        $apiUrl = $this->systemConfigService->get('CodeComFreshdeskForm.config.apiUrl', $salesChannelId);
        $apiKey = $this->systemConfigService->get('CodeComFreshdeskForm.config.apiKey', $salesChannelId);

        if (! $apiUrl || ! $apiKey) {
            $this->log('createFreshdeskCompany() aborted: API not configured');
            return ['success' => false, 'message' => 'API not configured'];
        }

        try {
            $url = rtrim(is_string($apiUrl) ? $apiUrl : '', '/') . '/api/v2/companies';
            $this->log("createFreshdeskCompany() → POST {$url} | name={$name}");

            $response     = $this->httpClient->request('POST', $url, [
                'auth_basic' => [is_string($apiKey) ? $apiKey : '', 'X'],
                'headers'    => ['Content-Type' => 'application/json'],
                'json'       => ['name' => $name],
            ]);
            $statusCode   = $response->getStatusCode();
            $responseData = $response->toArray(false);

            $this->log("createFreshdeskCompany() ← HTTP {$statusCode}");

            if ($statusCode === 201) {
                $this->log("createFreshdeskCompany() SUCCESS | id=" . ($responseData['id'] ?? '-'));
                return [
                    'success' => true,
                    'id'      => $responseData['id'] ?? null,
                    'name'    => $responseData['name'] ?? $name,
                ];
            }

            $this->log("createFreshdeskCompany() FAILED | HTTP {$statusCode} | " . json_encode($responseData));
            return ['success' => false, 'message' => 'Create company failed: ' . json_encode($responseData)];
        } catch (\Exception $e) {
            $this->log('createFreshdeskCompany() EXCEPTION | ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Contact helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Find a Freshdesk contact by email address.
     * @param string $email
     * @param string|null $salesChannelId
     * @return array<string, mixed>|null
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function findContactByEmail(string $email, ?string $salesChannelId = null): ?array
    {
        $this->log("findContactByEmail() called | email={$email}");

        $apiUrl = $this->systemConfigService->get('CodeComFreshdeskForm.config.apiUrl', $salesChannelId);
        $apiKey = $this->systemConfigService->get('CodeComFreshdeskForm.config.apiKey', $salesChannelId);

        if (! $apiUrl || ! $apiKey) {
            $this->log('findContactByEmail() aborted: API not configured');
            return null;
        }

        try {
            $url = rtrim(is_string($apiUrl) ? $apiUrl : '', '/') . '/api/v2/contacts?email=' . urlencode($email);
            $this->log("findContactByEmail() → GET {$url}");

            $response = $this->httpClient->request('GET', $url, [
                'auth_basic' => [is_string($apiKey) ? $apiKey : '', 'X'],
            ]);
            $data     = $response->toArray(false);

            $this->log("findContactByEmail() ← HTTP " . $response->getStatusCode());

            if (is_array($data) && isset($data[0]) && is_array($data[0])) {
                $this->log("findContactByEmail() contact found | id=" . ($data[0]['id'] ?? '-'));
                return $data[0];
            }
            if (is_array($data) && isset($data['id'])) {
                $this->log("findContactByEmail() contact found | id=" . $data['id']);
                return $data;
            }

            $this->log("findContactByEmail() no contact found for email={$email}");
            return null;
        } catch (\Exception $e) {
            $this->log('findContactByEmail() EXCEPTION | ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Update a Freshdesk contact (PUT /api/v2/contacts/:id).
     * @param int $contactId
     * @param array<string, mixed> $data
     * @param string|null $salesChannelId
     * @return array{success: bool, message?: string}
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function updateFreshdeskContact(int $contactId, array $data, ?string $salesChannelId = null): array
    {
        $this->log("updateFreshdeskContact() called | contact_id={$contactId} | data=" . json_encode($data));

        $apiUrl = $this->systemConfigService->get('CodeComFreshdeskForm.config.apiUrl', $salesChannelId);
        $apiKey = $this->systemConfigService->get('CodeComFreshdeskForm.config.apiKey', $salesChannelId);

        if (! $apiUrl || ! $apiKey) {
            $this->log('updateFreshdeskContact() aborted: API not configured');
            return ['success' => false, 'message' => 'API not configured'];
        }

        try {
            $url = rtrim(is_string($apiUrl) ? $apiUrl : '', '/') . '/api/v2/contacts/' . $contactId;
            $this->log("updateFreshdeskContact() → PUT {$url}");

            $response   = $this->httpClient->request('PUT', $url, [
                'auth_basic' => [is_string($apiKey) ? $apiKey : '', 'X'],
                'headers'    => ['Content-Type' => 'application/json'],
                'json'       => $data,
            ]);
            $statusCode = $response->getStatusCode();

            $responseBody = $response->toArray(false);
            $this->log("updateFreshdeskContact() ← HTTP {$statusCode} | body=" . json_encode($responseBody));

            if ($statusCode === 200) {
                $this->log("updateFreshdeskContact() SUCCESS | contact_id={$contactId}");
                return ['success' => true];
            }

            $this->log("updateFreshdeskContact() FAILED | HTTP {$statusCode} | body=" . json_encode($responseBody));
            return ['success' => false, 'message' => 'Update contact failed: HTTP ' . $statusCode . ' | ' . json_encode($responseBody)];
        } catch (\Exception $e) {
            $this->log('updateFreshdeskContact() EXCEPTION | ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Create or update a Freshdesk contact from the Shopware registration flow.
     *
     * @return array{success: bool, id?: int|null, created?: bool, message?: string}
     */
    public function createOrUpdateRegistrationContact(
        string $email,
        ?string $salesChannelId = null,
        ?string $name = null,
        ?string $phone = null,
        ?string $address = null
    ): array {
        $this->log("createOrUpdateRegistrationContact() called | email={$email} | name={$name} | address={$address}");

        $email = trim($email);
        if ($email === '') {
            $this->log('createOrUpdateRegistrationContact() aborted: empty email');
            return ['success' => false, 'message' => 'Email is required'];
        }

        $existingContact = $this->findContactByEmail($email, $salesChannelId);
        if ($existingContact !== null && !empty($existingContact['id'])) {
            $updateData = [];

            if (!empty($name)) {
                $updateData['name'] = $name;
            }

            if (!empty($phone)) {
                $updateData['phone'] = $phone;
            }

            if (!empty($address)) {
                $updateData['address'] = $address;
            }

            if ($updateData === []) {
                $this->log("createOrUpdateRegistrationContact() contact already exists with no updates needed | contact_id={$existingContact['id']}");
                return [
                    'success' => true,
                    'id' => (int) $existingContact['id'],
                    'created' => false,
                    'message' => 'Contact already exists',
                ];
            }

            $updateResult = $this->updateFreshdeskContact((int) $existingContact['id'], $updateData, $salesChannelId);

            return [
                'success' => $updateResult['success'],
                'id' => (int) $existingContact['id'],
                'created' => false,
                'message' => $updateResult['message'] ?? 'Contact updated successfully',
            ];
        }

        $apiUrl = $this->systemConfigService->get('CodeComFreshdeskForm.config.apiUrl', $salesChannelId);
        $apiKey = $this->systemConfigService->get('CodeComFreshdeskForm.config.apiKey', $salesChannelId);

        if (! $apiUrl || ! $apiKey) {
            $this->log('createOrUpdateRegistrationContact() aborted: API not configured');
            return ['success' => false, 'message' => 'API not configured'];
        }

        $payload = ['email' => $email];

        if (!empty($name)) {
            $payload['name'] = $name;
        }

        if (!empty($phone)) {
            $payload['phone'] = $phone;
        }

        if (!empty($address)) {
            $payload['address'] = $address;
        }

        try {
            $url = rtrim(is_string($apiUrl) ? $apiUrl : '', '/') . '/api/v2/contacts';
            $this->log("createOrUpdateRegistrationContact() → POST {$url} | payload=" . json_encode($payload));

            $response = $this->httpClient->request('POST', $url, [
                'auth_basic' => [is_string($apiKey) ? $apiKey : '', 'X'],
                'headers' => ['Content-Type' => 'application/json'],
                'json' => $payload,
            ]);
            $statusCode = $response->getStatusCode();
            $responseData = $response->toArray(false);

            $this->log("createOrUpdateRegistrationContact() ← HTTP {$statusCode} | response=" . json_encode($responseData));

            if ($statusCode === 201) {
                return [
                    'success' => true,
                    'id' => isset($responseData['id']) ? (int) $responseData['id'] : null,
                    'created' => true,
                    'message' => 'Contact created successfully',
                ];
            }

            if ($statusCode === 409) {
                $this->log("createOrUpdateRegistrationContact() HTTP 409 | retrying as update for email={$email}");
                $existingContact = $this->findContactByEmail($email, $salesChannelId);

                if ($existingContact !== null && !empty($existingContact['id'])) {
                    $updateData = [];

                    if (!empty($name)) {
                        $updateData['name'] = $name;
                    }

                    if (!empty($phone)) {
                        $updateData['phone'] = $phone;
                    }

                    if (!empty($address)) {
                        $updateData['address'] = $address;
                    }

                    if ($updateData !== []) {
                        $updateResult = $this->updateFreshdeskContact((int) $existingContact['id'], $updateData, $salesChannelId);

                        return [
                            'success' => $updateResult['success'],
                            'id' => (int) $existingContact['id'],
                            'created' => false,
                            'message' => $updateResult['message'] ?? 'Existing contact updated after duplicate response',
                        ];
                    }

                    return [
                        'success' => true,
                        'id' => (int) $existingContact['id'],
                        'created' => false,
                        'message' => 'Contact already exists',
                    ];
                }
            }

            return [
                'success' => false,
                'message' => 'Failed to create contact: ' . json_encode($responseData),
            ];
        } catch (\Exception $e) {
            $this->log('createOrUpdateRegistrationContact() EXCEPTION | ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Create a new Freshdesk contact with the given email and company_id.
     * Called when a contact does not exist yet — we pre-create it with company_id
     * so that the subsequent createTicket() call finds the requester already
     * belonging to the right company (otherwise Freshdesk rejects company_id).
     *
     * @param string $email
     * @param int $companyId
     * @param string|null $salesChannelId
     * @param string|null $name
     * @param string|null $phone
     * @param string|null $facebookId
     * @param string|null $twitterId
     * @param string|null $uniqueExternalId
     * @return array{success: bool, id?: int, message?: string}
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function createFreshdeskContact(
        string $email,
        int $companyId,
        ?string $salesChannelId = null,
        ?string $name = null,
        ?string $phone = null,
        ?string $facebookId = null,
        ?string $twitterId = null,
        ?string $uniqueExternalId = null
    ): array {
        $this->log("createFreshdeskContact() called | email={$email} | company_id={$companyId} | name={$name}");

        $apiUrl = $this->systemConfigService->get('CodeComFreshdeskForm.config.apiUrl', $salesChannelId);
        $apiKey = $this->systemConfigService->get('CodeComFreshdeskForm.config.apiKey', $salesChannelId);

        if (! $apiUrl || ! $apiKey) {
            $this->log('createFreshdeskContact() aborted: API not configured');
            return ['success' => false, 'message' => 'API not configured'];
        }

        // POST /api/v2/contacts — send only non-unique fields here.
        // facebook_id / twitter_id / unique_external_id are "Unique" fields in
        // Freshdesk: including them in the CREATE payload can cause HTTP 400 if
        // the value is already used by another contact.
        // We create the contact first, then update identity fields one-by-one.
        $payload = [
            'email'      => $email,
            'company_id' => $companyId,
        ];

        if (! empty($name)) {
            $payload['name'] = $name;
        }

        if (! empty($phone)) {
            $payload['mobile'] = $phone;   // map phone → mobile field
            $this->log("createFreshdeskContact() adding mobile={$phone}");
        }

        try {
            $url = rtrim(is_string($apiUrl) ? $apiUrl : '', '/') . '/api/v2/contacts';
            $this->log("createFreshdeskContact() → POST {$url} | payload=" . json_encode($payload));

            $response     = $this->httpClient->request('POST', $url, [
                'auth_basic' => [is_string($apiKey) ? $apiKey : '', 'X'],
                'headers'    => ['Content-Type' => 'application/json'],
                'json'       => $payload,
            ]);
            $statusCode   = $response->getStatusCode();
            $responseData = $response->toArray(false);

            $this->log("createFreshdeskContact() ← HTTP {$statusCode}");

            if ($statusCode === 201) {
                $newId = $responseData['id'] ?? null;
                $this->log("createFreshdeskContact() SUCCESS | new contact_id={$newId}");

                // NOW update identity fields one-by-one on the newly created contact
                if ($newId !== null) {
                    $this->updateContactIdentityFields(
                        (int) $newId,
                        $facebookId,
                        $twitterId,
                        $uniqueExternalId,
                        $salesChannelId
                    );
                }

                return [
                    'success' => true,
                    'id'      => $newId,
                    'message' => 'Contact created successfully with company_id',
                ];
            }

            // HTTP 409 = contact already exists (race condition) — update instead
            if ($statusCode === 409) {
                $this->log("createFreshdeskContact() HTTP 409 (contact already exists) | trying update | email={$email}");
                $existing = $this->findContactByEmail($email, $salesChannelId);
                if ($existing && ! empty($existing['id'])) {
                    $existingId = (int) $existing['id'];
                    // Update company_id + name + mobile (non-unique fields together)
                    $updateData = ['company_id' => $companyId];
                    if (! empty($name)) {
                        $updateData['name'] = $name;
                    }
                    if (! empty($phone)) {
                        $updateData['mobile'] = $phone;
                    }
                    $this->updateFreshdeskContact($existingId, $updateData, $salesChannelId);
                    // Then identity fields separately
                    $this->updateContactIdentityFields(
                        $existingId,
                        $facebookId,
                        $twitterId,
                        $uniqueExternalId,
                        $salesChannelId
                    );
                    $this->log("createFreshdeskContact() 409 fallback UPDATE SUCCESS | contact_id={$existingId}");
                    return [
                        'success' => true,
                        'id'      => $existing['id'],
                        'message' => 'Contact already existed; updated company_id, name, mobile and identity fields',
                    ];
                }
                $this->log('createFreshdeskContact() 409 fallback failed: could not find existing contact');
            }

            $this->log("createFreshdeskContact() FAILED | HTTP {$statusCode} | " . json_encode($responseData));
            return [
                'success' => false,
                'message' => 'Failed to create contact: ' . json_encode($responseData),
            ];
        } catch (\Exception $e) {
            $this->log('createFreshdeskContact() EXCEPTION | ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Orchestration: resolve company + ensure contact is linked
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Resolve or create a Freshdesk company and ensure the contact's company_id
     * is set BEFORE ticket creation.
     *
     * @param string $email
     * @param string|null $companyId
     * @param string|null $companyName
     * @param string|null $salesChannelId
     * @param string|null $contactName
     * @param string|null $contactPhone
     * @param string|null $facebookId
     * @param string|null $twitterId
     * @param string|null $uniqueExternalId
     * @return array{success: bool, companyId?: int, companyName?: string, message?: string}
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function resolveCompanyAndUpdateContact(
        string $email,
        ?string $companyId,
        ?string $companyName,
        ?string $salesChannelId = null,
        ?string $contactName = null,
        ?string $contactPhone = null,
        ?string $facebookId = null,
        ?string $twitterId = null,
        ?string $uniqueExternalId = null
    ): array {
        $this->log("resolveCompanyAndUpdateContact() called | email={$email} | company_id={$companyId} | company_name={$companyName}");

        $resolvedId   = null;
        $resolvedName = null;

        // Step 1: Resolve company ID
        if (! empty($companyId) && is_numeric($companyId)) {
            $resolvedId   = (int) $companyId;
            $resolvedName = $companyName ?? '';
            $this->log("resolveCompanyAndUpdateContact() using provided company_id={$resolvedId}");
        } elseif (! empty($companyName)) {
            $companyName = trim($companyName);
            $this->log("resolveCompanyAndUpdateContact() searching company by name='{$companyName}'");
            $found   = $this->searchCompaniesByName($companyName, $salesChannelId);
            $matched = null;
            foreach ($found as $c) {
                if (
                    is_array($c)
                    && isset($c['name'])
                    && strcasecmp(trim((string) $c['name']), $companyName) === 0
                ) {
                    $matched = $c;
                    break;
                }
            }

            if ($matched !== null) {
                $resolvedId   = (int) $matched['id'];
                $resolvedName = $matched['name'];
                $this->log("resolveCompanyAndUpdateContact() matched existing company id={$resolvedId}");
            } else {
                $this->log("resolveCompanyAndUpdateContact() no match found, creating new company='{$companyName}'");
                $createResult = $this->createFreshdeskCompany($companyName, $salesChannelId);
                if (! $createResult['success']) {
                    $this->log("resolveCompanyAndUpdateContact() company creation FAILED | " . ($createResult['message'] ?? ''));
                    return ['success' => false, 'message' => 'Could not create company: ' . ($createResult['message'] ?? '')];
                }
                $resolvedId   = (int) $createResult['id'];
                $resolvedName = $createResult['name'] ?? $companyName;
                $this->log("resolveCompanyAndUpdateContact() company created | id={$resolvedId}");
            }
        }

        if ($resolvedId === null) {
            $this->log('resolveCompanyAndUpdateContact() aborted: no company specified');
            return ['success' => false, 'message' => 'No company specified'];
        }

        // Step 2: Find or create contact, then ensure company_id is set
        $this->log("resolveCompanyAndUpdateContact() looking up contact | email={$email}");
        $contact = $this->findContactByEmail($email, $salesChannelId);

        if ($contact === null || empty($contact['id'])) {
            // New contact — create with company_id pre-assigned
            $this->log("resolveCompanyAndUpdateContact() contact not found, creating | email={$email} | company_id={$resolvedId}");
            $createContactResult = $this->createFreshdeskContact(
                $email,
                $resolvedId,
                $salesChannelId,
                $contactName,
                $contactPhone,
                $facebookId,
                $twitterId,
                $uniqueExternalId
            );

            $this->log("resolveCompanyAndUpdateContact() contact create result: success=" . ($createContactResult['success'] ? 'true' : 'false') . ' | ' . ($createContactResult['message'] ?? ''));
            return [
                'success'     => $createContactResult['success'],
                'companyId'   => $resolvedId,
                'companyName' => $resolvedName,
                'message'     => $createContactResult['message'] ?? 'Contact created with company_id pre-assigned',
            ];
        }

        // Existing contact — Step A: update company_id (must succeed for ticket to work)
        $this->log("resolveCompanyAndUpdateContact() contact found id={$contact['id']}, updating company_id={$resolvedId}");
        $updateResult = $this->updateFreshdeskContact(
            (int) $contact['id'],
            ['company_id' => $resolvedId],
            $salesChannelId
        );
        $this->log("resolveCompanyAndUpdateContact() company_id update: success=" . ($updateResult['success'] ? 'true' : 'false'));

        // Step B: update identity fields one-by-one BEFORE ticket creation.
        // These are "Unique" in Freshdesk — sending them together with other
        // fields can cause HTTP 400 if one conflicts. We send each separately
        // so a conflict on one field does not block the others.
        $this->updateContactIdentityFields(
            (int) $contact['id'],
            $facebookId,
            $twitterId,
            $uniqueExternalId,
            $salesChannelId
        );

        return [
            'success'     => $updateResult['success'],
            'companyId'   => $resolvedId,
            'companyName' => $resolvedName,
            'message'     => $updateResult['message'] ?? 'Company resolved and contact updated',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Identity field updater — sends each Unique field in its own PUT request
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Update social identity fields on a Freshdesk contact.
     *
     * FRESHDESK FIELD MAPPING (from API docs):
     *   - facebook_id  → NO direct field exists. Facebook goes via social_handler:
     *                    [{"label": "facebook", "value": "..."}]
     *   - twitter_id   → Goes via social_handler: [{"label": "x", "value": "..."}]
     *                    (Freshdesk maps twitter_id writes to social_handler.label=x)
     *   - unique_external_id → Direct field, send separately (Unique — may conflict)
     *
     * Strategy:
     *   1. Build social_handler array for facebook + Twitter in ONE call
     *      (Freshdesk replaces the whole array, so send all handles together)
     *   2. Send unique_external_id in a separate call (Unique field, may conflict)
     *
     * This method is always called AFTER the contact exists and BEFORE createTicket().
     */
    public function updateContactIdentityFields(
        int $contactId,
        ?string $facebookId,
        ?string $twitterId,
        ?string $uniqueExternalId,
        ?string $salesChannelId = null
    ): void {
        $this->log("updateContactIdentityFields() called | contact_id={$contactId} | facebook={$facebookId} | twitter={$twitterId} | external={$uniqueExternalId}");

        // ── Step 1: social_handler (facebook + Twitter together in one call) ─────
        // Freshdesk uses social_handler array for Facebook and Twitter/X.
        // facebook_id is NOT a standalone contact field — it must go through social_handler.
        // twitter_id writes are also reflected in social_handler with label="x".
        $socialHandlers = [];

        if (! empty($facebookId)) {
            $socialHandlers[] = ['label' => 'facebook', 'value' => $facebookId];
        }
        if (! empty($twitterId)) {
            $socialHandlers[] = ['label' => 'x', 'value' => $twitterId];
        }

        if (! empty($socialHandlers)) {
            $this->log("updateContactIdentityFields() updating social_handler | contact_id={$contactId} | handles=" . json_encode($socialHandlers));
            $result = $this->updateFreshdeskContact(
                $contactId,
                ['social_handler' => $socialHandlers],
                $salesChannelId
            );
            if ($result['success']) {
                $this->log("updateContactIdentityFields() social_handler updated OK | contact_id={$contactId}");
            } else {
                $this->log("updateContactIdentityFields() social_handler FAILED (non-fatal) | contact_id={$contactId} | " . ($result['message'] ?? 'unknown'));
            }
        }

        // ── Step 2: unique_external_id — send alone (Unique field, may conflict) ─
        if (! empty($uniqueExternalId)) {
            $this->log("updateContactIdentityFields() updating unique_external_id={$uniqueExternalId} | contact_id={$contactId}");
            $result = $this->updateFreshdeskContact(
                $contactId,
                ['unique_external_id' => $uniqueExternalId],
                $salesChannelId
            );
            if ($result['success']) {
                $this->log("updateContactIdentityFields() unique_external_id updated OK | contact_id={$contactId}");
            } else {
                $this->log("updateContactIdentityFields() unique_external_id FAILED (non-fatal) | contact_id={$contactId} | " . ($result['message'] ?? 'unknown'));
            }
        }

        if (empty($socialHandlers) && empty($uniqueExternalId)) {
            $this->log("updateContactIdentityFields() nothing to update for contact_id={$contactId}");
        }

        $this->log("updateContactIdentityFields() done | contact_id={$contactId}");
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param string $endpoint
     * @param string|null $salesChannelId
     * @return array<int, mixed>
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    private function fetchFromApi(string $endpoint, ?string $salesChannelId = null): array
    {
        $apiUrl = $this->systemConfigService->get('CodeComFreshdeskForm.config.apiUrl', $salesChannelId);
        $apiKey = $this->systemConfigService->get('CodeComFreshdeskForm.config.apiKey', $salesChannelId);

        if (! $apiUrl || ! $apiKey) {
            $this->log("fetchFromApi({$endpoint}) aborted: API not configured");
            return [];
        }

        try {
            $url = rtrim(is_string($apiUrl) ? $apiUrl : '', '/') . $endpoint;
            $this->log("fetchFromApi() → GET {$url}");

            $response   = $this->httpClient->request('GET', $url, [
                'auth_basic' => [is_string($apiKey) ? $apiKey : '', 'X'],
            ]);
            $statusCode = $response->getStatusCode();

            $this->log("fetchFromApi() ← HTTP {$statusCode} | endpoint={$endpoint}");

            return $response->toArray();
        } catch (\Exception $e) {
            $this->log("fetchFromApi() EXCEPTION | endpoint={$endpoint} | " . $e->getMessage());
            return [];
        }
    }

    /** @param array<string, mixed> $formData */
    private function formatDescription(array $formData): string
    {
        $excludeFields = ['name', 'email', 'phone', 'subject', 'group_id', 'product_id', 'type', 'priority', 'status'];
        $description   = '<html><body>';

        foreach ($formData as $key => $value) {
            if (in_array($key, $excludeFields)) {
                continue;
            }
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $label       = ucfirst(str_replace(['_', '-'], ' ', $key));
            $valueStr    = is_string($value) ? $value : '';
            $description .= '<p><strong>' . htmlspecialchars($label) . ':</strong> ' . htmlspecialchars($valueStr) . '</p>';
        }

        $description .= '</body></html>';
        return $description;
    }
}

<?php

declare(strict_types=1);

namespace CodeCom\FreshDeskForm\Storefront\Controller;

use DateTime;
use CodeCom\FreshDeskForm\Core\Content\FormSubmission\FormSubmissionCollection;
use CodeCom\FreshDeskForm\Service\FreshdeskService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class CustomFormController extends StorefrontController
{
    /**
     * @param EntityRepository<FormSubmissionCollection> $formSubmissionRepository
     */
    public function __construct(
        private readonly FreshdeskService $freshdeskService,
        private readonly EntityRepository $formSubmissionRepository,
        private readonly EntityRepository $formApiDataRepository
    ) {
    }

    // ── Form submission ───────────────────────────────────────────────────────

    #[Route(
        path: '/freshdesk-form/submit',
        name: 'frontend.freshdesk.form.submit',
        defaults: ['XmlHttpRequest' => true, 'csrf_protected' => false],
        methods: ['POST']
    )]
    public function warrantySubmit(RequestDataBag $data, SalesChannelContext $context): JsonResponse
    {
        $formData  = $data->all();
        $firstName = isset($formData['firstName']) && is_string($formData['firstName']) ? $formData['firstName'] : '';
        $lastName  = isset($formData['lastName'])  && is_string($formData['lastName'])  ? $formData['lastName']  : '';
        $formData['name'] = trim($firstName . ' ' . $lastName);

        // ── Collect custom fields + type hints ───────────────────────────────
        // Values: { "custom_fields": { "cf_reference_number": "1111" } }
        // Types:  { "custom_field_types": { "cf_reference_number": "custom_number" } }
        $customFields     = [];
        $customFieldTypes = [];

        // Read type hints (used by FreshdeskService to cast values correctly)
        $typesRaw = $formData['custom_field_types'] ?? null;
        if (is_array($typesRaw)) {
            foreach ($typesRaw as $k => $v) {
                if (is_string($k) && is_string($v)) {
                    $customFieldTypes[$k] = $v;
                }
            }
        }

        // Fallback: flat bracket-notation types
        foreach ($formData as $key => $value) {
            if (is_string($key) && preg_match('/^custom_field_types\[([^\]]+)\]$/', $key, $m)) {
                $customFieldTypes[$m[1]] = (string) $value;
            }
        }

        // Case A: properly nested object (expected from JS _buildFormData)
        $customFieldsRaw = $formData['custom_fields'] ?? null;
        if (is_array($customFieldsRaw)) {
            foreach ($customFieldsRaw as $fieldName => $value) {
                if (is_string($fieldName) && $fieldName !== '' && $value !== '' && $value !== null) {
                    $customFields[$fieldName] = $value;
                }
            }
        }

        // Case B: flat bracket-notation keys (fallback for cached old JS)
        foreach ($formData as $key => $value) {
            if (is_string($key) && preg_match('/^custom_fields\[([^\]]+)\]$/', $key, $m)) {
                $fieldName = $m[1];
                if ($fieldName !== '' && $value !== '' && $value !== null) {
                    $customFields[$fieldName] = $value;
                }
            }
        }

        $formData['_custom_fields']      = $customFields;
        $formData['_custom_field_types'] = $customFieldTypes;

        if (isset($formData['company_name']) && is_string($formData['company_name'])) {
            $formData['company_name'] = trim($formData['company_name']);
        }

        // ── 1. Resolve company and link contact BEFORE creating the ticket ────
        // Freshdesk rejects company_id on a new ticket if the requester (contact)
        // is not already linked to that company. We must update the contact first.
        $email       = $formData['email']        ?? '';
        $companyId   = !empty($formData['company_id'])   ? (string) $formData['company_id']   : null;
        $companyName = !empty($formData['company_name']) ? (string) $formData['company_name'] : null;

        if ($companyId === null && $companyName !== null) {
            $criteria = new Criteria();
            $criteria->setLimit(1);
            $apiData = $this->formApiDataRepository
                ->search($criteria, $context->getContext())
                ->first();

            foreach ($apiData?->getCompanies() ?? [] as $company) {
                if (!is_array($company) || !isset($company['id'], $company['name'])) {
                    continue;
                }

                if (mb_strtolower(trim((string) $company['name'])) === mb_strtolower($companyName)) {
                    $companyId = (string) $company['id'];
                    $formData['company_id'] = $companyId;
                    break;
                }
            }
        }

        $resolvedCompanyId = $companyId;

        if ($email && ($companyId || $companyName)) {
            $companyResult = $this->freshdeskService->resolveCompanyAndUpdateContact(
                $email,
                $companyId,
                $companyName,
                $context->getSalesChannelId(),
                trim(($formData['firstName'] ?? '') . ' ' . ($formData['lastName'] ?? '')) ?: null,
                !empty($formData['phone']) ? (string) $formData['phone'] : null,
                !empty($formData['facebook_id'])        ? (string) $formData['facebook_id']        : null,
                !empty($formData['twitter_id'])         ? (string) $formData['twitter_id']         : null,
                !empty($formData['unique_external_id']) ? (string) $formData['unique_external_id'] : null
            );

            if ($companyResult['success'] && !empty($companyResult['companyId'])) {
                $resolvedCompanyId            = (string) $companyResult['companyId'];
                $formData['company_id']       = $resolvedCompanyId;
            }
        }

        // ── 2. Create the Freshdesk ticket ────────────────────────────────────
        $result = $this->freshdeskService->createTicket($formData, $context->getSalesChannelId());

        // ── 3. Persist submission in Shopware DB ──────────────────────────────
        // ── Resolve requesterId: look up the Freshdesk contact by email ──────
        // This stores the Freshdesk contact/requester ID so we can reference
        // the contact directly in future API calls without another lookup.
        $requesterRecord = $this->freshdeskService->findContactByEmail($email, $context->getSalesChannelId());
        $requesterId     = ! empty($requesterRecord['id']) ? (string) $requesterRecord['id'] : null;

        $submissionData = [
            'id'              => Uuid::randomHex(),
            'firstName'       => $formData['firstName']    ?? '',
            'lastName'        => $formData['lastName']     ?? '',
            'email'           => $email,
            'phone'           => $formData['phone']        ?? '',
            'subject'         => $formData['subject']      ?? '',
            'message'         => $formData['message']      ?? '',
            'type'            => $formData['type']         ?? null,
            'groupId'         => !empty($formData['group_id'])      ? (string) $formData['group_id']      : null,
            'productId'       => !empty($formData['product_id'])    ? (string) $formData['product_id']    : null,
            'responderId'     => !empty($formData['responder_id'])  ? (string) $formData['responder_id']  : null,
            'emailConfigId'   => !empty($formData['email_config_id']) ? (string) $formData['email_config_id'] : null,
            'companyId'       => $resolvedCompanyId,
            'status'          => isset($formData['status'])   && is_numeric($formData['status'])   ? (int) $formData['status']   : null,
            'source'          => isset($formData['source'])   && is_numeric($formData['source'])   ? (int) $formData['source']   : null,
            'priority'        => isset($formData['priority']) && is_numeric($formData['priority']) ? (int) $formData['priority'] : null,
            'freshdeskTicketId' => isset($result['ticket_id']) ? (string) $result['ticket_id'] : null,
            // ── Identity fields ────────────────────────────────────────────
            'requesterId'       => $requesterId,
            'facebookId'        => !empty($formData['facebook_id'])        ? (string) $formData['facebook_id']        : null,
            'twitterId'         => !empty($formData['twitter_id'])         ? (string) $formData['twitter_id']         : null,
            'uniqueExternalId'  => !empty($formData['unique_external_id']) ? (string) $formData['unique_external_id'] : null,
            // ──────────────────────────────────────────────────────────────
            'extraFields'       => !empty($customFields) ? $customFields : null,
            'createdAt'         => new DateTime(),
        ];

        $this->formSubmissionRepository->create([$submissionData], $context->getContext());

        if ($result['success']) {
            return new JsonResponse([
                'type'    => 'success',
                'message' => 'Thank you for your submission! Your ticket has been created successfully.',
                'data'    => $result,
            ]);
        }

        return new JsonResponse([
            'type'    => 'danger',
            'message' => $result['message'] ?? 'Failed to submit the form. Please try again.',
            'data'    => $result,
        ]);
    }

    // ── Company search (reads from DB cache, no live API call) ────────────────

    /**
     * GET /freshdesk/search-companies?q=term
     *
     * Searches the cached freshdesk_form_api_data.companies array by name.
     * Returns [{id, name}, ...] filtered to a max of 20 results.
     */
    #[Route(
        path: '/freshdesk/search-companies',
        name: 'frontend.freshdesk.search_companies',
        defaults: ['XmlHttpRequest' => true, 'csrf_protected' => false],
        methods: ['GET']
    )]
    public function searchCompanies(Request $request, SalesChannelContext $context): JsonResponse
    {
        $query = strtolower(trim((string) $request->query->get('q', '')));

        if ($query === '') {
            return new JsonResponse([]);
        }

        // Load cached companies from DB
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $apiData = $this->formApiDataRepository
            ->search($criteria, $context->getContext())
            ->first();

        $companies = $apiData?->getCompanies() ?? [];

        // Filter by name containing the query
        $results = [];
        foreach ($companies as $company) {
            if (is_array($company) && isset($company['id'], $company['name'])) {
                if (str_contains(strtolower((string) $company['name']), $query)) {
                    $results[] = ['id' => $company['id'], 'name' => $company['name']];
                }
            }
            if (count($results) >= 20) {
                break;
            }
        }

        return new JsonResponse($results);
    }

    // ── Utility / diagnostics ─────────────────────────────────────────────────

    #[Route(
        path: '/freshdesk/test-connection',
        name: 'frontend.freshdesk.test_connection',
        defaults: ['XmlHttpRequest' => true, 'csrf_protected' => false],
        methods: ['POST']
    )]
    public function testConnection(SalesChannelContext $context): JsonResponse
    {
        $result = $this->freshdeskService->testConnection($context->getSalesChannelId());
        return new JsonResponse($result);
    }
}

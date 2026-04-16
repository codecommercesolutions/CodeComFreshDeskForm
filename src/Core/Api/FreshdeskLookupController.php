<?php

declare(strict_types=1);

namespace CodeCom\FreshDeskForm\Core\Api;

use CodeCom\FreshDeskForm\Service\FreshdeskService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Admin-API endpoints consumed by the Shopware administration panel.
 *
 * /metadata  — served from the freshdesk_form_api_data DB cache so the
 *              admin detail page does not trigger live Freshdesk API calls
 *              on every page open.  All other individual endpoints still
 *              proxy the live API (useful for diagnostics / forced refresh).
 */
#[Route(defaults: ['_routeScope' => ['api']])]
class FreshdeskLookupController extends AbstractController
{
    public function __construct(
        private readonly FreshdeskService $freshdeskService,
        private readonly EntityRepository $formApiDataRepository
    ) {
    }

    // ── Live-API individual endpoints (diagnostics / admin tools) ────────────

    #[Route(path: '/api/_action/freshdesk/groups', name: 'api.action.freshdesk.groups', methods: ['GET'])]
    public function getGroups(): JsonResponse
    {
        return new JsonResponse($this->freshdeskService->getGroups());
    }

    #[Route(path: '/api/_action/freshdesk/products', name: 'api.action.freshdesk.products', methods: ['GET'])]
    public function getProducts(): JsonResponse
    {
        return new JsonResponse($this->freshdeskService->getProducts());
    }

    #[Route(path: '/api/_action/freshdesk/ticket-types', name: 'api.action.freshdesk.ticket-types', methods: ['GET'])]
    public function getTicketTypes(): JsonResponse
    {
        return new JsonResponse($this->freshdeskService->getTicketTypes());
    }

    #[Route(path: '/api/_action/freshdesk/ticket-fields', name: 'api.action.freshdesk.ticket-fields', methods: ['GET'])]
    public function getTicketFields(): JsonResponse
    {
        return new JsonResponse($this->freshdeskService->getTicketFields());
    }

    #[Route(path: '/api/_action/freshdesk/agents', name: 'api.action.freshdesk.agents', methods: ['GET'])]
    public function getAgents(): JsonResponse
    {
        return new JsonResponse($this->freshdeskService->getAgents());
    }

    #[Route(path: '/api/_action/freshdesk/email-configs', name: 'api.action.freshdesk.email-configs', methods: ['GET'])]
    public function getEmailConfigs(): JsonResponse
    {
        return new JsonResponse($this->freshdeskService->getEmailConfigs());
    }

    #[Route(path: '/api/_action/freshdesk/companies', name: 'api.action.freshdesk.companies', methods: ['GET'])]
    public function getCompanies(): JsonResponse
    {
        return new JsonResponse($this->freshdeskService->getCompanies());
    }

    // ── On-demand sync — called by "Test API Connection" button ─────────────

    /**
     * POST /api/_action/freshdesk/sync
     *
     * Runs the exact same logic as FreshdeskApiSyncTaskHandler::run():
     * fetches all six Freshdesk API endpoints and upserts the results
     * into freshdesk_form_api_data so the storefront data is immediately fresh.
     *
     * Called by the admin "Test API Connection" button after a successful
     * connection test, so admins get instant data without waiting for the
     * 24-hour cron to fire.
     */
    #[Route(path: '/api/_action/freshdesk/sync', name: 'api.action.freshdesk.sync', methods: ['POST'])]
    public function syncApiData(Context $context): JsonResponse
    {
        try {
            $agents       = $this->freshdeskService->getAgents();
            $emailConfigs = $this->freshdeskService->getEmailConfigs();
            $companies    = $this->freshdeskService->getCompanies();
            $ticketFields = $this->freshdeskService->getTicketFields();
            $groups       = $this->freshdeskService->getGroups();
            $products     = $this->freshdeskService->getProducts();

            $criteria = new Criteria();
            $criteria->setLimit(1);
            $existing = $this->formApiDataRepository->search($criteria, $context)->first();

            $data = [
                'agents'       => $agents,
                'emailConfigs' => $emailConfigs,
                'companies'    => $companies,
                'ticketFields' => $ticketFields,
                'groups'       => $groups,
                'products'     => $products,
            ];

            if ($existing !== null) {
                $data['id'] = $existing->getId();
                $this->formApiDataRepository->update([$data], $context);
            } else {
                $data['id'] = Uuid::randomHex();
                $this->formApiDataRepository->create([$data], $context);
            }

            return new JsonResponse([
                'success' => true,
                'message' => 'Freshdesk data synced successfully.',
                'counts'  => [
                    'agents'       => count($agents),
                    'emailConfigs' => count($emailConfigs),
                    'companies'    => count($companies),
                    'ticketFields' => count($ticketFields),
                    'groups'       => count($groups),
                    'products'     => count($products),
                ],
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ── Company search + create/update contact (admin) ───────────────────────

    /**
     * GET /api/_action/freshdesk/search-companies?q=term
     *
     * Searches the cached DB companies array by name.
     * - If q is empty → returns first 20 companies (so dropdown shows on focus).
     * - Otherwise filters by name contains q.
     * Returns [{id, name}, ...].
     */
    #[Route(path: '/api/_action/freshdesk/search-companies', name: 'api.action.freshdesk.search_companies', methods: ['GET'])]
    public function searchCompanies(Request $request, Context $context): JsonResponse
    {
        $query = strtolower(trim((string) $request->query->get('q', '')));

        $criteria = new Criteria();
        $criteria->setLimit(1);
        $apiData = $this->formApiDataRepository
            ->search($criteria, $context)
            ->first();

        $companies = $apiData?->getCompanies() ?? [];

        $results = [];
        foreach ($companies as $company) {
            if (!is_array($company) || !isset($company['id'], $company['name'])) {
                continue;
            }
            // Empty query → return first 20 as "show all"
            if ($query === '' || str_contains(strtolower((string) $company['name']), $query)) {
                $results[] = ['id' => $company['id'], 'name' => $company['name']];
            }
            if (count($results) >= 20) {
                break;
            }
        }

        return new JsonResponse($results);
    }

    /**
     * GET /api/_action/freshdesk/company/{id}
     *
     * Looks up a single company by its Freshdesk numeric ID.
     * First checks the DB cache; falls back to the live Freshdesk API.
     * Returns {id, name} or 404 JSON.
     */
    #[Route(path: '/api/_action/freshdesk/company/{id}', name: 'api.action.freshdesk.company_by_id', methods: ['GET'])]
    public function getCompanyById(int $id, Context $context): JsonResponse
    {
        // 1. Try the DB cache first (no live API call)
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $apiData = $this->formApiDataRepository
            ->search($criteria, $context)
            ->first();

        $companies = $apiData?->getCompanies() ?? [];
        foreach ($companies as $company) {
            if (is_array($company) && isset($company['id']) && (int) $company['id'] === $id) {
                return new JsonResponse(['id' => $company['id'], 'name' => $company['name'] ?? '']);
            }
        }

        // 2. Fallback: live Freshdesk API
        $company = $this->freshdeskService->getCompanyById($id);
        if ($company !== null) {
            return new JsonResponse($company);
        }

        return new JsonResponse(['error' => 'Company not found'], 404);
    }

    /**
     * POST /api/_action/freshdesk/resolve-company
     *
     * Called from the admin submission detail page when the company field
     * changes.  Handles three cases:
     *   - Existing company selected: company_id provided → update contact
     *   - New company name typed:    company_name provided → search/create → update contact
     *
     * Request body: { "companyId": int|null, "companyName": string|null, "email": string }
     * Response:     { "success": bool, "companyId": int, "companyName": string }
     */
    #[Route(path: '/api/_action/freshdesk/resolve-company', name: 'api.action.freshdesk.resolve_company', methods: ['POST'])]
    public function resolveCompany(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true) ?? [];

        $email       = trim((string) ($body['email']       ?? ''));
        $companyId   = !empty($body['companyId'])   ? (string) $body['companyId']   : null;
        $companyName = !empty($body['companyName']) ? (string) $body['companyName'] : null;

        if ($email === '' || ($companyId === null && $companyName === null)) {
            return new JsonResponse(['success' => false, 'message' => 'email and company required'], 400);
        }

        $result = $this->freshdeskService->resolveCompanyAndUpdateContact(
            $email,
            $companyId,
            $companyName
        );

        return new JsonResponse($result);
    }

    // ── Cached endpoint — used by admin submissions detail page ──────────────

    /**
     * Returns all metadata from the freshdesk_form_api_data DB cache.
     *
     * The administration detail page (freshdesk-submissions-detail) calls
     * this single endpoint to populate all dropdowns.  Data is kept up-to-date
     * by FreshdeskApiSyncTask which runs every 24 hours.
     *
     * Falls back to an empty structure when the cron has not run yet so the
     * admin page remains usable even immediately after installation.
     */
    #[Route(path: '/api/_action/freshdesk/metadata', name: 'api.action.freshdesk.metadata', methods: ['GET'])]
    public function getMetadataFromCache(Context $context): JsonResponse
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $apiData = $this->formApiDataRepository
            ->search($criteria, $context)
            ->first();

        if ($apiData === null) {
            return new JsonResponse([
                'ticketFields' => [],
                'products'     => [],
                'groups'       => [],
                'agents'       => [],
                'emailConfigs' => [],
                'companies'    => [],
            ]);
        }

        return new JsonResponse([
            'ticketFields' => $apiData->getTicketFields() ?? [],
            'products'     => $apiData->getProducts()     ?? [],
            'groups'       => $apiData->getGroups()       ?? [],
            'agents'       => $apiData->getAgents()       ?? [],
            'emailConfigs' => $apiData->getEmailConfigs() ?? [],
            'companies'    => $apiData->getCompanies()    ?? [],
        ]);
    }
}

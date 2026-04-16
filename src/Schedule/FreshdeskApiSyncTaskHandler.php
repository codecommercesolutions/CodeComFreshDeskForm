<?php

declare(strict_types=1);

namespace CodeCom\FreshDeskForm\Schedule;

use CodeCom\FreshDeskForm\Service\FreshdeskService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Runs every 24 hours. Fetches six Freshdesk API endpoints and stores
 * the raw JSON arrays into a single row in freshdesk_form_api_data so that
 * the storefront can read from the DB instead of calling the live API.
 *
 * Endpoints synced:
 *   - /api/v2/agents
 *   - /api/v2/email_configs
 *   - /api/v2/companies
 *   - /api/v2/ticket_fields
 *   - /api/v2/groups
 *   - /api/v2/products
 *
 * @internal
 */
#[AsMessageHandler(handles: FreshdeskApiSyncTask::class)]
class FreshdeskApiSyncTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        private readonly LoggerInterface $logger,
        private readonly FreshdeskService $freshdeskService,
        private readonly EntityRepository $formApiDataRepository
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        try {
            $context = Context::createCLIContext();

            // Fetch all six data sets from Freshdesk API
            $agents       = $this->freshdeskService->getAgents();
            $emailConfigs = $this->freshdeskService->getEmailConfigs();
            $companies    = $this->freshdeskService->getCompanies();
            $ticketFields = $this->freshdeskService->getTicketFields();
            $groups       = $this->freshdeskService->getGroups();
            $products     = $this->freshdeskService->getProducts();

            // Find existing record to upsert (we always keep one row)
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

            $this->logger->info('FreshdeskApiSyncTask: all API data synced successfully', [
                'agents_count'        => count($agents),
                'email_configs_count' => count($emailConfigs),
                'companies_count'     => count($companies),
                'ticket_fields_count' => count($ticketFields),
                'groups_count'        => count($groups),
                'products_count'      => count($products),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('FreshdeskApiSyncTask: failed to sync API data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}

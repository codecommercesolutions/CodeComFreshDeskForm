<?php

declare(strict_types=1);

namespace CodeCom\FreshDeskForm\Subscriber;

use CodeCom\FreshDeskForm\Service\FreshdeskService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FormSubmissionSubscriber implements EventSubscriberInterface
{
    /**
     * Guard: prevents re-entrant processing when we call update() ourselves
     * to write the Freshdesk ticket ID back to the entity.
     */
    private bool $processingTicket = false;

    public function __construct(
        private readonly FreshdeskService $freshdeskService,
        private readonly EntityRepository $formSubmissionRepository,
        private readonly EntityRepository $formApiDataRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Logging helper — routes subscriber diagnostics through Shopware's logger
    // ─────────────────────────────────────────────────────────────────────────
    private function log(string $message): void
    {
        $this->logger->info($message, ['plugin' => 'CodeComFreshDeskForm', 'source' => 'FormSubmissionSubscriber']);
    }

    /**
     * @param array<string, mixed> $field
     */
    private function normalizeCustomFieldType(array $field): string
    {
        return isset($field['type']) && is_string($field['type']) ? $field['type'] : 'custom_text';
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'freshdesk_form_submission.written' => 'onSubmissionWritten',
            'freshdesk_form_submission.deleted' => 'onSubmissionDeleted',
        ];
    }

    public function onSubmissionWritten(EntityWrittenEvent $event): void
    {
        if ($this->processingTicket) {
            $this->log('onSubmissionWritten() skipped: re-entrant guard active');
            return;
        }

        $pendingWrites = [];
        $entityIds = [];

        foreach ($event->getWriteResults() as $result) {
            $payload = $result->getPayload();
            $entityId = $payload['id'] ?? null;

            if (!$entityId) {
                $this->log('onSubmissionWritten() skipped: no entity ID in payload');
                continue;
            }

            // Skip if only freshdeskTicketId changed — that is our own write-back
            $changedKeys = array_keys($payload);
            if ($changedKeys === ['id', 'freshdeskTicketId'] || $changedKeys === ['id']) {
                $this->log("onSubmissionWritten() skipped write-back update | entity_id={$entityId}");
                continue;
            }

            $pendingWrites[] = [
                'payload' => $payload,
                'entityId' => $entityId,
            ];
            $entityIds[] = $entityId;
        }

        if ($pendingWrites === []) {
            return;
        }

        $entitiesById = [];
        $criteria = new Criteria(array_values(array_unique($entityIds)));
        $entities = $this->formSubmissionRepository->search($criteria, Context::createCLIContext())->getEntities();

        foreach ($entities as $entity) {
            $entitiesById[$entity->getId()] = $entity;
        }

        foreach ($pendingWrites as $pendingWrite) {
            $entityId = $pendingWrite['entityId'];
            $this->log("onSubmissionWritten() processing | entity_id={$entityId}");

            $entity = $entitiesById[$entityId] ?? null;

            if (!$entity) {
                $this->log("onSubmissionWritten() entity not found | entity_id={$entityId}");
                continue;
            }

            $freshdeskTicketId = $entity->getFreshdeskTicketId();
            $this->log("onSubmissionWritten() entity loaded | entity_id={$entityId} | existing_ticket_id=" . ($freshdeskTicketId ?? 'none'));

            // Build custom field type map from cached ticket_fields
            $customFieldTypes = $this->resolveCustomFieldTypes();
            $this->log('onSubmissionWritten() custom field types loaded | count=' . count($customFieldTypes));

            $formData = [
                'firstName'           => $entity->getFirstName(),
                'lastName'            => $entity->getLastName(),
                'email'               => $entity->getEmail(),
                'phone'               => $entity->getPhone(),
                'subject'             => $entity->getSubject(),
                'message'             => $entity->getMessage(),
                'type'                => $entity->getType(),
                'group_id'            => $entity->getGroupId(),
                'product_id'          => $entity->getProductId(),
                'responder_id'        => $entity->getResponderId(),
                'email_config_id'     => $entity->getEmailConfigId(),
                'company_id'          => $entity->getCompanyId(),
                'status'              => $entity->getStatus(),
                'source'              => $entity->getSource(),
                'priority'            => $entity->getPriority(),
                '_custom_fields'      => $entity->getExtraFields() ?? [],
                '_custom_field_types' => $customFieldTypes,
                // ── Identity fields (passed directly to Freshdesk ticket API) ──
                'facebook_id'         => $entity->getFacebookId(),
                'twitter_id'          => $entity->getTwitterId(),
                'unique_external_id'  => $entity->getUniqueExternalId(),
            ];

            $formData['name'] = trim($formData['firstName'] . ' ' . $formData['lastName']);

            try {
                if (empty($freshdeskTicketId)) {
                    // ── CREATE branch ─────────────────────────────────────────
                    // Reached when the entity was saved WITHOUT a ticket ID yet
                    // (e.g. admin creates a submission manually from the back-office
                    // without going through the storefront controller).
                    // The storefront controller already calls createTicket() itself
                    // and saves the entity with freshdeskTicketId set, so for normal
                    // storefront submissions this branch is only a safety fallback.

                    $companyId   = $entity->getCompanyId();
                    $email       = $entity->getEmail();
                    $phone       = $entity->getPhone() ?: null;
                    $nameParts   = array_filter([
                        trim($entity->getFirstName() ?? ''),
                        trim($entity->getLastName()  ?? ''),
                    ]);
                    $contactName = ! empty($nameParts) ? implode(' ', $nameParts) : null;

                    $this->log("onSubmissionWritten() CREATE branch | email={$email} | company_id={$companyId} | contact_name={$contactName}");

                    // Resolve / create company and ensure contact is linked.
                    // companyId may be null — resolveCompanyAndUpdateContact handles that.
                    if ($email) {
                        if ($companyId) {
                            $this->log("onSubmissionWritten() calling resolveCompanyAndUpdateContact | email={$email}");
                            $resolveResult = $this->freshdeskService->resolveCompanyAndUpdateContact(
                                $email,
                                $companyId,
                                null,
                                null,
                                $contactName,
                                $phone,
                                $entity->getFacebookId(),
                                $entity->getTwitterId(),
                                $entity->getUniqueExternalId()
                            );
                            $this->log(
                                "onSubmissionWritten() resolveCompanyAndUpdateContact result: success=" .
                                ($resolveResult['success'] ? 'true' : 'false') .
                                ' | message=' . ($resolveResult['message'] ?? '-')
                            );
                        } else {
                            // No company — still update contact identity/name/phone directly
                            $this->log("onSubmissionWritten() no company_id, updating contact identity fields directly | email={$email}");
                            $this->syncContactFields($entity, null);
                        }
                    } else {
                        $this->log("onSubmissionWritten() skipping contact update: missing email");
                    }

                    // Create the Freshdesk ticket
                    $this->log("onSubmissionWritten() calling createTicket | email={$email}");
                    $ticketResult = $this->freshdeskService->createTicket($formData);
                    $this->log(
                        "onSubmissionWritten() createTicket result: success=" .
                        ($ticketResult['success'] ? 'true' : 'false') .
                        ' | ticket_id=' . ($ticketResult['ticket_id'] ?? '-') .
                        ' | message=' . ($ticketResult['message'] ?? '-')
                    );

                    if ($ticketResult['success'] && !empty($ticketResult['ticket_id'])) {
                        $this->syncRequesterId(
                            $entityId,
                            $email ?? '',
                            (string) $ticketResult['ticket_id']
                        );
                        $this->log("onSubmissionWritten() ticket_id/requester_id saved | entity_id={$entityId}");
                    } else {
                        $this->log("onSubmissionWritten() ticket creation failed, not saving ticket_id | entity_id={$entityId}");
                    }
                } else {
                    // ── UPDATE branch ─────────────────────────────────────────
                    // Reached for:
                    //   (a) New storefront submissions — controller already created
                    //       the ticket AND saved freshdeskTicketId, so the subscriber
                    //       fires here. We update the ticket + sync the contact.
                    //   (b) Admin edits an existing submission in the back-office.
                    //
                    // In both cases: update the Freshdesk ticket, then sync ALL
                    // contact fields (name, mobile, company_id, identity fields)
                    // directly via the contact API — independent of whether
                    // company_id is set or not.

                    $email = $entity->getEmail();
                    $this->log("onSubmissionWritten() UPDATE branch | entity_id={$entityId} | ticket_id={$freshdeskTicketId} | email={$email}");

                    $updateResult = $this->freshdeskService->updateTicket((int) $freshdeskTicketId, $formData);
                    $this->log(
                        "onSubmissionWritten() updateTicket result: success=" .
                        ($updateResult['success'] ? 'true' : 'false') .
                        ' | message=' . ($updateResult['message'] ?? '-')
                    );

                    // Always sync contact fields — company_id is optional.
                    // This ensures facebook_id / twitter_id / unique_external_id
                    // are always written to the Freshdesk contact API even when
                    // company_id is empty.
                    if ($email) {
                        $this->syncContactFields($entity, null);
                        $this->syncRequesterId($entityId, $email);
                    } else {
                        $this->log("onSubmissionWritten() skipping contact sync: missing email");
                    }
                }
            } catch (\Throwable $e) {
                $this->processingTicket = false;
                $this->log("onSubmissionWritten() EXCEPTION | entity_id={$entityId} | " . $e->getMessage());
            }
        }
    }

    public function onSubmissionDeleted(EntityDeletedEvent $event): void
    {
        $this->log('onSubmissionDeleted() called — no action (Freshdesk API does not support ticket deletion)');
    }

    private function syncRequesterId(string $entityId, string $email, ?string $freshdeskTicketId = null): void
    {
        if ($email === '') {
            $this->log("syncRequesterId() skipped: empty email | entity_id={$entityId}");
            return;
        }

        $requesterRecord = $this->freshdeskService->findContactByEmail($email, null);
        $requesterId     = ! empty($requesterRecord['id']) ? (string) $requesterRecord['id'] : null;

        if ($requesterId === null) {
            $this->log("syncRequesterId() contact not found | entity_id={$entityId} | email={$email}");
            return;
        }

        $updatePayload = [
            'id' => $entityId,
            'requesterId' => $requesterId,
        ];

        if ($freshdeskTicketId !== null && $freshdeskTicketId !== '') {
            $updatePayload['freshdeskTicketId'] = $freshdeskTicketId;
        }

        $this->processingTicket = true;

        try {
            $this->log(
                "syncRequesterId() writing requester_id back | entity_id={$entityId} | requester_id={$requesterId}" .
                ($freshdeskTicketId !== null && $freshdeskTicketId !== '' ? " | ticket_id={$freshdeskTicketId}" : '')
            );
            $this->formSubmissionRepository->update([$updatePayload], Context::createCLIContext());
        } finally {
            $this->processingTicket = false;
        }
    }

    /**
     * Syncs all writable contact fields to the Freshdesk contact API.
     *
     * Called after both CREATE and UPDATE so that name, mobile, company_id,
     * facebook_id, twitter_id, and unique_external_id are always kept in sync
     * — regardless of whether company_id is filled or not.
     *
     * @param \CodeCom\FreshDeskForm\Core\Content\FormSubmission\FormSubmissionEntity $entity
     * @param string|null $salesChannelId
     */
    private function syncContactFields(object $entity, ?string $salesChannelId): void
    {
        $email = $entity->getEmail();

        if (empty($email)) {
            $this->log('syncContactFields() skipped: no email');
            return;
        }

        $this->log("syncContactFields() called | email={$email}");

        // Find the existing Freshdesk contact
        $contactRecord = $this->freshdeskService->findContactByEmail($email, $salesChannelId);
        $contactId     = ! empty($contactRecord['id']) ? (int) $contactRecord['id'] : null;

        if ($contactId === null) {
            $this->log("syncContactFields() contact not found in Freshdesk for email={$email}, skipping");
            return;
        }

        // ── Step 1: update non-unique fields together (name, mobile, company_id) ─
        $update = [];

        $nameParts = array_filter([
            trim($entity->getFirstName() ?? ''),
            trim($entity->getLastName()  ?? ''),
        ]);
        if (! empty($nameParts)) {
            $update['name'] = implode(' ', $nameParts);
        }

        if (! empty($entity->getPhone())) {
            $update['mobile'] = $entity->getPhone();
        }

        $companyId = $entity->getCompanyId();
        if (! empty($companyId) && is_numeric($companyId)) {
            $update['company_id'] = (int) $companyId;
        }

        if (! empty($update)) {
            $this->log("syncContactFields() updating non-unique fields | contact_id={$contactId} | fields=" . json_encode(array_keys($update)));
            $result = $this->freshdeskService->updateFreshdeskContact($contactId, $update, $salesChannelId);
            $this->log(
                "syncContactFields() non-unique result: success=" . ($result['success'] ? 'true' : 'false') .
                ' | message=' . ($result['message'] ?? '-')
            );
        }

        // ── Step 2: update each identity field individually ───────────────────
        // facebook_id / twitter_id / unique_external_id are "Unique" in Freshdesk.
        // Sending them together causes HTTP 400 if any one conflicts.
        // Sending them one-by-one means a conflict on one field never blocks the others.
        $this->freshdeskService->updateContactIdentityFields(
            $contactId,
            $entity->getFacebookId() ?: null,
            $entity->getTwitterId() ?: null,
            $entity->getUniqueExternalId() ?: null,
            $salesChannelId
        );
    }

    /**
     * Reads the cached ticket_fields from freshdesk_form_api_data and builds
     * a map of [ 'cf_field_name' => 'custom_number' ] so FreshdeskService can
     * cast each custom-field value to the correct PHP type.
     *
     * @return array<string, string>  fieldName => fieldType
     */
    private function resolveCustomFieldTypes(): array
    {
        $this->log('resolveCustomFieldTypes() called');
        try {
            $criteria = new Criteria();
            $criteria->setLimit(1);
            $apiData = $this->formApiDataRepository
                ->search($criteria, Context::createCLIContext())
                ->first();

            $ticketFields = $apiData?->getTicketFields() ?? [];
            $typeMap      = [];

            foreach ($ticketFields as $field) {
                if (!is_array($field) || empty($field['name']) || empty($field['type'])) {
                    continue;
                }
                $typeMap[$field['name']] = $this->normalizeCustomFieldType($field);
            }

            $this->log('resolveCustomFieldTypes() loaded ' . count($typeMap) . ' type mappings');
            return $typeMap;
        } catch (\Throwable $e) {
            $this->log('resolveCustomFieldTypes() EXCEPTION (non-fatal): ' . $e->getMessage());
            return [];
        }
    }
}

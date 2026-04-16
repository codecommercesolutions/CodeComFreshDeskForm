<?php

declare(strict_types=1);

namespace CodeCom\FreshDeskForm\Subscriber;

use Shopware\Core\Content\Cms\Events\CmsPageLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Injects all Freshdesk data into CMS slots of type "freshdesk-standard-form".
 *
 * ALL data is read from the freshdesk_form_api_data table which is kept fresh
 * by the FreshdeskApiSyncTask cron (every 24 hours). No live API calls are
 * made from the storefront at page-load time.
 */
class CmsPageLoaderSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityRepository $formApiDataRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CmsPageLoadedEvent::class => 'onCmsPageLoaded',
        ];
    }

    public function onCmsPageLoaded(CmsPageLoadedEvent $event): void
    {
        $hasFreshdeskSlot = false;

        // Quick scan to skip DB query when not needed
        foreach ($event->getResult()->getElements() as $page) {
            foreach ($page->getSections() ?? [] as $section) {
                foreach ($section->getBlocks() ?? [] as $block) {
                    foreach ($block->getSlots() ?? [] as $slot) {
                        if ($slot->getType() === 'freshdesk-standard-form') {
                            $hasFreshdeskSlot = true;
                            break 4;
                        }
                    }
                }
            }
        }

        if (!$hasFreshdeskSlot) {
            return;
        }

        // ── Load cached API data from DB (single query) ───────────────────────
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $apiData = $this->formApiDataRepository
            ->search($criteria, $event->getSalesChannelContext()->getContext())
            ->first();

        // Raw arrays from DB (empty arrays when cron has not run yet)
        $rawAgents       = $apiData?->getAgents()       ?? [];
        $rawEmailConfigs = $apiData?->getEmailConfigs() ?? [];
        $rawCompanies    = $apiData?->getCompanies()    ?? [];
        $rawGroups       = $apiData?->getGroups()       ?? [];
        $rawProducts     = $apiData?->getProducts()     ?? [];
        $rawTicketFields = $apiData?->getTicketFields() ?? [];

        // ── Parse ticket_fields into per-fieldName choice lists ───────────────
        // Result: ['type' => [['value'=>'Question','label'=>'Question'], ...], 'status' => [...], ...]
        $parsedFields = $this->parseTicketFields($rawTicketFields);

        // ── Extract custom fields (default=false) for storefront rendering ────
        $customFields = $this->extractCustomFields($rawTicketFields);

        // ── Assign to every freshdesk-standard-form slot ──────────────────────
        foreach ($event->getResult()->getElements() as $page) {
            foreach ($page->getSections() ?? [] as $section) {
                foreach ($section->getBlocks() ?? [] as $block) {
                    foreach ($block->getSlots() ?? [] as $slot) {
                        if ($slot->getType() !== 'freshdesk-standard-form') {
                            continue;
                        }

                        $slot->assign([
                            'freshdeskGroups'       => $rawGroups,
                            'freshdeskProducts'     => $rawProducts,
                            'freshdeskFields'       => $parsedFields,
                            'freshdeskAgents'       => $rawAgents,
                            'freshdeskEmailConfigs' => $rawEmailConfigs,
                            'freshdeskCompanies'    => $rawCompanies,
                            'freshdeskCustomFields' => $customFields,
                        ]);
                    }
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $field
     */
    private function normalizeCustomFieldType(array $field): string
    {
        return isset($field['type']) && is_string($field['type']) ? $field['type'] : 'custom_text';
    }

    /**
     * Converts raw Freshdesk ticket_fields array into a map of fieldName => choices[].
     *
     * Each choice entry: ['value' => mixed, 'label' => string]
     *
     * @param  array<int, mixed> $ticketFields
     * @return array<string, array<int, array{value: mixed, label: string}>>
     */
    private function parseTicketFields(array $ticketFields): array
    {
        $nameMap = [
            'ticket_type' => 'type',
            'status'      => 'status',
            'priority'    => 'priority',
            'source'      => 'source',
        ];

        $parsed = [];

        foreach ($ticketFields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $rawName   = $field['name'] ?? '';
            $fieldName = $nameMap[$rawName] ?? $rawName;

            if (empty($field['choices'])) {
                continue;
            }

            $choices = $field['choices'];
            $formatted = [];

            if (is_array($choices) && !array_is_list($choices)) {
                // Object format: {"Open": 2, "Pending": 3}  or  {2: "Open", 3: "Pending"}
                $firstValue = reset($choices);
                if (is_numeric($firstValue)) {
                    // keys = labels, values = numeric IDs
                    foreach ($choices as $label => $value) {
                        $formatted[] = [
                            'value' => is_numeric($value) ? (int) $value : $value,
                            'label' => (string) $label,
                        ];
                    }
                } else {
                    // keys = numeric IDs, values = labels
                    foreach ($choices as $key => $label) {
                        $formatted[] = [
                            'value' => is_numeric($key) ? (int) $key : $key,
                            'label' => is_array($label) ? (string) ($label[0] ?? $key) : (string) $label,
                        ];
                    }
                }
            } elseif (is_array($choices)) {
                // Sequential array: ["Question", "Incident", ...]
                foreach ($choices as $choice) {
                    if (is_string($choice)) {
                        $formatted[] = ['value' => $choice, 'label' => $choice];
                    } elseif (is_array($choice) && isset($choice['value'])) {
                        $formatted[] = [
                            'value' => $choice['value'],
                            'label' => (string) ($choice['label'] ?? $choice['value']),
                        ];
                    }
                }
            }

            if (!empty($formatted)) {
                // For 'source': remove values the tickets API does not accept.
                // ticket_fields can list Forum(4) and others that are rejected by
                // POST/PUT /api/v2/tickets with "invalid_value".
                if ($fieldName === 'source') {
                    $validSources = [1, 2, 3, 7, 9, 10, 11, 13, 15, 16, 17, 18, 19, 20, 21, 22];
                    $formatted = array_values(array_filter($formatted, static function (array $c) use ($validSources): bool {
                        return in_array((int) $c['value'], $validSources, true);
                    }));
                }

                if (!empty($formatted)) {
                    $parsed[$fieldName] = $formatted;
                }
            }
        }

        return $parsed;
    }

    /**
     * Extracts custom ticket fields (those where default=false).
     * These are rendered as dynamic form fields on the storefront.
     *
     * Each entry: [
     *   'name'     => 'cf_reference_number',
     *   'label'    => 'Warranty Number',
     *   'type'     => 'custom_number',
     *   'required' => false,
     *   'choices'  => [],          // non-empty means dropdown
     * ]
     *
     * @param  array<int, mixed> $ticketFields
     * @return array<int, array<string, mixed>>
     */
    private function extractCustomFields(array $ticketFields): array
    {
        $custom = [];

        foreach ($ticketFields as $field) {
            if (!is_array($field)) {
                continue;
            }

            // Only include non-default (custom) fields
            if (($field['default'] ?? true) !== false) {
                continue;
            }

            // Skip fields not displayed to customers
            if (($field['displayed_to_customers'] ?? false) !== true) {
                continue;
            }

            $name     = $field['name']                  ?? '';
            $label    = $field['label_for_customers']   ?? ($field['label'] ?? $name);
            $type     = $this->normalizeCustomFieldType($field);
            $required = ($field['required_for_customers'] ?? false) === true;

            // Build choices for dropdown fields
            $choices = [];
            if (!empty($field['choices']) && is_array($field['choices'])) {
                foreach ($field['choices'] as $choice) {
                    if (is_string($choice)) {
                        $choices[] = ['value' => $choice, 'label' => $choice];
                    } elseif (is_array($choice) && isset($choice['value'])) {
                        $choices[] = [
                            'value' => $choice['value'],
                            'label' => (string) ($choice['label'] ?? $choice['value']),
                        ];
                    }
                }
            }

            $custom[] = [
                'name'     => $name,
                'label'    => $label,
                'type'     => $type,
                'required' => $required,
                'choices'  => $choices,
            ];
        }

        return $custom;
    }
}

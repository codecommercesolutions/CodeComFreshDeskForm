<?php

declare(strict_types=1);

namespace CodeCom\FreshDeskForm\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Adds four identity columns to freshdesk_form_submission:
 *   - requester_id       : Freshdesk numeric contact ID (auto-resolved after ticket creation)
 *   - facebook_id        : Facebook ID entered by user on the form
 *   - twitter_id         : Twitter handle entered by user on the form
 *   - unique_external_id : Custom external ID (shown only when enabled in plugin config)
 */
class Migration1700000008AddIdentityFields extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1700000008;
    }

    public function update(Connection $connection): void
    {
        $this->addColumnIfNotExists(
            $connection,
            'freshdesk_form_submission',
            'requester_id',
            "VARCHAR(64) NULL COMMENT 'Freshdesk contact/requester ID (resolved after ticket creation)' AFTER `freshdesk_ticket_id`"
        );

        $this->addColumnIfNotExists(
            $connection,
            'freshdesk_form_submission',
            'facebook_id',
            "VARCHAR(255) NULL COMMENT 'Facebook ID of the contact' AFTER `requester_id`"
        );

        $this->addColumnIfNotExists(
            $connection,
            'freshdesk_form_submission',
            'twitter_id',
            "VARCHAR(255) NULL COMMENT 'Twitter handle of the contact' AFTER `facebook_id`"
        );

        $this->addColumnIfNotExists(
            $connection,
            'freshdesk_form_submission',
            'unique_external_id',
            "VARCHAR(255) NULL COMMENT 'Unique external ID of the contact' AFTER `twitter_id`"
        );

        $connection->executeStatement(<<<SQL
            ALTER TABLE `freshdesk_form_submission`
                MODIFY COLUMN `requester_id` VARCHAR(64) NULL,
                MODIFY COLUMN `facebook_id` VARCHAR(255) NULL,
                MODIFY COLUMN `twitter_id` VARCHAR(255) NULL,
                MODIFY COLUMN `unique_external_id` VARCHAR(255) NULL
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function addColumnIfNotExists(
        Connection $connection,
        string $table,
        string $column,
        string $columnDef
    ): void {
        $exists = (int) $connection->fetchOne(
            'SELECT COUNT(*)
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME   = :table
                AND COLUMN_NAME  = :column',
            ['table' => $table, 'column' => $column]
        );

        if ($exists === 0) {
            $connection->executeStatement(
                sprintf('ALTER TABLE `%s` ADD COLUMN `%s` %s', $table, $column, $columnDef)
            );
        }
    }
}

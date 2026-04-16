<?php

declare(strict_types=1);

namespace CodeCom\FreshDeskForm\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Adds extra_fields JSON column to freshdesk_form_submission.
 *
 * This column stores the values of all Freshdesk custom ticket fields
 * (those where default=false) as a JSON object, e.g.:
 * {"cf_reference_number": "1111", "cf_some_dropdown": "option_a"}
 */
class Migration1700000006AddExtraFieldsColumn extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1700000006;
    }

    public function update(Connection $connection): void
    {
        $this->addColumnIfNotExists(
            $connection,
            'freshdesk_form_submission',
            'extra_fields',
            "JSON NULL COMMENT 'Custom Freshdesk ticket field values (default=false fields)' AFTER `freshdesk_ticket_id`"
        );
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

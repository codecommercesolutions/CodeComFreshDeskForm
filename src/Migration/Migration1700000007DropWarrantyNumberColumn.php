<?php

declare(strict_types=1);

namespace CodeCom\FreshdeskForm\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Drops the warranty_number column from freshdesk_form_submission.
 * The field is no longer used on the storefront or admin forms.
 */
class Migration1700000007DropWarrantyNumberColumn extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1700000007;
    }

    public function update(Connection $connection): void
    {
        // Only drop if the column still exists (safe for fresh installs)
        $exists = (int) $connection->fetchOne(
            'SELECT COUNT(*)
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME   = :table
                AND COLUMN_NAME  = :column',
            ['table' => 'freshdesk_form_submission', 'column' => 'warranty_number']
        );

        if ($exists > 0) {
            $connection->executeStatement(
                'ALTER TABLE `freshdesk_form_submission` DROP COLUMN `warranty_number`'
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

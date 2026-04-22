<?php

declare(strict_types=1);

namespace CodeCom\FreshdeskForm\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1700000002AlterProductGroupColumns extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1700000002;
    }

    public function update(Connection $connection): void
    {
        // First, convert existing INT values to VARCHAR
        $sql = <<<SQL
ALTER TABLE `freshdesk_form_submission`
    MODIFY COLUMN `group_id` VARCHAR(255) NULL,
    MODIFY COLUMN `product_id` VARCHAR(255) NULL;
SQL;
        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

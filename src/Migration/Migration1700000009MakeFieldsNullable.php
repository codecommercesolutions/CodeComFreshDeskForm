<?php

declare(strict_types=1);

namespace CodeCom\FreshdeskForm\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1700000009MakeFieldsNullable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1700000009;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `freshdesk_form_submission` MODIFY COLUMN `phone` VARCHAR(255) NULL;');
        $connection->executeStatement('ALTER TABLE `freshdesk_form_submission` MODIFY COLUMN `subject` VARCHAR(255) NULL;');
        $connection->executeStatement('ALTER TABLE `freshdesk_form_submission` MODIFY COLUMN `type` VARCHAR(255) NULL;');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

<?php

declare(strict_types=1);

namespace CodeCom\FreshdeskForm\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1700000001CreateFormSubmissionTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1700000001;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS `freshdesk_form_submission` (
            `id` BINARY(16) NOT NULL,
            `first_name` VARCHAR(255) NOT NULL,
            `last_name` VARCHAR(255) NOT NULL,
            `email` VARCHAR(255) NOT NULL,
            `phone` VARCHAR(255) NOT NULL,
            `warranty_number` VARCHAR(255) NOT NULL,
            `subject` VARCHAR(255) NOT NULL,
            `message` LONGTEXT NOT NULL,
            `type` VARCHAR(255) NULL,
            `group_id` VARCHAR(255) NULL,
            `product_id` VARCHAR(255) NULL,
            `status` INT NULL,
            `source` INT NULL,
            `priority` INT NULL,
            `freshdesk_ticket_id` VARCHAR(255) NULL,
            `created_at` DATETIME(3) NOT NULL,
            `updated_at` DATETIME(3) NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;

        try {
            $connection->executeStatement($sql);
        } catch (Exception) {

        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

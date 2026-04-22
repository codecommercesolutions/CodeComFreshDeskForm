<?php

declare(strict_types=1);

namespace CodeCom\FreshdeskForm\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1700000005AddFieldsAndApiDataTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1700000005;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<SQL
            ALTER TABLE `freshdesk_form_submission`
                MODIFY COLUMN `group_id` VARCHAR(255) NULL,
                MODIFY COLUMN `product_id` VARCHAR(255) NULL,
                MODIFY COLUMN `status` INT NULL,
                MODIFY COLUMN `source` INT NULL,
                MODIFY COLUMN `priority` INT NULL
        SQL);

        // ── 1. Add responder_id / email_config_id / company_id to warranty submission ──
        $this->addColumnIfNotExists(
            $connection,
            'freshdesk_form_submission',
            'responder_id',
            'VARCHAR(255) NULL AFTER `product_id`'
        );
        $this->addColumnIfNotExists(
            $connection,
            'freshdesk_form_submission',
            'email_config_id',
            'VARCHAR(255) NULL AFTER `responder_id`'
        );
        $this->addColumnIfNotExists(
            $connection,
            'freshdesk_form_submission',
            'company_id',
            'VARCHAR(255) NULL AFTER `email_config_id`'
        );

        $connection->executeStatement(<<<SQL
            ALTER TABLE `freshdesk_form_submission`
                MODIFY COLUMN `responder_id` VARCHAR(255) NULL,
                MODIFY COLUMN `email_config_id` VARCHAR(255) NULL,
                MODIFY COLUMN `company_id` VARCHAR(255) NULL
        SQL);

        // ── 2. Create freshdesk_form_api_data table (all 6 JSON columns) ──────
        $connection->executeStatement(<<<SQL
            CREATE TABLE IF NOT EXISTS `freshdesk_form_api_data` (
                `id`            BINARY(16)   NOT NULL,
                `agents`        JSON         NULL COMMENT '/api/v2/agents',
                `email_configs` JSON         NULL COMMENT '/api/v2/email_configs',
                `companies`     JSON         NULL COMMENT '/api/v2/companies',
                `ticket_fields` JSON         NULL COMMENT '/api/v2/ticket_fields',
                `groups`        JSON         NULL COMMENT '/api/v2/groups',
                `products`      JSON         NULL COMMENT '/api/v2/products',
                `created_at`    DATETIME(3)  NOT NULL,
                `updated_at`    DATETIME(3)  NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);

        // ── 3. If table already existed without new columns, add them now ─────
        $this->addColumnIfNotExists(
            $connection,
            'freshdesk_form_api_data',
            'ticket_fields',
            "JSON NULL COMMENT '/api/v2/ticket_fields' AFTER `companies`"
        );
        $this->addColumnIfNotExists(
            $connection,
            'freshdesk_form_api_data',
            'groups',
            "JSON NULL COMMENT '/api/v2/groups' AFTER `ticket_fields`"
        );
        $this->addColumnIfNotExists(
            $connection,
            'freshdesk_form_api_data',
            'products',
            "JSON NULL COMMENT '/api/v2/products' AFTER `groups`"
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

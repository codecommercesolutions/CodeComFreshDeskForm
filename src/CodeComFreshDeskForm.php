<?php

declare(strict_types=1);

namespace CodeCom\FreshDeskForm;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;

class CodeComFreshDeskForm extends Plugin
{
    /**
     * All tables created by this plugin, in dependency-safe drop order.
     */
    private const PLUGIN_TABLES = [
        'freshdesk_form_api_data',
        'freshdesk_form_submission',
    ];

    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);
        $this->applyNullableSubmissionSchema();
    }

    public function update(UpdateContext $updateContext): void
    {
        parent::update($updateContext);
        $this->applyNullableSubmissionSchema();
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        /** @var Connection $connection */
        $connection = $this->container->get(Connection::class);

        foreach (self::PLUGIN_TABLES as $table) {
            try {
                $connection->executeStatement(
                    sprintf('DROP TABLE IF EXISTS `%s`;', $table)
                );
            } catch (Exception) {

            }
        }
    }

    private function applyNullableSubmissionSchema(): void
    {
        /** @var Connection $connection */
        $connection = $this->container->get(Connection::class);

        try {
            $tableExists = (int) $connection->fetchOne(
                'SELECT COUNT(*)
                   FROM information_schema.TABLES
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = :table',
                ['table' => 'freshdesk_form_submission']
            );

            if ($tableExists === 0) {
                return;
            }

            $connection->executeStatement(<<<SQL
                ALTER TABLE `freshdesk_form_submission`
                    MODIFY COLUMN `group_id` VARCHAR(255) NULL,
                    MODIFY COLUMN `product_id` VARCHAR(255) NULL,
                    MODIFY COLUMN `responder_id` VARCHAR(255) NULL,
                    MODIFY COLUMN `email_config_id` VARCHAR(255) NULL,
                    MODIFY COLUMN `company_id` VARCHAR(255) NULL,
                    MODIFY COLUMN `status` INT NULL,
                    MODIFY COLUMN `source` INT NULL,
                    MODIFY COLUMN `priority` INT NULL,
                    MODIFY COLUMN `requester_id` VARCHAR(64) NULL,
                    MODIFY COLUMN `facebook_id` VARCHAR(255) NULL,
                    MODIFY COLUMN `twitter_id` VARCHAR(255) NULL,
                    MODIFY COLUMN `unique_external_id` VARCHAR(255) NULL
            SQL);
        } catch (Exception) {
        }
    }
}

<?php

declare(strict_types=1);

namespace CodeCom\FreshdeskForm\Core\Content\FormApiData;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class FormApiDataDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'freshdesk_form_api_data';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return FormApiDataEntity::class;
    }

    public function getCollectionClass(): string
    {
        return FormApiDataCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            // ── Sync: /api/v2/agents ──────────────────────────────────────────
            new JsonField('agents', 'agents'),
            // ── Sync: /api/v2/email_configs ───────────────────────────────────
            new JsonField('email_configs', 'emailConfigs'),
            // ── Sync: /api/v2/companies ───────────────────────────────────────
            new JsonField('companies', 'companies'),
            // ── Sync: /api/v2/ticket_fields ───────────────────────────────────
            new JsonField('ticket_fields', 'ticketFields'),
            // ── Sync: /api/v2/groups ──────────────────────────────────────────
            new JsonField('groups', 'groups'),
            // ── Sync: /api/v2/products ────────────────────────────────────────
            new JsonField('products', 'products'),
            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}

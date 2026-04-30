<?php

declare(strict_types=1);

namespace CodeCom\FreshdeskForm\Core\Content\FormSubmission;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class FormSubmissionDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'freshdesk_form_submission';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return FormSubmissionEntity::class;
    }

    public function getCollectionClass(): string
    {
        return FormSubmissionCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('first_name', 'firstName'))->addFlags(new Required()),
            (new StringField('last_name', 'lastName'))->addFlags(new Required()),
            (new StringField('email', 'email'))->addFlags(new Required()),
            new StringField('phone', 'phone'),
            new StringField('subject', 'subject'),
            (new LongTextField('message', 'message'))->addFlags(new Required(), new ApiAware(), new AllowHtml()),
            new StringField('type', 'type'),
            new StringField('group_id', 'groupId'),
            new StringField('product_id', 'productId'),
            // ── New fields ─────────────────────────────────────────────────────
            new StringField('responder_id', 'responderId'),
            new StringField('email_config_id', 'emailConfigId'),
            new StringField('company_id', 'companyId'),
            // ───────────────────────────────────────────────────────────────────
            new IntField('status', 'status'),
            new IntField('source', 'source'),
            new IntField('priority', 'priority'),
            new StringField('freshdesk_ticket_id', 'freshdeskTicketId'),
            // ── Identity fields ────────────────────────────────────────────────
            new StringField('requester_id', 'requesterId'),
            new StringField('facebook_id', 'facebookId'),
            new StringField('twitter_id', 'twitterId'),
            new StringField('unique_external_id', 'uniqueExternalId'),
            // ──────────────────────────────────────────────────────────────────
            new JsonField('extra_fields', 'extraFields'),
            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}

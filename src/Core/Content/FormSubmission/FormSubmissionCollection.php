<?php

declare(strict_types=1);

namespace CodeCom\FreshdeskForm\Core\Content\FormSubmission;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<FormSubmissionEntity>
 */
class FormSubmissionCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return FormSubmissionEntity::class;
    }
}

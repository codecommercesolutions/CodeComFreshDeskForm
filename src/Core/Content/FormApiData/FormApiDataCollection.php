<?php

declare(strict_types=1);

namespace CodeCom\FreshdeskForm\Core\Content\FormApiData;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<FormApiDataEntity>
 */
class FormApiDataCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return FormApiDataEntity::class;
    }
}

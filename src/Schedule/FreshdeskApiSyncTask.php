<?php

declare(strict_types=1);

namespace CodeCom\FreshDeskForm\Schedule;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class FreshdeskApiSyncTask extends ScheduledTask
{
    private const TIME_INTERVAL_24_HOURS = 86400;

    public static function getTaskName(): string
    {
        return 'freshdesk.api_data_sync';
    }

    public static function getDefaultInterval(): int
    {
        return self::TIME_INTERVAL_24_HOURS;
    }
}

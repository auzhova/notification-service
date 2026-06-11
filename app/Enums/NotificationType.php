<?php

namespace App\Enums;

enum NotificationType: string
{
    case TRANSACTIONAL = 'transactional';
    case MARKETING = 'marketing';

    public function priority(): NotificationPriority
    {
        return match ($this) {
            self::TRANSACTIONAL => NotificationPriority::HIGH,
            self::MARKETING => NotificationPriority::LOW,
        };
    }
}

<?php

namespace App\Enums;

enum NotificationStatus: string
{
    case QUEUED = 'queued';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::QUEUED => 'В очереди',
            self::SENT => 'Отправлено',
            self::DELIVERED => 'Доставлено',
            self::FAILED => 'Отброшено',
        };
    }
}

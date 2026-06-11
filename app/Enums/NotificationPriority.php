<?php

namespace App\Enums;

enum NotificationPriority: int
{
    case LOW = 1;
    case HIGH = 10;

    public function label(): string
    {
        return match ($this) {
            self::LOW => 'Низкий',
            self::HIGH => 'Высокий',
        };
    }
}

<?php

namespace App\Enums;

enum NotificationChannel: string
{
    case SMS = 'sms';
    case EMAIL = 'email';

    public function label(): string
    {
        return match ($this) {
            self::SMS => 'SMS',
            self::EMAIL => 'Email',
        };
    }
}

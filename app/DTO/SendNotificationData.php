<?php

namespace App\DTO;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;

class SendNotificationData
{
    public function __construct(
        public readonly NotificationType $type,
        public readonly NotificationChannel $channel,
        public readonly string $message,
        public readonly array $recipients,
        public readonly ?string $idempotencyKey = null,
    ) {}
}

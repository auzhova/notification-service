<?php

namespace App\Services;

use App\Contracts\NotificationProviderInterface;
use App\Enums\NotificationChannel;

class ProviderFactory
{
    public function make(
        NotificationChannel $channel
    ): NotificationProviderInterface {
        return match ($channel) {
            NotificationChannel::SMS   => new SmsProviderStub(),
            NotificationChannel::EMAIL => new EmailProviderStub(),
        };
    }
}

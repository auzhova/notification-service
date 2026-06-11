<?php

namespace App\Contracts;

use App\DTO\ProviderResponse;

interface NotificationProviderInterface
{
    public function send(string $recipient, string $message): ProviderResponse;
}

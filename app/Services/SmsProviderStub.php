<?php

namespace App\Services;

use App\Contracts\NotificationProviderInterface;
use App\DTO\ProviderResponse;

class SmsProviderStub implements NotificationProviderInterface
{
    public function __construct(
        private bool $isSuccess = true
    ){
    }

    public function send(string $recipient, string $message): ProviderResponse
    {
        if ($this->isSuccess) {
            return new ProviderResponse(
                success: true,
                providerMessageId: 'sms_' . uniqid(),
            );
        }
        return new ProviderResponse(
            success: false,
            error: 'Ошибка SMS провайдера'
        );
    }
}

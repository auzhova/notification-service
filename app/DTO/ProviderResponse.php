<?php

namespace App\DTO;

class ProviderResponse
{
    public function __construct(
        public bool $success,
        public ?string $providerMessageId = null,
        public ?string $error = null,
    ) {}
}

<?php

namespace App\Notifications\Messages;

use App\Enums\DeliveryStatus;

class EmergencyDeliveryResult
{
    public function __construct(
        public readonly DeliveryStatus $status,
        public readonly ?string $providerReference = null,
        public readonly ?string $failureCode = null,
        public readonly ?string $failureMessage = null,
    ) {}
}

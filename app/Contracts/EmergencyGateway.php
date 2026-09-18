<?php

namespace App\Contracts;

use App\Notifications\Messages\EmergencyDeliveryResult;
use App\Notifications\Messages\EmergencyMessage;

interface EmergencyGateway
{
    public function sendSms(string $to, EmergencyMessage $message): EmergencyDeliveryResult;

    public function placeVoiceCall(string $to, EmergencyMessage $message): EmergencyDeliveryResult;

    public function name(): string;
}

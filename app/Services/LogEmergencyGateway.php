<?php

namespace App\Services;

use App\Contracts\EmergencyGateway;
use App\Enums\DeliveryStatus;
use App\Notifications\Messages\EmergencyDeliveryResult;
use App\Notifications\Messages\EmergencyMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogEmergencyGateway implements EmergencyGateway
{
    public function sendSms(string $to, EmergencyMessage $message): EmergencyDeliveryResult
    {
        Log::info('Smart Cane emergency SMS (log gateway).', [
            'destination' => $to,
            'message' => $message->body,
        ]);

        return new EmergencyDeliveryResult(DeliveryStatus::Delivered, 'log-sms:'.Str::uuid());
    }

    public function placeVoiceCall(string $to, EmergencyMessage $message): EmergencyDeliveryResult
    {
        Log::info('Smart Cane emergency voice call (log gateway).', [
            'destination' => $to,
            'message' => $message->body,
        ]);

        return new EmergencyDeliveryResult(DeliveryStatus::Delivered, 'log-voice:'.Str::uuid());
    }

    public function name(): string
    {
        return 'log';
    }
}

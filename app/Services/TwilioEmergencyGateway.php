<?php

namespace App\Services;

use App\Contracts\EmergencyGateway;
use App\Enums\DeliveryStatus;
use App\Notifications\Messages\EmergencyDeliveryResult;
use App\Notifications\Messages\EmergencyMessage;
use Illuminate\Support\Facades\Http;

class TwilioEmergencyGateway implements EmergencyGateway
{
    public function sendSms(string $to, EmergencyMessage $message): EmergencyDeliveryResult
    {
        $accountSid = (string) config('services.twilio.account_sid');
        $authToken = (string) config('services.twilio.auth_token');
        $from = (string) config('services.twilio.sms_from');

        if ($accountSid === '' || $authToken === '' || $from === '') {
            return new EmergencyDeliveryResult(
                DeliveryStatus::Failed,
                failureCode: 'twilio_not_configured',
                failureMessage: 'Twilio SMS credentials are not configured.',
            );
        }

        $response = Http::asForm()
            ->withBasicAuth($accountSid, $authToken)
            ->timeout(10)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                'To' => $to,
                'From' => $from,
                'Body' => $message->body,
                'StatusCallback' => config('services.twilio.status_callback'),
            ]);

        if ($response->successful()) {
            return new EmergencyDeliveryResult(DeliveryStatus::Accepted, $response->json('sid'));
        }

        return new EmergencyDeliveryResult(
            DeliveryStatus::Failed,
            failureCode: (string) ($response->json('code') ?? $response->status()),
            failureMessage: (string) ($response->json('message') ?? 'Twilio rejected the SMS.'),
        );
    }

    public function placeVoiceCall(string $to, EmergencyMessage $message): EmergencyDeliveryResult
    {
        $accountSid = (string) config('services.twilio.account_sid');
        $authToken = (string) config('services.twilio.auth_token');
        $from = (string) config('services.twilio.voice_from');

        if ($accountSid === '' || $authToken === '' || $from === '') {
            return new EmergencyDeliveryResult(
                DeliveryStatus::Failed,
                failureCode: 'twilio_not_configured',
                failureMessage: 'Twilio voice credentials are not configured.',
            );
        }

        $twiml = '<Response><Say language="en-US">'.htmlspecialchars($message->body, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</Say></Response>';
        $response = Http::asForm()
            ->withBasicAuth($accountSid, $authToken)
            ->timeout(10)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Calls.json", [
                'To' => $to,
                'From' => $from,
                'Twiml' => $twiml,
                'StatusCallback' => config('services.twilio.status_callback'),
            ]);

        if ($response->successful()) {
            return new EmergencyDeliveryResult(DeliveryStatus::Accepted, $response->json('sid'));
        }

        return new EmergencyDeliveryResult(
            DeliveryStatus::Failed,
            failureCode: (string) ($response->json('code') ?? $response->status()),
            failureMessage: (string) ($response->json('message') ?? 'Twilio rejected the voice call.'),
        );
    }

    public function name(): string
    {
        return 'twilio';
    }
}

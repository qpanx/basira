<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Models\NotificationDelivery;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TwilioStatusController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $reference = $request->string('MessageSid')->toString()
            ?: $request->string('CallSid')->toString();

        if ($reference === '') {
            return response()->noContent();
        }

        $status = strtolower($request->string('MessageStatus')->toString() ?: $request->string('CallStatus')->toString());
        $deliveryStatus = match ($status) {
            'delivered', 'completed' => DeliveryStatus::Delivered,
            'failed', 'undelivered', 'busy', 'no-answer', 'canceled' => DeliveryStatus::Failed,
            default => DeliveryStatus::Accepted,
        };
        $delivery = NotificationDelivery::query()->where('provider_reference', $reference)->first();

        if ($delivery === null) {
            return response()->noContent();
        }

        $delivery->forceFill([
            'status' => $deliveryStatus,
            'accepted_at' => $deliveryStatus !== DeliveryStatus::Failed ? ($delivery->accepted_at ?? now()) : $delivery->accepted_at,
            'delivered_at' => $deliveryStatus === DeliveryStatus::Delivered ? now() : $delivery->delivered_at,
            'failed_at' => $deliveryStatus === DeliveryStatus::Failed ? now() : $delivery->failed_at,
            'failure_code' => $deliveryStatus === DeliveryStatus::Failed ? $request->string('ErrorCode')->toString() : $delivery->failure_code,
            'failure_message' => $deliveryStatus === DeliveryStatus::Failed ? $request->string('ErrorMessage')->toString() : $delivery->failure_message,
        ])->save();

        return response()->noContent();
    }
}

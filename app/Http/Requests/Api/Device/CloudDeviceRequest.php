<?php

namespace App\Http\Requests\Api\Device;

use App\Models\Device;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Ramsey\Uuid\Uuid;

abstract class CloudDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    abstract public function isCloudPayload(): bool;

    /** @return array<string, ValidationRule|array<mixed>|string> */
    protected function cloudRules(): array
    {
        /** @var Device $device */
        $device = $this->attributes->get('device');

        return [
            'device_id' => ['required', 'string', Rule::in([$device->serial_number])],
            'timestamp' => ['required', 'date', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,3})?(?:Z|\+00:00)$/', 'before_or_equal:now'],
            'message_id' => ['prohibited'],
            'blind_user_id' => ['prohibited'],
            'received_at' => ['prohibited'],
            'last_seen_at' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }

    /**
     * A stable, device-scoped identity for a retry, independent of JSON key order.
     *
     * @param  array<string, bool|float|int|string|null>  $values
     */
    protected function cloudMessageId(string $kind, array $values): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, json_encode([
            'basira-cloud-v1',
            $this->validated('device_id'),
            $kind,
            Carbon::parse($this->validated('timestamp'))->toISOString(),
            $values,
        ], JSON_THROW_ON_ERROR))->toString();
    }
}

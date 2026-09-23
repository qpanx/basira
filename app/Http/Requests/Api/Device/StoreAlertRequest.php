<?php

namespace App\Http\Requests\Api\Device;

use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class StoreAlertRequest extends CloudDeviceRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->isCloudPayload()) {
            return [
                ...$this->cloudRules(),
                'type' => ['required', Rule::in(array_keys($this->cloudTypes()))],
                'severity' => ['required', $this->input('type') === 'sos_critical'
                    ? Rule::in(['critical']) : Rule::enum(AlertSeverity::class)],
                'value' => $this->input('type') === 'sos_critical' ? ['prohibited'] : [
                    'required', 'numeric', 'decimal:0,3',
                    'between:0,'.match ($this->input('type')) {
                        'water_detected' => '4095',
                        'pit_detected' => '655350',
                        default => '65535',
                    },
                ],
                'lat' => ['required', 'numeric', 'between:-90,90'],
                'lng' => ['required', 'numeric', 'between:-180,180'],
                'distance_cm' => ['prohibited'],
                'resolved_at' => ['prohibited'],
                'resolved_by' => ['prohibited'],
                'resolution' => ['prohibited'],
            ];
        }

        return [
            'message_id' => ['nullable', 'uuid'],
            'type' => ['required', Rule::enum(AlertType::class)],
            'severity' => ['required', Rule::enum(AlertSeverity::class)],
            'distance_cm' => ['nullable', 'integer', 'between:0,65535'],
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:-180,180'],
            'timestamp' => ['nullable', 'date'],
            'device_id' => ['prohibited'],
            'blind_user_id' => ['prohibited'],
            'resolved_at' => ['prohibited'],
            'resolved_by' => ['prohibited'],
            'resolution' => ['prohibited'],
        ];
    }

    public function isCloudPayload(): bool
    {
        return $this->hasAny(['device_id', 'value'])
            || (is_string($this->input('type')) && array_key_exists($this->input('type'), $this->cloudTypes()));
    }

    /** @return array<string, AlertType> */
    private function cloudTypes(): array
    {
        return [
            'front_obstacle' => AlertType::ObstacleFront,
            'upper_obstacle' => AlertType::ObstacleUpper,
            'pit_detected' => AlertType::ObstacleLower,
            'water_detected' => AlertType::WaterHazard,
            'sos_critical' => AlertType::Sos,
        ];
    }

    /** @return array<string, mixed> */
    public function ingestionData(): array
    {
        if (! $this->isCloudPayload()) {
            return $this->validated();
        }

        $type = $this->cloudTypes()[$this->validated('type')];
        $value = $this->validated('value');
        $values = [
            'type' => $type->value,
            'severity' => $this->validated('severity'),
            'sensor_value' => $value === null ? null : (float) $value,
            'lat' => (float) $this->validated('lat'),
            'lng' => (float) $this->validated('lng'),
        ];

        return [
            ...$values,
            'timestamp' => $this->validated('timestamp'),
            'message_id' => $this->cloudMessageId('alert', $values),
            'sensor_unit' => match ($type) {
                AlertType::ObstacleFront, AlertType::ObstacleUpper => 'cm',
                AlertType::ObstacleLower => 'mm',
                AlertType::WaterHazard => 'adc',
                default => null,
            },
            'distance_cm' => match ($type) {
                AlertType::ObstacleFront, AlertType::ObstacleUpper => (int) round($value),
                AlertType::ObstacleLower => (int) round($value / 10),
                default => null,
            },
        ];
    }
}

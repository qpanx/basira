<?php

namespace Database\Factories;

use App\Enums\DeliveryChannel;
use App\Enums\DeliveryStatus;
use App\Models\Alert;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationDelivery>
 */
class NotificationDeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'alert_id' => Alert::factory()->sos(),
            'recipient_user_id' => User::factory()->family(),
            'notification_id' => null,
            'channel' => fake()->randomElement(DeliveryChannel::cases()),
            'provider' => 'log',
            'status' => DeliveryStatus::Delivered,
            'provider_reference' => null,
            'attempt_count' => 1,
            'destination_last_four' => fake()->numerify('####'),
            'queued_at' => now()->subSeconds(30),
            'accepted_at' => now()->subSeconds(20),
            'delivered_at' => now()->subSeconds(10),
            'failed_at' => null,
            'failure_code' => null,
            'failure_message' => null,
        ];
    }
}

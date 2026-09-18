<?php

namespace Database\Seeders;

use App\Enums\AlertResolution;
use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Enums\DeliveryChannel;
use App\Enums\DeliveryStatus;
use App\Enums\DeviceStatus;
use App\Enums\FamilyPermission;
use App\Enums\Language;
use App\Enums\PowerState;
use App\Models\Alert;
use App\Models\ArrivalConfirmation;
use App\Models\BatteryLog;
use App\Models\BlindUser;
use App\Models\Device;
use App\Models\DeviceAssignment;
use App\Models\FamilyBlindUser;
use App\Models\Location;
use App\Models\Ngo;
use App\Models\NotificationDelivery;
use App\Models\SafeZone;
use App\Models\User;
use App\Models\UserFeedback;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();
        $admin = User::factory()->superAdmin()->create([
            'name' => 'Smart Cane Administrator',
            'email' => 'admin@smartcane.test',
            'password' => 'password',
        ]);
        $ngoOne = Ngo::query()->create([
            'name' => 'Cairo Vision Foundation',
            'address' => '12 Nile Street, Cairo',
            'contact_name' => 'Mona Hassan',
            'contact_email' => 'contact@cairovision.test',
            'contact_phone' => '+201001111111',
        ]);
        $ngoTwo = Ngo::query()->create([
            'name' => 'Alexandria Access Network',
            'address' => '8 Corniche Road, Alexandria',
            'contact_name' => 'Omar Nabil',
            'contact_email' => 'hello@alexaccess.test',
            'contact_phone' => '+201002222222',
        ]);
        $staffOne = User::factory()->ngoStaff($ngoOne)->create([
            'name' => 'Cairo NGO Staff',
            'email' => 'staff@cairovision.test',
            'password' => 'password',
        ]);
        $staffTwo = User::factory()->ngoStaff($ngoTwo)->create([
            'name' => 'Alexandria NGO Staff',
            'email' => 'staff@alexaccess.test',
            'password' => 'password',
        ]);
        $familyOne = User::factory()->family()->create([
            'name' => 'Nour Ahmed',
            'email' => 'family@smartcane.test',
            'phone' => '+201011111111',
            'password' => 'password',
        ]);
        $familyTwo = User::factory()->family()->arabic()->create([
            'name' => 'Youssef Ahmed',
            'email' => 'caregiver@smartcane.test',
            'phone' => '+201012222222',
            'password' => 'password',
        ]);

        $blindOne = BlindUser::query()->create([
            'ngo_id' => $ngoOne->getKey(),
            'created_by' => $staffOne->getKey(),
            'name' => 'Ahmed Hassan',
            'phone' => '+201099999999',
            'emergency_notes' => 'Prefers Arabic voice prompts. Emergency contact is Nour.',
        ]);
        $blindTwo = BlindUser::query()->create([
            'ngo_id' => $ngoTwo->getKey(),
            'created_by' => $staffTwo->getKey(),
            'name' => 'Salma Youssef',
            'phone' => '+201088888888',
            'emergency_notes' => 'Keep the cane charged before rehabilitation sessions.',
        ]);

        $permissions = FamilyBlindUser::defaultPermissions();
        $familyOne->blindUsers()->attach($blindOne, [
            'relationship' => 'daughter',
            'permissions' => $permissions,
        ]);
        $familyOne->blindUsers()->attach($blindTwo, [
            'relationship' => 'caregiver',
            'permissions' => [...$permissions, FamilyPermission::ManageSafeZones->value => true],
        ]);
        $familyTwo->blindUsers()->attach($blindTwo, [
            'relationship' => 'brother',
            'permissions' => [
                FamilyPermission::ViewLocation->value => true,
                FamilyPermission::ReceiveAlerts->value => true,
                FamilyPermission::ManageSafeZones->value => false,
            ],
        ]);

        $deviceOne = Device::query()->create([
            'blind_user_id' => $blindOne->getKey(),
            'serial_number' => 'SAC-CAIRO-0001',
            'firmware_version' => '1.4.0',
            'battery_level' => 84,
            'preferred_language' => Language::Arabic,
            'status' => DeviceStatus::Active,
            'settings' => ['obstacle_thresholds_cm' => [200, 100, 30], 'location_interval_seconds' => 30, 'haptic_intensity' => 80],
            'last_seen_at' => $now->copy()->subMinutes(2),
            'last_location_recorded_at' => $now->copy()->subMinutes(2),
            'geofence_evaluated_at' => $now->copy()->subMinutes(2),
        ]);
        $deviceTwo = Device::query()->create([
            'blind_user_id' => $blindTwo->getKey(),
            'serial_number' => 'SAC-ALEX-0002',
            'firmware_version' => '1.3.1',
            'battery_level' => 42,
            'preferred_language' => Language::English,
            'status' => DeviceStatus::Offline,
            'settings' => ['obstacle_thresholds_cm' => [200, 100, 30], 'location_interval_seconds' => 60, 'haptic_intensity' => 65],
            'last_seen_at' => $now->copy()->subHours(3),
            'last_location_recorded_at' => $now->copy()->subHours(3),
            'geofence_evaluated_at' => $now->copy()->subHours(3),
        ]);
        DeviceAssignment::query()->insert([
            ['device_id' => $deviceOne->getKey(), 'blind_user_id' => $blindOne->getKey(), 'assigned_by' => $admin->getKey(), 'assigned_at' => $now->copy()->subMonths(4), 'created_at' => $now->copy()->subMonths(4), 'updated_at' => $now->copy()->subMonths(4)],
            ['device_id' => $deviceTwo->getKey(), 'blind_user_id' => $blindTwo->getKey(), 'assigned_by' => $admin->getKey(), 'assigned_at' => $now->copy()->subMonths(2), 'created_at' => $now->copy()->subMonths(2), 'updated_at' => $now->copy()->subMonths(2)],
        ]);

        SafeZone::query()->create(['blind_user_id' => $blindOne->getKey(), 'name' => 'Ahmed home', 'center_lat' => 30.0444, 'center_lng' => 31.2357, 'radius_meters' => 180]);
        SafeZone::query()->create(['blind_user_id' => $blindOne->getKey(), 'name' => 'Rehabilitation center', 'center_lat' => 30.0626, 'center_lng' => 31.2497, 'radius_meters' => 250]);
        SafeZone::query()->create(['blind_user_id' => $blindTwo->getKey(), 'name' => 'Salma home', 'center_lat' => 31.2001, 'center_lng' => 29.9187, 'radius_meters' => 200]);

        foreach ([$deviceOne, $deviceTwo] as $device) {
            for ($index = 12; $index >= 0; $index--) {
                $recordedAt = $now->copy()->subMinutes($index * 10);
                Location::query()->create([
                    'device_id' => $device->getKey(),
                    'blind_user_id' => $device->blind_user_id,
                    'message_id' => sprintf('00000000-0000-4000-8000-%012d', $device->getKey() * 100 + $index),
                    'lat' => $device === $deviceOne ? 30.0444 + $index * 0.00015 : 31.2001 + $index * 0.0001,
                    'lng' => $device === $deviceOne ? 31.2357 + $index * 0.0001 : 29.9187 + $index * 0.00012,
                    'accuracy' => 5 + ($index % 4) * 2,
                    'recorded_at' => $recordedAt,
                    'received_at' => $recordedAt->copy()->addSeconds(2),
                ]);
                BatteryLog::query()->create([
                    'device_id' => $device->getKey(),
                    'blind_user_id' => $device->blind_user_id,
                    'message_id' => sprintf('10000000-0000-4000-8000-%012d', $device->getKey() * 100 + $index),
                    'battery_level' => min(100, max(8, (int) $device->battery_level + $index - 6)),
                    'power_state' => PowerState::Discharging,
                    'recorded_at' => $recordedAt,
                    'received_at' => $recordedAt->copy()->addSeconds(2),
                ]);
            }
        }

        $warning = Alert::query()->create([
            'device_id' => $deviceOne->getKey(), 'blind_user_id' => $blindOne->getKey(), 'message_id' => '20000000-0000-4000-8000-000000000001',
            'type' => AlertType::ObstacleFront, 'severity' => AlertSeverity::Warning, 'distance_cm' => 95,
            'lat' => 30.045, 'lng' => 31.236, 'occurred_at' => $now->copy()->subMinutes(28),
        ]);
        $sos = Alert::query()->create([
            'device_id' => $deviceOne->getKey(), 'blind_user_id' => $blindOne->getKey(), 'message_id' => '20000000-0000-4000-8000-000000000002',
            'type' => AlertType::Sos, 'severity' => AlertSeverity::Critical, 'lat' => 30.0452, 'lng' => 31.2362,
            'occurred_at' => $now->copy()->subMinutes(12),
        ]);
        Alert::query()->create([
            'device_id' => $deviceTwo->getKey(), 'blind_user_id' => $blindTwo->getKey(), 'message_id' => '20000000-0000-4000-8000-000000000003',
            'type' => AlertType::WaterHazard, 'severity' => AlertSeverity::Info, 'distance_cm' => 40,
            'lat' => 31.201, 'lng' => 29.919, 'occurred_at' => $now->copy()->subDay(),
            'resolution' => AlertResolution::FalsePositive, 'resolution_notes' => 'A puddle was shallow.', 'resolved_at' => $now->copy()->subHours(20), 'resolved_by' => $familyOne->getKey(),
        ]);
        ArrivalConfirmation::query()->create([
            'blind_user_id' => $blindOne->getKey(), 'device_id' => $deviceOne->getKey(), 'message_id' => '30000000-0000-4000-8000-000000000001',
            'lat' => 30.0444, 'lng' => 31.2357, 'confirmed_at' => $now->copy()->subHour(), 'received_at' => $now->copy()->subHour()->addSeconds(2),
        ]);
        foreach ([$familyOne, $familyTwo] as $recipient) {
            if ($recipient->blindUsers()->whereKey($blindOne)->exists()) {
                NotificationDelivery::query()->create([
                    'alert_id' => $sos->getKey(), 'recipient_user_id' => $recipient->getKey(), 'notification_id' => null,
                    'channel' => DeliveryChannel::Sms, 'provider' => 'log', 'status' => DeliveryStatus::Delivered,
                    'provider_reference' => 'demo-'.$recipient->getKey(), 'attempt_count' => 1, 'destination_last_four' => substr($recipient->phone, -4),
                    'queued_at' => $now->copy()->subMinutes(12), 'accepted_at' => $now->copy()->subMinutes(11), 'delivered_at' => $now->copy()->subMinutes(10),
                ]);
            }
        }
        UserFeedback::query()->create(['blind_user_id' => $blindOne->getKey(), 'submitted_by' => $familyOne->getKey(), 'rating' => 5, 'comment' => 'The alerts are clear and helpful.', 'period_start' => $now->copy()->subMonth()->toDateString(), 'period_end' => $now->toDateString()]);

        $admin->tokens()->delete();
        $deviceOne->tokens()->delete();
        $deviceTwo->tokens()->delete();
        $abilities = ['device:heartbeat', 'device:location', 'device:alert', 'device:arrive', 'device:config'];
        $deviceOne->createToken('demo-device-one', $abilities);
        $deviceTwo->createToken('demo-device-two', $abilities);

        $this->command?->info('Demo users: admin@smartcane.test, staff@cairovision.test, family@smartcane.test (password: password).');
    }
}

# Smart Assistive Cane

Smart Assistive Cane is a Laravel 11 backend and Filament 3 operations dashboard for a connected white cane. It stores telemetry from each cane, evaluates safe zones, broadcasts live location/alerts with Reverb, and delivers SOS notifications through database, broadcast, SMS, and voice channels.

## Requirements

- PHP 8.3+ (the project is tested with PHP 8.5)
- Composer 2
- Node.js 20+ and npm
- MySQL 8 for production (SQLite is useful for local development and tests)

## Local setup

```bash
cp .env.example .env
composer install
php artisan key:generate
npm install
php artisan migrate --seed
npm run build
```

The seed creates demo accounts (all use the password `password`):

| Role | Email |
| --- | --- |
| Super admin | `admin@smartcane.test` |
| NGO staff | `staff@cairovision.test` |
| Family / caregiver | `family@smartcane.test` |
| Arabic family caregiver | `caregiver@smartcane.test` |

Open `/admin` for the Filament panel. Navigation, notifications, API messages, and the panel direction follow the selected English or Arabic locale. The user menu contains a language switcher.

## Device authentication

Sanctum tokens are issued against the `Device` model, never against a dashboard user. Create a token from an authenticated administrative workflow or a deployment console:

```php
$device = App\Models\Device::findOrFail($id);
$token = $device->createToken('production-cane', [
    'device:heartbeat', 'device:location', 'device:alert',
    'device:arrive', 'device:config',
])->plainTextToken;
```

Send the token as `Authorization: Bearer <token>`. Device identity, assignment, and all foreign keys are taken from the token; clients cannot submit another `device_id` or `blind_user_id`.

## Device API

All endpoints are rate limited and require a device token with the matching ability:

| Method | Endpoint | Ability |
| --- | --- | --- |
| POST | `/api/device/heartbeat` | `device:heartbeat` |
| POST | `/api/device/location` | `device:location` |
| POST | `/api/device/alert` | `device:alert` |
| POST | `/api/device/arrived` | `device:arrive` |
| GET | `/api/device/config` | `device:config` |

Use `Accept-Language: en` or `Accept-Language: ar` for API responses. If no header is present, the device's preferred language is used. Location and heartbeat/alert records accept a client `message_id`; retries with the same ID are idempotent.

Twilio status callbacks can update delivery timestamps at `/api/webhooks/twilio/status` (configure `TWILIO_STATUS_CALLBACK` to this URL).

## Reverb and queues

Set `BROADCAST_CONNECTION=reverb` and configure the `REVERB_*` values in `.env`, then run:

```bash
php artisan reverb:start
php artisan queue:work --queue=sos,broadcasts,default
```

For local development the default `log` emergency gateway records SMS/voice attempts without contacting Twilio. Set `EMERGENCY_GATEWAY=twilio`, the Twilio credentials, and `EMERGENCY_VOICE_CALLS_ENABLED=true` to use real delivery. The scheduler runs `devices:mark-offline` every minute; run `php artisan schedule:work` in development.

## Testing and quality checks

```bash
php artisan test --compact
vendor/bin/pint --format agent
```

The feature tests cover device-token scoping, idempotent telemetry, localized responses, notifications, NGO/family visibility, and safe-zone transitions.

## Authorization model

- Super admins can access every organization, user, device, and telemetry record.
- NGO staff are restricted to blind users assigned to their NGO.
- Family/caregiver accounts only see linked blind users. Pivot permissions independently control location viewing, alert delivery, and safe-zone management.
- Filament resource queries are scoped as well as protected by policies, so guessed record URLs do not bypass the boundary.

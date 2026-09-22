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
    'device:vision', 'device:ocr', 'device:navigation',
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

## Vision, OCR, and walking navigation

Camera uploads can use configurable HTTP vision/OCR providers. Walking navigation uses Google Places API (New) and Routes API, the existing authenticated GPS pipeline, and scoped Filament 3 navigation history. Arabic and English are supported. Images, OCR text, and detections are not permanently stored.

See [the IoT integration guide](IOT_API.md) for all seven new endpoints, token abilities, request/response examples, provider contracts, environment variables, migration details, and the file inventory. The [local Python inference service](ai-service/README.md) supplies real CPU YOLO detection and Arabic/English PaddleOCR, with setup instructions for macOS Apple Silicon, optional service keys and separate real-inference smoke tests. Set provider configuration in your private `.env` using the guide and run `php artisan migrate` to add `navigation_sessions`; no credentials are assumed. Existing explicit-ability device tokens need the new abilities provisioned.

Keep immediate obstacle detection and warnings on the cane. Cloud vision and Google walking directions do not guarantee accessible paths, accurate obstacle distances, or safe independent travel. The device still needs camera/GPS integration, local sensors, speech output, and field validation.

## Smart Cane Simulator

The Filament dashboard page at `/admin/smart-cane-simulator` (accessible to super admins and NGO staff) exercises the same vision, OCR, and navigation services without any cane hardware:

- **Object detection + speech** — upload a photo, get normalized detections (label, confidence, image-relative position) and a localized English/Arabic sentence read aloud.
- **OCR + speech** — upload a photo of text; the exact extracted text is shown and read aloud in the chosen language.
- **Navigation simulation** — enter origin/destination coordinates, start an OSRM walking route, then step a simulated GPS position forward; turn-by-turn instructions are spoken as they change. This never writes `locations` or `navigation_sessions` rows and warns when the selected device has a live production session.

Speech uses the browser Web Speech API only — no paid TTS or AI keys, and nothing is sent to the server for audio. Voice, volume, and language (`en-US`/`ar-EG`) are persisted in the browser. Verify speech manually in Chrome/Edge/Safari (Firefox support varies); automated tests mock the HTTP providers and do not verify audible output.

To run it locally:

1. Complete the local setup above and sign in as `admin@smartcane.test` or `staff@cairovision.test`.
2. Start the [local Python inference service](ai-service/README.md) for real detections/OCR, or point `VISION_SERVICE_URL`/`OCR_SERVICE_URL` at any provider implementing the same contract.
3. Point `OSRM_SERVICE_URL` at a pedestrian-profile OSRM server and set `OSRM_WALKING_ENABLED=true` (see the [OSRM setup section in IOT_API.md](IOT_API.md#osrm-pedestrian-routing-without-google-billing)).
4. Open **Smart Cane Simulator** from the admin navigation, upload an image or start a simulation, and use the language/voice controls in the toolbar.

```bash
php artisan test --compact tests/Feature/SmartCaneSimulatorTest.php
```

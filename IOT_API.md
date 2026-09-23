# BASIRA camera and navigation API

These endpoints extend the existing device API. They require a **Device Sanctum bearer token**, an enabled device, and an assigned BlindUser. Dashboard-user tokens are rejected. Existing heartbeat, GPS, alerts, arrival confirmation, safe zones, notifications, and Reverb channels remain available.

## Authentication and language

Send `Authorization: Bearer <device-token>` and `Accept: application/json`. Provision tokens through the existing trusted administrative/deployment workflow:

```php
$token = $device->createToken('camera-navigation', [
    'device:vision', 'device:ocr', 'device:navigation',
    'device:location', 'device:config',
])->plainTextToken;
```

Existing tokens with explicit abilities need to be reissued with the required new abilities. Wildcard tokens retain their existing behavior. For camera/navigation requests, do not send `device_id`, `blind_user_id`, or navigation `session_id`; identity and the active session come from the token. The Cloud telemetry formats below require a serial-number `device_id` that must match that same authenticated device. Stop navigation and revoke old tokens before transferring a cane to another person.

Use `Accept-Language: ar` or `en`. With no header, the existing middleware uses the device's preferred language. Vision/OCR/search/start also accept `language: ar|en` to select provider output. A navigation session retains its starting language. Provider labels and text are not translated or invented by Laravel. Google and OCR language support depends on the provider and source material.

## Endpoints

| Method | Endpoint | Ability | Default requests/minute/device |
| --- | --- | --- | --- |
| POST | `/api/device/vision/detect` | `device:vision` | 12 |
| POST | `/api/device/ocr/read` | `device:ocr` | 12 |
| GET | `/api/device/navigation/search` | `device:navigation` | 10 |
| POST | `/api/device/navigation/start` | `device:navigation` | 5 |
| GET | `/api/device/navigation/active` | `device:navigation` | 30, shared with stop |
| POST | `/api/device/navigation/update` | `device:navigation` | 60 |
| POST | `/api/device/navigation/stop` | `device:navigation` | 30, shared with active |

Successful responses contain `success: true` and `data`. Failures contain `success: false`, `code`, and `message`; validation errors also contain `errors`. Dates are UTC ISO 8601. Coordinates use WGS84 decimal degrees. Examples below are synthetic contract examples, not Google search results.

## Cloud telemetry (BASIRA Cloud Data Spec)

The PDF JSON bodies are accepted unchanged through existing POST endpoints:

| PDF payload | HTTP path | Existing token ability |
| --- | --- | --- |
| Location (`device_id`, `timestamp`, `lat`, `lng`, `gps_valid`) | `/api/device/location` | `device:location` |
| Battery (`device_id`, `timestamp`, `voltage`, `percentage`) | `/api/device/heartbeat` | `device:heartbeat` |
| Status (`device_id`, `timestamp`, `online`) | `/api/device/heartbeat` | `device:heartbeat` |
| Alerts (`device_id`, `timestamp`, `type`, `severity`, `value`, `lat`, `lng`) | `/api/device/alert` | `device:alert` |
| SOS (`device_id`, `timestamp`, `type: sos_critical`, `severity: critical`, `lat`, `lng`) | `/api/device/alert` | `device:alert` |

No endpoint was added or renamed. The PDF specifies MQTT topics, not Cloud Functions' HTTP destinations; verify the Terraform destinations against this table before rollout. Do not equate an MQTT topic ending in `/battery`, `/status`, `/alerts`, or `/sos` with an existing HTTP endpoint.

Reuse the existing Device Sanctum bearer token with the listed abilities. No token creation or rotation is needed. Serial numbers are validation inputs, never authentication: mismatches return 422 without writes. Missing/invalid tokens return 401; disabled/unassigned devices and missing abilities remain forbidden. No demonstration-device identity is hardcoded.

Legacy request formats and responses remain supported. Battery-only events do not change firmware or require a firmware field; status-only events do not change battery or create a battery log. `gps_valid: false` returns 200 with `data.ignored: true` and `data.reason: invalid_gps_fix`, without writing location history, moving the map, or evaluating safe zones.

Cloud alert types map to existing enums: `front_obstacle` → `obstacle_front`, `upper_obstacle` → `obstacle_upper`, `pit_detected` → `obstacle_lower`, `water_detected` → `water_hazard`, and `sos_critical` → `sos`. Responses retain these existing enum names. Severities are `info`, `warning`, and `critical`; SOS requires `critical`. Raw sensor values and units are retained. Front/upper readings use cm; pit readings use mm, with rounded cm stored for existing displays; water readings use raw ADC (0–4095) and never populate distance. Voltage is nullable decimal(5,3); sensor values are nullable decimal(12,3).

Cloud timestamps must be UTC ISO 8601 (`Z` or `+00:00`), optionally with up to three fractional digits, and cannot be in the future. Historical events are accepted. Location/battery `recorded_at` and alert `occurred_at` retain event time; location/battery `received_at` and alert `created_at` represent server receipt. The latest status report retains its own event time, receipt time, and `online` value separately from effective device status.

Status updates are serialized per device. Older or equal status timestamps do not reapply; an offline report cannot override activity received at or after its event time. Fresh online/activity reports preserve maintenance mode. Backlogs older than `DEVICE_OFFLINE_AFTER_MINUTES` (default 10) do not revive an offline cane. Keep the existing `devices:mark-offline` scheduled command running for silent devices. Older battery/location reports remain history without replacing the newest readings.

Cloud location, battery, and alert retries derive stable UUIDs from the device, event kind, timestamp, and normalized values and reuse the existing unique indexes and device locks. Same JSON with reordered keys remains a retry; changed values or timestamps represent a new event. With no upstream event ID, two identical events with the same timestamp cannot be distinguished. A retry returns 200 and does not re-notify or refresh connectivity; a new location/alert returns 201. SOS uses the existing events, `SendSosNotifications` job, `sos` queue, and dedicated rate limit. Retry 429 responses after `Retry-After`; keep the original payload and timestamp when retrying.

Deploy through the normal release process, running `php artisan migrate --force` before activating the updated code, then `php artisan optimize` and `php artisan queue:restart`. The additive migration introduces only nullable columns on existing tables; it does not alter tokens or remove records. Its rollback drops the new telemetry columns, so prefer a forward fix after data has been ingested. Existing workers must consume the `sos` queue, and the scheduler must keep running.

Safely test all five exact PDF examples, invalid inputs, token boundaries, retries, delayed messages, and SOS dispatch with:

```bash
php artisan test --compact tests/Feature/CloudDeviceApiTest.php
```

These tests use an isolated test database, test-only tokens, notification/queue fakes, and blocked outbound HTTP. For manual checks, send the unchanged PDF JSON to the mapped endpoint in a staging environment with an existing staging token in the Authorization header. Use a current UTC timestamp to test connectivity; the historical PDF timestamp intentionally does not prove the cane is online. Check stored voltage/percentage, location history, sensor units, and effective status, then resend the same payload to check deduplication. Test alerts and SOS through the automated fakes unless the staging environment has deliberately isolated notification recipients and emergency delivery. Do not send a live SOS as a smoke test. Never log or print bearer tokens.

## Camera → Vision

Send a multipart POST with `image` and optional `language`. JPEG, PNG, and WebP content are accepted, up to 5120 KiB and 8192 pixels on either axis. SVG, GIF, non-images, and oversized files are rejected. Match PHP's `upload_max_filesize`, `post_max_size`, and proxy body limits to the configured limits (multipart needs some additional overhead).

```bash
curl "$BASIRA_BASE_URL/api/device/vision/detect" \
  -H "Authorization: Bearer $DEVICE_TOKEN" -H 'Accept: application/json' \
  -H 'Accept-Language: en' -F 'image=@capture.jpg'
```

HTTP 200:

```json
{
  "success": true,
  "data": {
    "objects": [{"label": "person", "confidence": 0.91, "position": "front"}],
    "message": "1 objects recognized. Use local cane sensors for immediate obstacle warnings."
  },
  "meta": {"safety_notice": "Cloud scene recognition is non-critical. It does not estimate reliable distances or guarantee safety. Keep local obstacle detection active."}
}
```

An empty detection list is a valid result, **not evidence that the path is clear**. Low-confidence results are filtered. Administrators can use the existing Device settings editor to set `vision_enabled` (boolean or `true`/`false` string) and `vision_min_confidence` (0–1). These settings are already available via `/api/device/config`; increment `configuration_version` when changing configuration. Never put service credentials in device settings, which are returned to the cane.

## Camera → OCR

Send the same multipart image format to `/api/device/ocr/read`. Limits are independently configurable.

```bash
curl "$BASIRA_BASE_URL/api/device/ocr/read" \
  -H "Authorization: Bearer $DEVICE_TOKEN" -H 'Accept: application/json' \
  -F 'image=@sign.jpg' -F 'language=ar'
```

HTTP 200:

```json
{"success":true,"data":{"text":"جامعة المنصورة","language":"ar"}}
```

The returned text preserves line breaks and wording; outer whitespace and CRLF line endings are normalized. `language` may be `null` if the provider cannot identify it. Empty or whitespace-only text returns HTTP 422 `no_text_detected`. Provider summaries and interpretations are discarded. The device may read `data.text` using its own TTS engine; Laravel does not generate speech audio.

## GPS → Navigation

`NAVIGATION_PROVIDER=google` is the default. The examples in this section show Google Places destinations. For development without billing, select `osrm` and follow the [OSRM setup](#osrm-pedestrian-routing-without-google-billing) below; the same session endpoints accept a coordinate destination identifier returned by search.

### 1. Search

```http
GET /api/device/navigation/search?query=Library&language=en
Authorization: Bearer <device-token>
Accept: application/json
```

HTTP 200:

```json
{"success":true,"data":[{"place_id":"returned-google-place-id","name":"Provider destination name","address":"Provider address","latitude":30.001,"longitude":31}],"meta":{"attribution":"Google Maps"}}
```

With the Google provider, the backend uses Places API (New) Text Search and returns at most five candidates. No matches returns `data: []`. Have the user confirm the intended destination, using speech where appropriate; never silently treat the first ambiguous match as the user's choice. Present Google Maps attribution in the companion interface or equivalent user-facing flow.

### 2. Supply a recent GPS fix, then start

Use existing `POST /api/device/location` with `device:location` first:

```json
{"message_id":"d6c7aa32-e3dd-4afd-a549-595461b6f2c8","lat":30,"lng":31,"accuracy":5,"timestamp":"2026-09-21T10:00:00Z"}
```

Use the **actual current UTC time**, not the example timestamp. Start navigation with JSON:

```http
POST /api/device/navigation/start
Content-Type: application/json
Authorization: Bearer <device-token>
```

```json
{"destination_place_id":"returned-google-place-id","language":"en"}
```

The origin comes exclusively from this device's stored GPS fixes for its current BlindUser. It must be no older than 120 seconds, not future-dated, and have reported accuracy at most 50 meters. A missing/unknown/inaccurate/stale fix returns 422 `recent_location_required`. Device authentication establishes the source; this is not cryptographic GPS attestation and cannot detect GPS spoofing.

With the Google provider, Laravel resolves the place with Places Details (New), then calls Routes API `computeRoutes` with `travelMode: WALK`. HTTP 201 creates a session:

```json
{
  "success": true,
  "data": {
    "session_id": 15,
    "status": "active",
    "destination": {"place_id":"returned-google-place-id","name":"Provider destination name","address":"Provider address","latitude":30.001,"longitude":31},
    "language": "en",
    "distance_meters": 111,
    "estimated_duration_seconds": 90,
    "steps": [{"instruction":"Walk straight","distance_meters":111}],
    "current_step_index": 0,
    "instruction": "Walk straight",
    "off_route": false,
    "warnings": [],
    "attribution": "Google Maps",
    "routing_provider": "google",
    "safety_notice": "Walking routes may lack sidewalks or pedestrian paths and may contain obstacles. These instructions do not guarantee accessible or safe independent travel.",
    "started_at": "2026-09-21T10:00:00Z",
    "completed_at": null,
    "cancelled_at": null
  }
}
```

Retrying start for the same destination returns the existing active session with HTTP 200, without another provider call. A different destination while active returns 409; stop first. Requests for the same device use an atomic cache lock, and creation checks the device under a database lock. External HTTP calls run outside database transactions. Use a shared database or Redis cache store across application instances, not `array` or per-host file storage.

### 3. Active navigation

`GET /api/device/navigation/active` returns HTTP 200 with the same session data shape, or `{"success":true,"data":null}`. There is no API for fetching another session by ID.

### 4. Update GPS and progress

`POST /api/device/navigation/update` accepts the same GPS fields as the existing location endpoint. `accuracy` is required and must meet the navigation accuracy threshold. Timestamp must be current or within the configured freshness interval; future timestamps are rejected.

```json
{"message_id":"9676f5a0-fb95-4d37-bd03-a9c7b08e059c","lat":30.0001,"lng":31,"accuracy":5,"timestamp":"2026-09-21T10:00:10Z"}
```

This endpoint requires `device:navigation` and reuses `RecordDeviceLocation`: the same location table, message ID deduplication, safe-zone checks, notifications, and `location.updated` Reverb broadcast. Send navigation fixes here instead of duplicating them on both GPS endpoints. GPS sent only to the original location endpoint does not independently advance navigation; update uses the newest available fix. Duplicate or older fixes cannot rewind progress.

The response uses the same session data shape. `current_step_index` is zero-based. Speak the current plain-text `instruction` when it changes; do not repeat it on every GPS update. When off route, `instruction` is null. The route deviation threshold is the larger of 50 meters and twice the fix's reported accuracy. Deviation is measured against route segments, not merely distance to maneuver points.

Each session stores its `routing_provider`; rerouting and attribution retain that provider after a configuration change. Recalculation happens no more than once per 60 seconds per session, including failed attempts, and never on every GPS fix. Provider failure returns 503 (502 for malformed responses), while retaining the active session and the new GPS fix. The device should pause stale directions and use backoff, its local sensors, and a caregiver workflow as needed. Poll active to recover current state after ambiguous network failures.

Progress advances when a fix is within the configured 20-meter radius of the current step endpoint. Passing the last step marks the session `completed` with `completed_at`, and `instruction` becomes null. This is an approximate GPS-based completion signal, **not a safe-arrival confirmation**; it does not invoke `/api/device/arrived` or send a safe-arrival notification. Missed maneuvers, overlapping paths, urban GPS drift, and indoor travel require device/user handling. Route distance and ETA describe the most recently calculated route, not continuously calculated remaining distance/time.

### 5. Stop

`POST /api/device/navigation/stop` with `{}` cancels this device's active session. HTTP 200 returns the same data shape with `status: cancelled` and `cancelled_at`. Repeating it returns `data: null`. No Google request is made. No session IDs are accepted.

### Dashboard history

The Filament 3 **Navigation history** resource shows sessions and includes an active-status filter and 30-second refresh. It stores device/person ownership, origin and last GPS references, destination, routing provider, route distance/ETA, status and timestamps. The provider is also shown in the history table. Caregivers require the existing `can_view_location` permission; NGO staff see only their NGO; administrators see all. Queries and the policy enforce the boundary. History remains linked to the original person after device reassignment. Stop active navigation before reassignment; the next start cancels any abandoned session from a previous assignment.

## Errors and retries

```json
{"success":false,"code":"provider_unavailable","message":"The external service is temporarily unavailable. Try again later."}
```

| HTTP | Code | Handling |
| --- | --- | --- |
| 401 | `device_token_required` | Supply a valid Device token, not a dashboard token. |
| 403 | `missing_token_ability`, `device_disabled`, `device_unassigned` | Fix provisioning/assignment; do not retry continuously. |
| 403 | `feature_disabled` | Vision is disabled in device settings. |
| 422 | `validation_error` | Inspect `errors`; fix image, coordinates, timestamp, query, language, or prohibited identity fields. |
| 422 | `no_text_detected` | Capture a clearer image. No text is invented. |
| 422 | `recent_location_required` | Obtain and transmit a fresh accurate GPS fix. |
| 422 | `destination_not_found`, `route_not_found` | Ask the user to select another destination. |
| 404 | `navigation_not_active` | Start a session before updating. |
| 409 | `navigation_already_active` | Stop before changing destinations. |
| 409 | `navigation_busy` | Another navigation mutation is in progress; retry with backoff. |
| 409 | `device_assignment_changed` | Device was disabled/reassigned during processing; recheck provisioning. |
| 429 | `rate_limited` | Respect `Retry-After`; retain local functionality. |
| 502 | `invalid_provider_response` | Provider contract is invalid; investigate integration without logging images/text. |
| 503 | `provider_not_configured` | Configure the provider; no fallback inference or location data is generated. |
| 503 | `provider_unavailable` | Network timeout, quota, authorization, redirect, or provider HTTP failure; use bounded backoff. |

API errors from proxies/PHP body limits may precede Laravel and have a different body; handle HTTP status even if JSON cannot be parsed. New success/error fields are additive to existing device middleware errors.

## Provider contracts and configuration

No new Composer/npm dependency is required. No production credentials are included. Set deployment environment variables or real values in your private `.env`, then refresh cached configuration as appropriate.

### Vision and OCR HTTP providers

`VISION_SERVICE_URL` and `OCR_SERVICE_URL` are **full POST endpoint URLs**. Laravel sends multipart `image` under a generated filename and `language: en|ar`, with `Accept: application/json`. If configured, the API key is sent as a bearer token. It never sends the device token or caller's filename. Configure trusted HTTPS URLs for deployment; redirects are rejected to avoid forwarding images or secrets to another host.

Vision provider success must be HTTP 2xx with a top-level object:

```json
{"objects":[{"label":"person","confidence":0.91,"position":"front"}]}
```

At most 100 objects; labels at most 120 characters; confidence 0–1; positions `left`, `front`, `right`, or `unknown`. An empty array is valid. Provider prose is not used as a safety instruction.

OCR provider success:

```json
{"text":"Actual extracted text\nSecond line","language":"en"}
```

`text` is required (maximum 50,000 characters); an empty string means unreadable. `language` is required but can be `en`, `ar`, or null. A custom FastAPI/Tesseract service must adapt its native output to this contract. `OCRProvider` is container-bound to `HttpOCRProvider`; replace that binding to add another implementation. Laravel does not bundle a model, Tesseract, or Google Cloud Vision adapter.

Provider clients use a 3-second connection timeout and configurable total timeout capped at 20 seconds. They do not automatically retry paid POSTs. Logs contain provider name/status only, never image data, extracted text, URLs with credentials, or raw provider errors. Uploaded images use PHP temporary files only; no camera frames, OCR text, or detection event history are permanently stored. Consequently there are no vision/OCR history tables or dashboard history views in this implementation. Providers have their own retention policies and must be configured accordingly.

### Google Maps

Enable **Places API (New)** and **Routes API**, configure billing/quota, and set a server-side `GOOGLE_MAPS_API_KEY` restricted to the required APIs and your deployment's network where supported. The key is sent through `X-Goog-Api-Key`, never API responses or device settings. Field masks request only the used fields. No live Google calls are made in tests.

API references: [Places Text Search](https://developers.google.com/maps/documentation/places/web-service/text-search), [Place Details](https://developers.google.com/maps/documentation/places/web-service/place-details), [Routes computeRoutes](https://developers.google.com/maps/documentation/routes/reference/rest/v2/TopLevel/computeRoutes).

Navigation stores provider destination/route data for history. Before production rollout, set a data retention policy appropriate to the users' consent and your Google Maps agreement, and review provider attribution/storage requirements. There is no automatic history pruning job in this change.

### OSRM pedestrian routing without Google billing

OSRM provides routing, not place-name search. BASIRA separates `RoutingProvider` (normalized walking routes and attribution) from `DestinationProvider` (search and destination resolution). `GoogleRoutingProvider` delegates to the unchanged `GoogleMapsService`. `OsrmRoutingProvider` uses a configured OSRM HTTP backend; its companion `CoordinateDestinationProvider` resolves coordinates locally. There is no automatic fallback between providers.

Add these settings to your private `.env`, then run `php artisan config:clear` (or rebuild your deployment's configuration cache):

```dotenv
NAVIGATION_PROVIDER=osrm
OSRM_SERVICE_URL=http://127.0.0.1:5000
OSRM_PROFILE=foot
OSRM_WALKING_ENABLED=true
OSRM_TIMEOUT=10
OSRM_MAX_SNAP_DISTANCE_METERS=50
```

**Set `OSRM_WALKING_ENABLED=true` only after confirming the server's data was built with a pedestrian profile.** The default is false, and there is no default public OSRM endpoint. Setting the URL path to `foot` or `walking` does not convert a driving graph into a pedestrian graph. OSRM selects its transport behavior when extracting OSM data with a Lua profile. See the [OSRM profiles documentation](https://github.com/Project-OSRM/osrm-backend/wiki/Profiles) and [HTTP API](https://project-osrm.org/docs/v5.24.0/api/).

BASIRA additionally requires every returned step to declare `mode: walking`. A driving response, or mixed walking/ferry/cycling response, is rejected with HTTP 503 `walking_routing_unavailable`, even if the requested URL contains `/foot/`. Missing transport metadata is an invalid provider response. This is a defensive check, not remote attestation of the backend's graph or a guarantee of accessible travel. Use a backend you operate or one whose pedestrian configuration you have verified; do not use the default driving demo as a walking service.

#### Run a pedestrian backend

Obtain an OSM `.osm.pbf` extract covering the intended area, outside this application repository. Use OSRM's `foot.lua` during extraction and keep the generated graph files together. The following adapts the [official OSRM Docker workflow](https://github.com/Project-OSRM/osrm-backend#quick-start); it does not run automatically as part of BASIRA installation:

```bash
# Set this to an existing directory containing region.osm.pbf.
OSRM_DATA_DIR=/absolute/path/to/osrm-data
# Pin a tested version or digest for reproducible deployments.
OSRM_IMAGE=ghcr.io/project-osrm/osrm-backend

docker run --rm -v "$OSRM_DATA_DIR:/data" "$OSRM_IMAGE" \
  osrm-extract -p /opt/foot.lua /data/region.osm.pbf
docker run --rm -v "$OSRM_DATA_DIR:/data" "$OSRM_IMAGE" \
  osrm-partition /data/region.osrm
docker run --rm -v "$OSRM_DATA_DIR:/data" "$OSRM_IMAGE" \
  osrm-customize /data/region.osrm
docker run --rm -p 127.0.0.1:5000:5000 -v "$OSRM_DATA_DIR:/data" "$OSRM_IMAGE" \
  osrm-routed --algorithm mld /data/region.osrm
```

Run each preprocessing step only after the previous one succeeds. Use the same OSRM version for extraction, partitioning, customization, and serving. Review the [foot profile](https://github.com/Project-OSRM/osrm-backend/blob/master/profiles/foot.lua) for your deployed version and test local pedestrian-only paths. Map preprocessing needs disk space, RAM, and time; a small regional extract is preferable for development. If Laravel runs in a container, use the backend's reachable service hostname rather than that container's loopback address. Production endpoints should use trusted network access and HTTPS where appropriate.

#### Select a destination without a geocoder

Search using **latitude,longitude** in decimal degrees:

```http
GET /api/device/navigation/search?query=30.0005,31.001&language=en
```

The coordinates here illustrate the format only. Supply a destination you have chosen and verified for your test area.

```json
{
  "success": true,
  "data": [{
    "place_id": "coordinates_300005000_310010000",
    "name": "Coordinates 30.0005, 31.001",
    "address": "",
    "latitude": 30.0005,
    "longitude": 31.001
  }],
  "meta": {"attribution": "User-supplied coordinates"}
}
```

Pass the returned ID unchanged to the existing start endpoint:

```json
{"destination_place_id":"coordinates_300005000_310010000","language":"en"}
```

The origin still comes from the authenticated device's latest trusted GPS record; callers cannot provide an origin or another device's identity. Coordinate IDs contain signed latitude/longitude integers at 7-decimal precision; they represent caller-selected destinations, not Google or OSM place IDs. Search returns a coordinate label with an empty address, never a fabricated building name. Inputs outside the coordinate bounds are rejected. Place-name queries return 422 `coordinate_destination_required`. A named-place geocoder, such as a separately configured service implementing `DestinationProvider`, is not bundled with this change.

OSRM requests use **longitude,latitude** internally, request full GeoJSON geometry and steps, and constrain snapping distance. Replies retain the existing distance, ETA, steps, progress, safety notice, and session fields; they also include `routing_provider: osrm` and `attribution: © OpenStreetMap contributors`. Preserve that attribution and link to [OpenStreetMap copyright](https://www.openstreetmap.org/copyright) in a companion interface where available.

Instructions are rendered from OSRM maneuvers into English or Arabic; road names remain as supplied by OSM. Step completion uses the final coordinate of each step geometry, not the maneuver's starting coordinate. Maneuvers that cannot be described reliably return 422 `route_instructions_unavailable`; the backend does not invent directions. OSRM ETA comes from the pedestrian profile and is only an estimate. Ferries and other mixed-mode routes are deliberately unsupported in this adapter.

#### Provider selection and session history

- `NAVIGATION_PROVIDER=google` selects the existing Google Places/Routes integrations for **new** sessions and searches. Google credentials/billing are still required for these operations.
- `NAVIGATION_PROVIDER=osrm` selects coordinate destination search and the configured pedestrian OSRM backend for **new** sessions. No Google API requests or credentials are involved.
- Existing sessions keep their stored provider for rerouting and attribution. Existing database rows become `google` through the additive migration. If Google billing is unavailable, stop an active Google session and start an OSRM session with a coordinate destination; the application will not silently reinterpret its Google place ID.
- Switching the default does not affect reading/stopping an existing session, GPS ownership, caregiver/NGO visibility, location broadcasts, or history. Provider-specific backend URLs remain deployment settings; keep them available while their sessions are active.
- Rerouting still observes the configured deviation threshold and cooldown, including failed attempts. Failed calls preserve session/history and GPS data. Unknown provider names return 503 `navigation_provider_invalid` rather than falling back.

| Variable | Default | Purpose |
| --- | --- | --- |
| `NAVIGATION_PROVIDER` | `google` | `google` or `osrm`, used for searches and new sessions |
| `OSRM_SERVICE_URL` | blank | Trusted OSRM base URL, without `/route/v1/...` |
| `OSRM_PROFILE` | `foot` | Endpoint profile path segment; does not select/rebuild the server graph |
| `OSRM_WALKING_ENABLED` | `false` | Operator confirmation that the server was prepared for pedestrian routing |
| `OSRM_TIMEOUT` | `10` | Total request timeout in seconds, bounded to 1–20; connection timeout is 3 seconds |
| `OSRM_MAX_SNAP_DISTANCE_METERS` | `50` | Maximum start/destination snapping radius, bounded to 1–1000 meters |

`NoRoute` and `NoSegment` return 422 `route_not_found`. Network failures, non-success HTTP responses, and redirects return sanitized 503 errors; malformed route data returns 502. No provider response bodies or GPS-containing URLs are logged. Requests are not automatically retried. Automated tests use `Http::fake()` and block unexpected requests, so no OSRM process, map download, Google credentials, or hardware is required to run them.

This removes the Google billing dependency for coordinate-based navigation development. It does not provide offline maps on the cane, a production geocoder, indoor routing, accessible-path verification, or obstacle detection. Pedestrian OSM coverage varies, and the backend itself must be operated and updated. Local cane obstacle warnings remain essential.

### New environment variables

Set these in your private `.env` or deployment environment; URLs and credentials default to blank. The OSRM/provider variables are documented above.

| Variable | Default | Purpose |
| --- | --- | --- |
| `VISION_SERVICE_URL` | blank | Full vision inference endpoint |
| `VISION_SERVICE_API_KEY` | blank | Optional provider bearer key |
| `VISION_SERVICE_TIMEOUT` | 10 | Total HTTP timeout in seconds, bounded to 1–20 |
| `VISION_MAX_IMAGE_KB` | 5120 | Upload limit in KiB |
| `VISION_MIN_CONFIDENCE` | 0.5 | Default detection confidence filter |
| `OCR_SERVICE_URL` | blank | Full OCR endpoint |
| `OCR_SERVICE_API_KEY` | blank | Optional provider bearer key |
| `OCR_SERVICE_TIMEOUT` | 10 | Total HTTP timeout in seconds, bounded to 1–20 |
| `OCR_MAX_IMAGE_KB` | 5120 | Upload limit in KiB |
| `GOOGLE_MAPS_API_KEY` | blank | Server Places/Routes API key |
| `GOOGLE_MAPS_TIMEOUT` | 10 | Timeout per Google request, bounded to 1–20 seconds |
| `NAVIGATION_LOCATION_MAX_AGE_SECONDS` | 120 | GPS freshness limit |
| `NAVIGATION_MAX_ACCURACY_METERS` | 50 | Maximum accepted reported GPS error |
| `NAVIGATION_DEVIATION_METERS` | 50 | Minimum route deviation threshold |
| `NAVIGATION_REROUTE_COOLDOWN_SECONDS` | 60 | Minimum interval between recalculation attempts |
| `NAVIGATION_ARRIVAL_RADIUS_METERS` | 20 | Approximate step completion radius |
| `DEVICE_VISION_RATE_LIMIT` | 12 | Vision calls/minute/device |
| `DEVICE_OCR_RATE_LIMIT` | 12 | OCR calls/minute/device |
| `DEVICE_NAVIGATION_SEARCH_RATE_LIMIT` | 10 | Searches/minute/device |
| `DEVICE_NAVIGATION_START_RATE_LIMIT` | 5 | Starts/minute/device |
| `DEVICE_NAVIGATION_UPDATE_RATE_LIMIT` | 60 | Updates/minute/device |
| `DEVICE_NAVIGATION_READ_RATE_LIMIT` | 30 | Shared active/stop requests/minute/device |

## Migrations and local development

After reviewing the migration, run the normal additive migration command:

```bash
php artisan migrate
```

The migration `2026_09_21_063759_create_navigation_sessions_table.php` creates navigation history with foreign keys and device/status, person/start-time, and status/start-time indexes. It references existing locations rather than storing a second GPS stream. Foreign keys restrict deletion of referenced devices, people, and origin/latest GPS records, following existing telemetry conventions. Rolling it back deletes navigation history; use forward fixes on deployed systems. The additive `2026_09_21_211514_add_routing_provider_to_navigation_sessions_table.php` migration adds `routing_provider`, defaulting existing rows to `google` regardless of the current `.env` setting. Apply it before using this version. No existing database was reset as part of implementation.

For **real local image recognition**, use the CPU FastAPI service in [`ai-service/README.md`](ai-service/README.md). It runs YOLO11n and PaddleOCR with separate Arabic/English models and implements the existing raw provider contracts. No Laravel controller changes are needed. Start it on port 8091, set `VISION_SERVICE_URL=http://127.0.0.1:8091/detect` and `OCR_SERVICE_URL=http://127.0.0.1:8091/read`, and match each optional service API key between Laravel and Python. Laravel still authenticates devices; Python authenticates Laravel. The service guide covers Python 3.12 on Apple Silicon, model downloads, curl requests, upload limits, Docker, mocked contract tests and separate opt-in real inference tests.

The Python service returns empty text when OCR finds nothing confident; Laravel retains its existing 422 `no_text_detected` behavior. Arabic is a selected recognition model, not automatic language detection. Vision labels remain English COCO labels, and left/front/right describe image thirds, not safe directions or distance. Keep local cane obstacle detection independent.

For **synthetic contract testing only**, the earlier PHP fixture remains available. Do not run it on the same port as FastAPI:

```bash
php -S 127.0.0.1:8091 tests/Fixtures/image-provider.php
```

Configure your local environment:

```dotenv
VISION_SERVICE_URL=http://127.0.0.1:8091/detect
OCR_SERVICE_URL=http://127.0.0.1:8091/read
```

Leave both API keys blank, clear Laravel's config cache, and send a real image file with the curl examples. This fixture always returns labeled synthetic data; it does not recognize the image. Bind it only to loopback and never deploy it. Google Maps remains a real integration. The OSRM provider below supplies a separate real pedestrian-routing integration without Google credentials; both providers have mocked HTTP tests. For a synthetic dashboard row only, the optional local/testing-only `NavigationSessionSeeder` can be invoked explicitly; it is not part of normal seeding.

```bash
php artisan test --compact tests/Feature/AssistiveImageApiTest.php
php artisan test --compact tests/Feature/NavigationApiTest.php
php artisan test --compact tests/Feature/NavigationProviderTest.php
php artisan test --compact tests/Feature/NavigationAuthorizationTest.php
php artisan test --compact tests/Unit/RouteGeometryTest.php
php artisan test --compact
```

Tests use in-memory SQLite, HTTP fakes, and `Http::preventStrayRequests()`. `.env.testing` and the public test-only encryption key in `phpunit.xml` allow a clean checkout to run tests; never use that key in deployment. Build existing frontend assets with `npm ci && npm run build` before the full suite. No hardware, paid API key, or external inference process is required by tests.

## Device and safety responsibilities

Local obstacle detection and immediate haptic/audio warnings must continue without Wi-Fi, cellular service, Laravel, or AI providers. Vision supplies non-critical scene information, not reliable distance estimation. Google walking guidance is not a guarantee of sidewalks, accessibility, freedom from obstacles, or safe independent travel. Present the returned safety notice and provider warnings to the user in an accessible form.

The IoT team still supplies the camera capture, GPS accuracy reporting/time synchronization, TTS or audio playback, local sensors, connectivity/retry behavior, destination confirmation, and field validation. Laravel provides synchronous bounded inference calls; it does not queue stale frames for later warnings. Existing SOS and arrival workflows are not triggered automatically by inference or approximate navigation progress.

## Implementation map

New implementation files:

- `app/Contracts/OCRProvider.php`; `app/Enums/NavigationStatus.php`; `app/Exceptions/AssistiveServiceException.php`.
- `app/Services/{ImageProviderClient,VisionService,OCRService,HttpOCRProvider,GoogleMapsService,NavigationService,RouteGeometry}.php`.
- `app/Http/Controllers/Api/Device/{VisionController,OCRController,NavigationSearchController,NavigationController}.php`.
- `app/Http/Requests/Api/Device/{ReadImageRequest,NavigationRequest,SearchDestinationRequest,StartNavigationRequest,UpdateNavigationRequest}.php`.
- `app/Models/NavigationSession.php`; `app/Policies/NavigationSessionPolicy.php`.
- `app/Filament/Resources/NavigationSessionResource.php` and `NavigationSessionResource/Pages/ManageNavigationSessions.php`.
- Navigation migration, `database/factories/NavigationSessionFactory.php`, and optional `database/seeders/NavigationSessionSeeder.php`.
- `lang/{en,ar}/assistive.php`; `.env.testing`.
- `tests/Feature/{AssistiveImageApiTest,NavigationApiTest,NavigationAuthorizationTest}.php`, `tests/Unit/RouteGeometryTest.php`, and `tests/Fixtures/image-provider.php`.
- This document.

Modified existing files: `.env.example`, `README.md`, `phpunit.xml`, `bootstrap/app.php`, `config/{services,smart-cane}.php`, `routes/api.php`, `app/Providers/AppServiceProvider.php`, `app/Http/Middleware/EnsureDeviceToken.php`, `app/Models/{Device,BlindUser,Location}.php`, and `lang/ar/validation.php`. No dependency versions were changed.

### Configurable routing extension files

Added `app/Contracts/{RoutingProvider,DestinationProvider}.php`, `app/Services/{GoogleRoutingProvider,OsrmRoutingProvider,CoordinateDestinationProvider,NavigationProviders}.php`, the additive routing-provider migration, and `tests/Feature/NavigationProviderTest.php`. Updated `NavigationService`, `NavigationSearchController`, `NavigationSession`, `NavigationSessionResource`, `AppServiceProvider`, `config/services.php`, English/Arabic assistive translations, and navigation tests. `GoogleMapsService` is unchanged. No dependency changes or automatic changes to `.env` are required.

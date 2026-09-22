# BASIRA inference service

Real CPU inference using YOLO11n (COCO object classes) and PaddleOCR 3.3.2. Laravel already speaks these contracts; no controller changes are necessary. Immediate obstacle sensing and haptic/audio warnings must keep working locally on the cane without this service or internet access.

## Local setup (including macOS Apple Silicon)

Use native ARM64 **Python 3.12** on Apple Silicon, not Rosetta and not the system Python 3.14. The pinned PaddlePaddle 3.2.2 has a macOS ARM64 CPU wheel for Python 3.12. No CUDA, GPU, Homebrew OpenMP installation, or paid API key is required. Linux x86_64 is also supported by the CPU dependencies; the Docker recipe below targets that platform.

```bash
cd ai-service
python3.12 -m venv .venv
source .venv/bin/activate
python -m pip install -r requirements.txt -r requirements-test.txt
cp .env.example .env
uvicorn app.main:app --host 127.0.0.1 --port 8091 --workers 1 --limit-concurrency 8 --no-access-log
```

If Python 3.12 is unavailable, install it separately (for example `uv python install 3.12`, then `uv venv --python 3.12 .venv`). Keep the virtual environment isolated from Laravel. Run commands from `ai-service/`; its `.env` is separate from Laravel's root `.env`. Environment variables override the file. Python dependencies are pinned directly; transitive package resolution can still vary.

Startup downloads and loads `models/yolo11n.pt`, `PP-OCRv5_mobile_det`, `en_PP-OCRv5_mobile_rec`, and `arabic_PP-OCRv5_mobile_rec`. Paddle caches weights under the service user's home (normally `~/.paddlex/official_models`); Ultralytics may also create settings/cache files there. Allow outbound network access on the first startup, and allow several minutes and several hundred MB for dependencies/caches. Uploaded images are never sent to those download hosts. Preserve caches for offline restarts. Startup fails if required models cannot load; there is no synthetic fallback. `GET /health` returns `{"status":"ready"}` after model initialization, not an accuracy certification. First inference may still take longer than subsequent requests; warm both OCR languages and vision before device use.

The [PaddleOCR 3.3.2 language mapping](https://github.com/PaddlePaddle/PaddleOCR/blob/v3.3.2/paddleocr/_pipelines/ocr.py) includes Arabic and English PP-OCRv5 recognition models. We select those model names explicitly instead of relying on a changing default. [Ultralytics prediction documentation](https://docs.ultralytics.com/modes/predict/) describes the bounding boxes and confidence scores used here.

## Configuration

| Variable | Default | Meaning |
| --- | --- | --- |
| `VISION_SERVICE_API_KEY` | blank | Optional bearer secret for `/detect` |
| `OCR_SERVICE_API_KEY` | blank | Independent bearer secret for `/read` |
| `YOLO_MODEL` | `models/yolo11n.pt` | Trusted local weights path; no request-controlled model loading |
| `VISION_MIN_CONFIDENCE` | `0.5` | Detection threshold, 0–1 |
| `OCR_MIN_CONFIDENCE` | `0.5` | Recognition threshold, 0–1; uncertain lines are omitted |
| `MAX_IMAGE_BYTES` | `5242880` | Maximum uploaded image bytes |
| `MAX_IMAGE_DIMENSION` | `8192` | Maximum width/height |
| `MAX_IMAGE_PIXELS` | `16777216` | Additional decoded pixel limit |
| `CPU_THREADS` | `2` | Per-model CPU thread budget |

Image requests require exactly one `image` file and one `language=ar|en` form field. JPEG, PNG and WebP are decoded and checked against the declared MIME type. Animated images, corrupt files, oversized files and dimensions are rejected. EXIF orientation is applied, then pixels are converted to RGB (BGR for Paddle). The complete multipart body is limited to the image byte limit plus 64 KiB, including requests without Content-Length. Temporary multipart files close after each request; no permanent image, OCR text, or detection history is written. Inference is serialized; overlapping work gets 503 with `Retry-After: 1` instead of an unbounded queue.

Errors return a `detail` string: 401 for a missing/wrong configured key, 413 for size/dimensions, 415 for unsupported/mismatched type, 422 for invalid image/language, 400 for malformed multipart, and 503 for unavailable/busy inference. Model exception messages, images, text and credentials are not logged by application handlers. Do not enable request-body logging in a proxy or debug middleware. Keep the service on loopback or a private network; configure keys, TLS and network access controls before any remote exposure. `/health` requires no key. Use one worker to avoid duplicate model memory; apply proxy connection, request-rate and timeout limits for deployment.

## Laravel integration

Set these in Laravel's root `.env`:

```dotenv
VISION_SERVICE_URL=http://127.0.0.1:8091/detect
OCR_SERVICE_URL=http://127.0.0.1:8091/read
VISION_SERVICE_API_KEY=
OCR_SERVICE_API_KEY=
VISION_SERVICE_TIMEOUT=20
OCR_SERVICE_TIMEOUT=20
```

Put the same respective keys in `ai-service/.env` if using authentication, then restart Python and run `php artisan config:clear` from the repository root. The root `.env.example` was already deleted in this working tree; this change does not restore it. The new service includes its own example. Laravel already adds the appropriate bearer header and multipart fields. Its provider timeout is capped at 20 seconds; very large images or slow CPUs may exceed it. Prefer camera frames around 640–1280 pixels on the long side. Laravel's device-specific confidence threshold applies after Python's threshold, so it cannot recover detections already filtered out by Python.

Laravel continues to authenticate the **IoT device** using Sanctum and checks device assignment, enabled state and token abilities. Python authenticates **Laravel**, not individual devices. Keep service keys off the cane. The flow is camera capture → Laravel `/api/device/vision/detect` or `/api/device/ocr/read` → private inference service → Laravel's existing response wrapper → device. Do not call Python directly from deployed devices. Bounded backoff on failure must not stop local obstacle warnings. See [IOT_API.md](../IOT_API.md) for device requests and response handling.

Direct local provider checks (omit `-H` if the corresponding key is blank):

```bash
curl http://127.0.0.1:8091/health
curl http://127.0.0.1:8091/detect \
  -H "Authorization: Bearer $VISION_SERVICE_API_KEY" \
  -F 'image=@/absolute/path/scene.jpg;type=image/jpeg' -F 'language=en'
curl http://127.0.0.1:8091/read \
  -H "Authorization: Bearer $OCR_SERVICE_API_KEY" \
  -F 'image=@/absolute/path/arabic-sign.png;type=image/png' -F 'language=ar'
curl http://127.0.0.1:8091/read \
  -H "Authorization: Bearer $OCR_SERVICE_API_KEY" \
  -F 'image=@/absolute/path/english-sign.png;type=image/png' -F 'language=en'
```

Provider responses have exactly these shapes (illustrative values):

```json
{"objects":[{"label":"person","confidence":0.94,"position":"front"}]}
```
```json
{"text":"جامعة المنصورة","language":"ar"}
```

No detections returns `{"objects":[]}`. No confident OCR returns `{"text":"","language":"en"}` (or `ar`); Laravel deliberately converts that to its existing 422 `no_text_detected` response.

## Tests

```bash
# Fast unit/API tests: no weights, downloads or inference libraries needed.
python -m pip install -r requirements-test.txt
python -m pytest -q -m 'not real'
# From the Laravel root:
php artisan test --compact tests/Feature/AssistiveImageApiTest.php
php artisan test --compact
```

Unit/API tests mock model outputs and prove contracts, validation and error behavior only. They do not prove recognition accuracy.

Separate real inference tests load the actual weights and post actual sample images through FastAPI. The default vision sample is the public Ultralytics bus photograph, downloaded to a temporary directory only when opted in. The committed OCR fixtures are project-generated images of clean English and properly shaped Arabic text, with expected wording defined before inference. They exercise real recognition but do not measure field accuracy.

```bash
RUN_REAL_INFERENCE=1 python -m pytest -q -m real
```

Optionally supply a clear scene with a known COCO object and independently transcribed Arabic/English text samples. A custom image requires its corresponding expected label/text variable. Use expected substrings because a model may find additional visible text. Samples remain local and should not contain private information.

```bash
RUN_REAL_INFERENCE=1 \
REAL_VISION_IMAGE=/absolute/path/scene.jpg REAL_VISION_LABEL=person \
REAL_OCR_EN_IMAGE=/absolute/path/english.png REAL_OCR_EN_TEXT='Mansoura University' \
REAL_OCR_AR_IMAGE=/absolute/path/arabic.png REAL_OCR_AR_TEXT='جامعة المنصورة' \
python -m pytest -q -m real
```

Use `samples/` for local fixtures; that directory, weights, caches, environments and uploads must stay out of Git. No real sample is downloaded by the default test suite. Real tests use environment settings but ignore `.env` and disable service keys only in their in-process TestClient configuration.

## Container

```bash
docker build --platform linux/amd64 -t basira-ai ai-service
docker run --rm --platform linux/amd64 -p 127.0.0.1:8091:8091 \
  --env-file ai-service/.env \
  -v basira-yolo:/service/models -v basira-paddle:/home/inference/.paddlex \
  basira-ai
```

This is a non-root CPU container. On Apple Silicon, native Python is faster than x86_64 container emulation. Docker startup also needs first-run model download access. For Laravel in a separate container, use the inference container's private network name rather than `127.0.0.1`. The Docker build does not embed weights or application secrets.

## Accuracy and safety limits

YOLO11n detects its trained COCO classes; it misses many real obstacles, transparent objects, holes and hazards. Positions describe thirds of the camera image based on bounding-box centers; `front` means the middle third, not a physical bearing or a clear walking path. Labels stay in the model's English vocabulary even when `language=ar`. Scores are model confidence, not calibrated safety probabilities. No distance, path-clearance judgment or spoken travel instruction is generated.

PaddleOCR returns recognized segments in model order, separated by newlines, without rewriting, translating, summarizing or manually reversing Arabic characters. `language` is the requested recognizer, not automatic language identification. Multi-column order, mixed Arabic/English, handwriting, diacritics, glare, blur, tiny text and rotated text can be inaccurate. EXIF rotation is handled; document unwarping and orientation classifiers are disabled to reduce CPU work. Arabic support is real model support, not a guarantee of correct recognition on every sign. Use clean upright samples and verify important text independently.

This service does not provide certified obstacle avoidance or safe navigation. Keep immediate warnings available on the cane without internet. Review the [Ultralytics licensing terms](https://www.ultralytics.com/license) for your deployment; installing the package does not resolve distribution obligations.

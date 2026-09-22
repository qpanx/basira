from concurrent.futures import ThreadPoolExecutor
from io import BytesIO
from threading import Event

import pytest
from fastapi.testclient import TestClient
from PIL import Image

from app.config import Settings
from app.main import create_app
from app.schemas import OCRResponse


def post(client, path, image_bytes, language="en", **kwargs):
    return client.post(
        path,
        files={"image": ("capture.png", image_bytes, "image/png")},
        data={"language": language},
        **kwargs,
    )


def test_health_is_ready_without_inference(client, engine):
    assert client.get("/health").json() == {"status": "ready"}
    engine.detect.assert_not_called()


def test_detection_contract(client, engine, image_bytes):
    response = post(client, "/detect", image_bytes)
    assert response.status_code == 200
    assert response.json() == {
        "objects": [{"label": "person", "confidence": 0.94, "position": "front"}]
    }
    assert engine.detect.call_args.args[0].size == (90, 60)


@pytest.mark.parametrize(
    "language,text",
    [("ar", "جامعة المنصورة\nمرحبا"), ("en", "Mansoura University\nWelcome")],
)
def test_ocr_contract_preserves_lines(client, image_bytes, language, text):
    response = post(client, "/read", image_bytes, language)
    assert response.status_code == 200
    assert response.json() == {"text": text, "language": language}


def test_empty_ocr_is_not_invented(client, engine, image_bytes):
    engine.read.side_effect = None
    engine.read.return_value = OCRResponse(text="", language="ar")
    assert post(client, "/read", image_bytes, "ar").json() == {
        "text": "",
        "language": "ar",
    }


@pytest.mark.parametrize("path", ["/detect", "/read"])
@pytest.mark.parametrize("language", ["fr", "", "AR", "ar,en"])
def test_unsupported_languages_do_not_run_models(
    client, engine, image_bytes, path, language
):
    assert post(client, path, image_bytes, language).status_code == 422
    engine.detect.assert_not_called()
    engine.read.assert_not_called()


@pytest.mark.parametrize(
    "path,key", [("/detect", "vision-secret"), ("/read", "ocr-secret")]
)
def test_separate_bearer_keys(engine, image_bytes, path, key):
    settings = Settings(
        _env_file=None,
        vision_service_api_key="vision-secret",
        ocr_service_api_key="ocr-secret",
    )
    with TestClient(create_app(settings, engine)) as client:
        assert post(client, path, image_bytes).status_code == 401
        wrong = "ocr-secret" if path == "/detect" else "vision-secret"
        assert (
            post(
                client, path, image_bytes, headers={"Authorization": f"Bearer {wrong}"}
            ).status_code
            == 401
        )
        assert (
            post(
                client, path, image_bytes, headers={"Authorization": f"Bearer {key}"}
            ).status_code
            == 200
        )


@pytest.mark.parametrize("path", ["/detect", "/read"])
@pytest.mark.parametrize(
    "payload", [b"", b"not an image", b"\x89PNG\r\n\x1a\ntruncated"]
)
def test_invalid_content(client, engine, path, payload):
    assert post(client, path, payload).status_code == 422
    engine.detect.assert_not_called()
    engine.read.assert_not_called()


def test_type_spoofing_and_unsupported_types(client, image_bytes):
    for mime in ["image/jpeg", "text/plain", "image/svg+xml"]:
        response = client.post(
            "/detect",
            files={"image": ("a.jpg", image_bytes, mime)},
            data={"language": "en"},
        )
        assert response.status_code == 415


@pytest.mark.parametrize(
    "format,mime", [("JPEG", "image/jpeg"), ("WEBP", "image/webp")]
)
def test_supported_formats_are_decoded(client, format, mime):
    output = BytesIO()
    Image.new("RGB", (90, 60), "white").save(output, format=format)
    response = client.post(
        "/detect",
        files={"image": ("capture", output.getvalue(), mime)},
        data={"language": "ar"},
    )
    assert response.status_code == 200


def test_exif_orientation_is_applied_before_inference(client, engine):
    output = BytesIO()
    image = Image.new("RGB", (90, 60))
    exif = image.getexif()
    exif[274] = 6
    image.save(output, format="JPEG", exif=exif)
    response = client.post(
        "/detect",
        files={"image": ("capture.jpg", output.getvalue(), "image/jpeg")},
        data={"language": "en"},
    )
    assert response.status_code == 200
    assert engine.detect.call_args.args[0].size == (60, 90)


def test_keys_can_be_enabled_independently(engine, image_bytes):
    with TestClient(
        create_app(Settings(_env_file=None, ocr_service_api_key="ocr-only"), engine)
    ) as client:
        assert post(client, "/detect", image_bytes).status_code == 200
        assert post(client, "/read", image_bytes).status_code == 401


@pytest.mark.parametrize(
    "setting,value",
    [("max_image_dimension", 50), ("max_image_pixels", 100), ("max_image_bytes", 10)],
)
def test_image_limits(engine, image_bytes, setting, value):
    with TestClient(
        create_app(Settings(_env_file=None, **{setting: value}), engine)
    ) as client:
        assert post(client, "/detect", image_bytes).status_code == 413
        engine.detect.assert_not_called()


def test_chunked_body_limit_is_enforced(client):
    response = client.post(
        "/detect",
        content=iter([b"x" * 65536] * 82),
        headers={"Content-Type": "multipart/form-data; boundary=test"},
    )
    assert response.status_code == 413


def test_missing_and_extra_fields(client, image_bytes):
    assert client.post("/read").status_code == 422
    assert (
        client.post(
            "/read", files={"image": ("a.png", image_bytes, "image/png")}
        ).status_code
        == 422
    )
    assert (
        client.post(
            "/read",
            files={"image": ("a.png", image_bytes, "image/png")},
            data={"language": "en", "extra": "x"},
        ).status_code
        == 400
    )


def test_animated_upload_is_rejected(client):
    output = BytesIO()
    Image.new("RGB", (10, 10), "white").save(
        output,
        format="PNG",
        save_all=True,
        append_images=[Image.new("RGB", (10, 10), "black")],
    )
    assert post(client, "/detect", output.getvalue()).status_code == 415


def test_inference_errors_are_sanitized(client, engine, image_bytes, caplog):
    engine.read.side_effect = RuntimeError("secret OCR text and credentials")
    response = post(client, "/read", image_bytes)
    assert response.status_code == 503
    assert response.json() == {"detail": "Inference unavailable"}
    assert "secret OCR text" not in caplog.text


def test_concurrent_inference_is_rejected_instead_of_queued(
    client, engine, image_bytes
):
    entered, release = Event(), Event()
    original = engine.detect.return_value

    def blocking(image):
        entered.set()
        assert release.wait(5)
        return original

    engine.detect.side_effect = blocking
    with ThreadPoolExecutor() as executor:
        pending = executor.submit(post, client, "/detect", image_bytes)
        try:
            assert entered.wait(5)
            response = post(client, "/read", image_bytes)
            assert response.status_code == 503
            assert response.headers["Retry-After"] == "1"
        finally:
            release.set()
        assert pending.result().status_code == 200

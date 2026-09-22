"""No model mocks. Default OCR fixtures are rendered text, not field photographs."""

import os
from pathlib import Path
from urllib.request import urlopen

import pytest
from fastapi.testclient import TestClient

from app.config import Settings
from app.main import create_app

pytestmark = [
    pytest.mark.real,
    pytest.mark.skipif(
        os.getenv("RUN_REAL_INFERENCE") != "1", reason="Opt-in model inference"
    ),
]


@pytest.fixture(scope="module")
def real_client():
    with TestClient(
        create_app(
            Settings(_env_file=None, vision_service_api_key="", ocr_service_api_key="")
        )
    ) as client:
        yield client


@pytest.mark.parametrize(
    "endpoint,sample,language,expected",
    [
        ("/detect", "REAL_VISION_IMAGE", "en", "REAL_VISION_LABEL"),
        ("/read", "REAL_OCR_EN_IMAGE", "en", "REAL_OCR_EN_TEXT"),
        ("/read", "REAL_OCR_AR_IMAGE", "ar", "REAL_OCR_AR_TEXT"),
    ],
)
def test_real_recognition(real_client, tmp_path, endpoint, sample, language, expected):
    if os.getenv(sample):
        assert os.getenv(expected), (
            f"Set {expected} to independently verified expected content"
        )
        path = Path(os.environ[sample])
        expected_content = os.environ[expected]
    elif endpoint == "/detect":
        path = tmp_path / "bus.jpg"
        with urlopen(
            "https://raw.githubusercontent.com/ultralytics/assets/main/im/bus.jpg",
            timeout=60,
        ) as response:
            path.write_bytes(response.read())
        expected_content = "bus"
    else:
        path = (
            Path(__file__).parent
            / "fixtures"
            / ("arabic.png" if language == "ar" else "english.png")
        )
        expected_content = (
            "جامعة المنصورة" if language == "ar" else "Mansoura University"
        )
    mime = {
        ".jpg": "image/jpeg",
        ".jpeg": "image/jpeg",
        ".png": "image/png",
        ".webp": "image/webp",
    }[path.suffix.lower()]
    response = real_client.post(
        endpoint,
        files={"image": (path.name, path.read_bytes(), mime)},
        data={"language": language},
    )
    assert response.status_code == 200
    if endpoint == "/detect":
        assert expected_content in [
            item["label"] for item in response.json()["objects"]
        ]
    else:
        assert response.json()["language"] == language
        assert expected_content in response.json()["text"]

from io import BytesIO
from unittest.mock import Mock

import pytest
from fastapi.testclient import TestClient
from PIL import Image

from app.config import Settings
from app.main import create_app
from app.schemas import DetectionResponse, OCRResponse


@pytest.fixture
def image_bytes():
    output = BytesIO()
    Image.new("RGB", (90, 60), "white").save(output, format="PNG")
    return output.getvalue()


@pytest.fixture
def engine():
    model = Mock()
    model.detect.return_value = DetectionResponse(
        objects=[
            {"label": "person", "confidence": 0.94, "position": "front"},
        ]
    )
    model.read.side_effect = lambda image, language: OCRResponse(
        text="جامعة المنصورة\nمرحبا"
        if language == "ar"
        else "Mansoura University\nWelcome",
        language=language,
    )
    return model


@pytest.fixture
def client(engine):
    with TestClient(create_app(Settings(_env_file=None), engine)) as client:
        yield client

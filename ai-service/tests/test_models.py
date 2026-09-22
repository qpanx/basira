from types import SimpleNamespace
from unittest.mock import Mock

import numpy as np
from PIL import Image

from app.config import Settings
from app.services.ocr import OCR
from app.services.vision import Vision


def test_yolo_positions_filtering_and_cpu_inference():
    model = Mock()
    model.predict.return_value = [
        SimpleNamespace(
            names={0: "person"},
            boxes=SimpleNamespace(
                xyxy=np.array(
                    [[0, 0, 10, 10], [35, 0, 55, 10], [80, 0, 90, 10], [0, 0, 10, 10]]
                ),
                conf=np.array([0.9, 0.8, 0.7, 0.1]),
                cls=np.array([0, 0, 0, 0]),
            ),
        )
    ]
    result = Vision(Settings(_env_file=None), model).detect(Image.new("RGB", (90, 60)))
    assert [item.position for item in result.objects] == ["left", "front", "right"]
    assert [item.confidence for item in result.objects] == [0.9, 0.8, 0.7]
    assert model.predict.call_args.kwargs["device"] == "cpu"
    assert model.predict.call_args.kwargs["save"] is False


def test_no_yolo_detections_remain_empty():
    model = Mock()
    model.predict.return_value = [SimpleNamespace(boxes=None)]
    assert (
        Vision(Settings(_env_file=None), model)
        .detect(Image.new("RGB", (90, 60)))
        .objects
        == []
    )


def test_ocr_preserves_arabic_wording_and_filters_uncertain_lines():
    arabic, english = Mock(), Mock()
    arabic.predict.return_value = [
        {
            "rec_texts": ["جامعة المنصورة", "غير واضح", "مرحبا"],
            "rec_scores": [0.95, 0.1, 0.8],
        }
    ]
    result = OCR(Settings(_env_file=None), {"ar": arabic, "en": english}).read(
        Image.new("RGB", (2, 2), "red"), "ar"
    )
    assert result.model_dump() == {"text": "جامعة المنصورة\nمرحبا", "language": "ar"}
    english.predict.assert_not_called()
    assert arabic.predict.call_args.args[0][0, 0].tolist() == [0, 0, 255]


def test_ocr_without_text_is_empty():
    model = Mock()
    model.predict.return_value = [{"rec_texts": [], "rec_scores": []}]
    assert (
        OCR(Settings(_env_file=None), {"en": model})
        .read(Image.new("RGB", (10, 10)), "en")
        .text
        == ""
    )

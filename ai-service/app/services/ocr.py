from typing import Any

import numpy as np
from PIL import Image

from app.config import Settings
from app.schemas import Language, OCRResponse


class OCR:
    def __init__(self, settings: Settings, models: dict[str, Any] | None = None):
        self.settings = settings
        if models is None:
            from paddleocr import PaddleOCR

            models = {
                language: PaddleOCR(
                    text_detection_model_name="PP-OCRv5_mobile_det",
                    text_recognition_model_name=name,
                    use_doc_orientation_classify=False,
                    use_doc_unwarping=False,
                    use_textline_orientation=False,
                    text_rec_score_thresh=settings.ocr_min_confidence,
                    device="cpu",
                    cpu_threads=settings.cpu_threads,
                    enable_mkldnn=False,
                )
                for language, name in {
                    "en": "en_PP-OCRv5_mobile_rec",
                    "ar": "arabic_PP-OCRv5_mobile_rec",
                }.items()
            }
        self.models = models

    def read(self, image: Image.Image, language: Language) -> OCRResponse:
        # PaddleOCR's ndarray input uses BGR, unlike Pillow's RGB input.
        pixels = np.asarray(image)[:, :, ::-1].copy()
        lines = []
        for result in self.models[language].predict(pixels):
            for text, score in zip(
                result["rec_texts"], result["rec_scores"], strict=True
            ):
                if score >= self.settings.ocr_min_confidence:
                    lines.append(text)
        return OCRResponse(text="\n".join(lines), language=language)

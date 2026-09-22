from pathlib import Path
from typing import Any

from PIL import Image

from app.config import Settings
from app.schemas import DetectedObject, DetectionResponse


class Vision:
    def __init__(self, settings: Settings, model: Any = None):
        self.settings = settings
        if model is None:
            import torch
            from ultralytics import YOLO

            torch.set_num_threads(settings.cpu_threads)
            Path(settings.yolo_model).parent.mkdir(parents=True, exist_ok=True)
            model = YOLO(settings.yolo_model)
        self.model = model

    def detect(self, image: Image.Image) -> DetectionResponse:
        results = self.model.predict(
            source=image,
            device="cpu",
            conf=self.settings.vision_min_confidence,
            imgsz=640,
            max_det=100,
            verbose=False,
            save=False,
        )
        objects = []
        for result in results:
            if result.boxes is None:
                continue
            for box, confidence, category in zip(
                result.boxes.xyxy.tolist(),
                result.boxes.conf.tolist(),
                result.boxes.cls.tolist(),
                strict=True,
            ):
                if confidence < self.settings.vision_min_confidence:
                    continue
                center = (box[0] + box[2]) / (2 * image.width)
                objects.append(
                    DetectedObject(
                        label=result.names[int(category)],
                        confidence=confidence,
                        position="left"
                        if center < 1 / 3
                        else "right"
                        if center > 2 / 3
                        else "front",
                    )
                )
        return DetectionResponse(objects=objects)

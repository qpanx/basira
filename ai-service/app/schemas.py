from typing import Literal

from pydantic import BaseModel, Field

Language = Literal["ar", "en"]


class DetectedObject(BaseModel):
    label: str = Field(min_length=1, max_length=120)
    confidence: float = Field(ge=0, le=1, allow_inf_nan=False)
    position: Literal["left", "front", "right"]


class DetectionResponse(BaseModel):
    objects: list[DetectedObject] = Field(max_length=100)


class OCRResponse(BaseModel):
    text: str = Field(max_length=50000)
    language: Language

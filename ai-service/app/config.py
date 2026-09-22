from pydantic import Field, SecretStr
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", extra="ignore")

    vision_service_api_key: SecretStr = SecretStr("")
    ocr_service_api_key: SecretStr = SecretStr("")
    yolo_model: str = "models/yolo11n.pt"
    vision_min_confidence: float = Field(default=0.5, ge=0, le=1)
    ocr_min_confidence: float = Field(default=0.5, ge=0, le=1)
    max_image_bytes: int = Field(default=5 * 1024 * 1024, gt=0)
    max_image_dimension: int = Field(default=8192, gt=0, le=8192)
    max_image_pixels: int = Field(default=16 * 1024 * 1024, gt=0)
    cpu_threads: int = Field(default=2, ge=1, le=32)

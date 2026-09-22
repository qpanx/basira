import warnings
from io import BytesIO

from fastapi import HTTPException, UploadFile
from PIL import Image, ImageOps, UnidentifiedImageError

from app.config import Settings


async def decode_image(upload: UploadFile, settings: Settings) -> Image.Image:
    formats = {"image/jpeg": "JPEG", "image/png": "PNG", "image/webp": "WEBP"}
    if upload.content_type not in formats:
        raise HTTPException(415, "Unsupported image type")
    data = await upload.read(settings.max_image_bytes + 1)
    if len(data) > settings.max_image_bytes:
        raise HTTPException(413, "Image too large")
    try:
        with warnings.catch_warnings():
            warnings.simplefilter("error", Image.DecompressionBombWarning)
            with Image.open(BytesIO(data)) as image:
                if image.format != formats[upload.content_type]:
                    raise HTTPException(415, "Image content does not match type")
                if (
                    max(image.size) > settings.max_image_dimension
                    or image.width * image.height > settings.max_image_pixels
                ):
                    raise HTTPException(413, "Image dimensions too large")
                if getattr(image, "n_frames", 1) != 1:
                    raise HTTPException(415, "Animated images are not supported")
                image.verify()
            with Image.open(BytesIO(data)) as image:
                return ImageOps.exif_transpose(image).convert("RGB")
    except (
        UnidentifiedImageError,
        OSError,
        SyntaxError,
        ValueError,
        Image.DecompressionBombError,
        Image.DecompressionBombWarning,
    ):
        raise HTTPException(422, "Invalid image") from None

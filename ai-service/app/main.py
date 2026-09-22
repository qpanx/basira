import hmac
import logging
from contextlib import asynccontextmanager
from threading import Lock
from typing import Protocol

from fastapi import FastAPI, HTTPException, Request
from fastapi.exceptions import RequestValidationError
from PIL import Image
from starlette.concurrency import run_in_threadpool
from starlette.datastructures import Headers, UploadFile
from starlette.responses import JSONResponse

from app.config import Settings
from app.images import decode_image
from app.schemas import DetectionResponse, Language, OCRResponse
from app.services.ocr import OCR
from app.services.vision import Vision

logger = logging.getLogger("basira.ai")


class Inference(Protocol):
    def detect(self, image: Image.Image) -> DetectionResponse: ...
    def read(self, image: Image.Image, language: Language) -> OCRResponse: ...


class Models:
    def __init__(self, settings: Settings):
        self.vision = Vision(settings)
        self.ocr = OCR(settings)

    def detect(self, image: Image.Image) -> DetectionResponse:
        return self.vision.detect(image)

    def read(self, image: Image.Image, language: Language) -> OCRResponse:
        return self.ocr.read(image, language)


class RequestGuard:
    """Authenticate and cap bytes before multipart parsing or temporary spooling."""

    def __init__(self, app, settings: Settings):
        self.app = app
        self.settings = settings

    async def __call__(self, scope, receive, send):
        if scope["type"] != "http" or scope["path"] not in ("/detect", "/read"):
            return await self.app(scope, receive, send)
        headers = Headers(scope=scope)
        key = (
            self.settings.vision_service_api_key
            if scope["path"] == "/detect"
            else self.settings.ocr_service_api_key
        ).get_secret_value()
        if key and not hmac.compare_digest(
            headers.get("authorization", "").encode(), f"Bearer {key}".encode()
        ):
            return await JSONResponse(
                {"detail": "Unauthorized"},
                status_code=401,
                headers={"WWW-Authenticate": "Bearer"},
            )(scope, receive, send)
        body = bytearray()
        while True:
            message = await receive()
            if message["type"] == "http.disconnect":
                return
            body.extend(message.get("body", b""))
            if len(body) > self.settings.max_image_bytes + 65536:
                return await JSONResponse(
                    {"detail": "Request too large"}, status_code=413
                )(scope, receive, send)
            if not message.get("more_body", False):
                break

        async def replay():
            return {"type": "http.request", "body": bytes(body), "more_body": False}

        await self.app(scope, replay, send)


def create_app(
    settings: Settings | None = None, inference: Inference | None = None
) -> FastAPI:
    settings = settings or Settings()
    lock = Lock()

    @asynccontextmanager
    async def lifespan(app: FastAPI):
        app.state.inference = (
            inference
            if inference is not None
            else await run_in_threadpool(Models, settings)
        )
        app.state.ready = True
        yield
        app.state.ready = False

    app = FastAPI(title="BASIRA inference", lifespan=lifespan)
    app.state.ready = False
    app.add_middleware(RequestGuard, settings=settings)

    @app.exception_handler(RequestValidationError)
    async def invalid_request(request: Request, error: RequestValidationError):
        return JSONResponse({"detail": "Invalid request"}, status_code=422)

    @app.get("/health")
    async def health():
        return JSONResponse(
            {"status": "ready" if app.state.ready else "starting"},
            status_code=200 if app.state.ready else 503,
        )

    async def infer(request: Request, operation: str):
        if not app.state.ready:
            raise HTTPException(503, "Models not ready")
        if not lock.acquire(blocking=False):
            raise HTTPException(503, "Inference busy", headers={"Retry-After": "1"})
        try:
            async with request.form(max_files=1, max_fields=1) as form:
                if (
                    set(form.keys()) != {"image", "language"}
                    or len(form.multi_items()) != 2
                ):
                    raise HTTPException(422, "Provide image and language")
                language = form["language"]
                upload = form["image"]
                if language not in ("ar", "en") or not isinstance(upload, UploadFile):
                    raise HTTPException(422, "Invalid image or language")
                image = await decode_image(upload, settings)
                try:
                    if operation == "detect":
                        return await run_in_threadpool(
                            app.state.inference.detect, image
                        )
                    return await run_in_threadpool(
                        app.state.inference.read, image, language
                    )
                except Exception:
                    logger.warning("%s inference failed", operation)
                    raise HTTPException(503, "Inference unavailable") from None
                finally:
                    image.close()
        finally:
            lock.release()

    @app.post("/detect", response_model=DetectionResponse)
    async def detect(request: Request):
        return await infer(request, "detect")

    @app.post("/read", response_model=OCRResponse)
    async def read(request: Request):
        return await infer(request, "read")

    return app


app = create_app()

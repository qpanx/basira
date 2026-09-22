# Real OCR smoke fixtures

`english.png` contains **Mansoura University**. `arabic.png` contains **جامعة المنصورة**. These are project-generated clean black text on white images (1000 × 180), rendered using native macOS text shaping. They contain no user uploads or model output. Expected strings were specified before inference.

The opt-in smoke tests feed their pixels to real PaddleOCR. Success verifies a basic inference path, not performance on photographs, handwriting, mixed languages, glare or street signs.

The real vision test downloads [Ultralytics' public bus photograph](https://github.com/ultralytics/assets/blob/main/im/bus.jpg) into pytest's temporary directory only when opted in. Its expected COCO label is `bus`. No photograph or model weight is committed here. Supply `REAL_VISION_IMAGE` and `REAL_VISION_LABEL` to use an offline local sample instead.

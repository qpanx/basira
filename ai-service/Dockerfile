FROM python:3.12-slim
ENV PYTHONDONTWRITEBYTECODE=1 PYTHONUNBUFFERED=1 HOME=/home/inference
RUN apt-get update && apt-get install -y --no-install-recommends libgl1 libglib2.0-0 libgomp1 \
    && rm -rf /var/lib/apt/lists/* \
    && useradd --create-home inference
WORKDIR /service
COPY requirements.txt requirements-api.txt ./
RUN pip install --no-cache-dir -r requirements.txt
COPY --chown=inference:inference app ./app
RUN mkdir -p models && chown inference:inference models
USER inference
EXPOSE 8091
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8091", "--workers", "1", "--limit-concurrency", "8", "--no-access-log"]

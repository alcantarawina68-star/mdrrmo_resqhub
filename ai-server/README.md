# CEIRIS AI Image Detection Server

Standalone Node.js service that exposes a local AI detection endpoint for the CEIRIS Laravel app.

Laravel (`AiImageDetectionService`) POSTs the uploaded evidence image to this server, which
forwards it to the Hugging Face Inference API and returns a normalized prediction.

## API

- `GET /health` → `{ "status": "ok" }`
- `POST /predict` → multipart form, field `file` (the image)

Response (200):

```json
{
  "success": true,
  "label": "AI Generated",
  "confidence": 0.9863,
  "predictions": [
    { "label": "AI Generated", "score": 0.9863 },
    { "label": "Real Image", "score": 0.0137 }
  ]
}
```

Failure responses return non-200 with `{ "success": false, "error": "..." }`.

## Setup

Requires Node.js 18+ (global `fetch`/`FormData`).

```bash
cd ai-server
npm install
```

Copy the environment template and add your Hugging Face token:

```bash
copy .env.example .env
# edit .env → set HF_TOKEN=hf_...
```

## Run

```bash
npm start
```

The server listens on `http://127.0.0.1:8001` by default (`PORT` in `.env`). Keep it
running in parallel with `php artisan serve` (Laravel on port 8000).

## Verification

```bash
curl http://127.0.0.1:8001/health
curl -F "file=@some-photo.png" http://127.0.0.1:8001/predict
```

## Laravel configuration

In the Laravel `.env`:

```
AI_DETECTION_URL=http://127.0.0.1:8001
AI_DETECTION_TOKEN=          # optional; leave blank unless REQUIRED_TOKEN is set here
AI_DETECTION_TIMEOUT=60
```

## Deployment note

This is a separate, independently deployable service (Node on cPanel/container/VPS).
The Laravel app only needs `AI_DETECTION_URL` to point at its public address. No
Laravel queue worker is required for AI detection.
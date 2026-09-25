import { fileURLToPath } from 'node:url';
import dotenv from 'dotenv';
import express from 'express';
import multer from 'multer';

dotenv.config({ path: fileURLToPath(new URL('./.env', import.meta.url)) });

const PORT = Number(process.env.PORT || 8001);
const HF_URL = process.env.HF_API_URL || 'https://router.huggingface.co/hf-inference/models/dima806/ai_vs_human_generated_image_detection';
const HF_TOKEN = process.env.HF_TOKEN || '';
const REQUIRED_TOKEN = process.env.REQUIRED_TOKEN || '';
const HF_TIMEOUT_MS = Number(process.env.HF_TIMEOUT_MS || 60000);

const app = express();

const upload = multer({
  storage: multer.memoryStorage(),
  limits: { fileSize: 10 * 1024 * 1024 },
});

app.get('/health', (_req, res) => {
  res.json({ status: 'ok' });
});

app.post('/predict', upload.single('file'), async (req, res) => {
  if (!req.file) {
    return res.status(422).json({ success: false, error: 'Missing multipart field "file".' });
  }

  const bearer = req.headers.authorization || '';
  const providedToken = bearer.startsWith('Bearer ') ? bearer.slice(7) : '';
  if (REQUIRED_TOKEN && providedToken !== REQUIRED_TOKEN) {
    return res.status(401).json({ success: false, error: 'Unauthorized.' });
  }

  try {
    const headers = {
      Accept: 'application/json',
      'Content-Type': req.file.mimetype || 'application/octet-stream',
    };
    if (HF_TOKEN) {
      headers.Authorization = `Bearer ${HF_TOKEN}`;
    }

    const response = await fetch(HF_URL, {
      method: 'POST',
      headers,
      body: req.file.buffer,
      signal: AbortSignal.timeout(HF_TIMEOUT_MS),
    });

    const text = await response.text();

    let payload;
    try {
      payload = JSON.parse(text);
    } catch {
      return res.status(502).json({ success: false, error: `Hugging Face returned non-JSON (HTTP ${response.status}).` });
    }

    if (!response.ok) {
      return res.status(502).json({ success: false, error: `Hugging Face error (HTTP ${response.status}): ${String(payload?.error || text).slice(0, 500)}` });
    }

    const predictions = Array.isArray(payload)
      ? payload
      : payload && Array.isArray(payload.predictions) ? payload.predictions : null;

    if (!predictions || predictions.length === 0) {
      return res.status(502).json({ success: false, error: 'Hugging Face returned no predictions.' });
    }

    const top = predictions[0];
    const confidence = Math.round(Number(top.score ?? 0) * 10000) / 10000;

    return res.json({
      success: true,
      label: String(top.label ?? 'unknown'),
      confidence,
      predictions,
    });
  } catch (error) {
    const name = String(error?.name || '');
    const code = String(error?.code || '');
    const message = String(error?.message || 'unknown');

    const isTimeout = name === 'TimeoutError' || code === 'ETIMEDOUT' || /(timed out|timeout)/i.test(message);

    return res.status(502).json({
      success: false,
      error: isTimeout ? 'AI detection timed out.' : `Upstream inference failed: ${message.slice(0, 500)}`,
    });
  }
});

app.listen(PORT, () => {
  console.log(`CEIRIS AI detection server listening on http://127.0.0.1:${PORT}`);
});
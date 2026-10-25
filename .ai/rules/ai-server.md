---
paths:
  - 'ai-server/**'
---

# Ai Server

## ai-server/ is unused — detection is in-process
Laravel no longer proxies through the Node service. `App\Support\AiImageDetector::predict()` posts the raw image bytes straight to Hugging Face with `Http`, configured by `services.huggingface.*` (`HF_TOKEN`, `HF_API_URL`, `HF_TIMEOUT_SECONDS`), and returns `['success' => true, 'label', 'confidence', 'predictions']` or `['success' => false, 'error']`. `composer run dev` no longer starts `node ai-server/index.js` and `composer run setup` no longer installs its npm deps. The `ai-server/` folder is kept on disk only as leftover and is safe to delete; do not wire it back up.
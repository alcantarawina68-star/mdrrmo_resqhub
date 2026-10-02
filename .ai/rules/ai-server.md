---
paths:
  - 'ai-server/**'
---

# Ai Server

## ai-server runs via composer run dev from repo root
The AI detection microservice is started automatically by the root `composer run dev` script (added `node ai-server/index.js` to the concurrently command). ai-server/index.js resolves its `.env` via `import.meta.url`, so it is cwd-independent: always launch it as `node ai-server/index.js` from the repo root, never `cd ai-server && node index.js`. Its deps are installed by the composer `setup` script. If `AI detection failed` / `AI service unavailable` appears on evidence records, check that the ai-server process is actually running.

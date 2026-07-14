# Node Agent Changelog

## 0.2.0 - 2026-04-28
- Added `node_agent_tokens` persistence table.
- Added DB-first authentication using SHA-256 token hashes.
- Added `last_used_at` updates for token usage traceability.
- Added bootstrap flow to import `NODE_AGENT_MASTER_TOKEN` from `.env` into DB hash.
- Kept `.env` auth as fallback for backward compatibility.

## 0.1.0 - 2026-04-28
- Initial private release of node-agent API.
- Added endpoints:
  - `GET /api/v1/node/health`
  - `POST /api/v1/node/tenants`

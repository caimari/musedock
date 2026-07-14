# Node Orchestrator Changelog

## 0.2.0 - 2026-04-28
- Added superadmin node management screen at `/musedock/nodes`.
- Added SweetAlert2 modal flows for create/edit/delete and health checks.
- Added tenant create override with node selector (automatic or manual target node).
- Added encrypted API key support (`nodes.api_key_encrypted`) with transparent migration from plaintext.
- Added local master node seed during install when no local node exists.

## 0.1.0 - 2026-04-28
- Initial private release of Phase A multi-node CMS orchestration.
- Added migrations: `nodes` table and `tenants.node_id`.
- Added local/remote tenant provisioning core flow through node-agent API.

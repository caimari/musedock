# Node Orchestrator Private Roadmap

Status date: 2026-04-28
Scope: Private plugin only (not for public repository)

## Phase A (Current)
- [x] Core multi-node orchestration for CMS tenants.
- [x] Node registry and assignment (`nodes`, `tenants.node_id`).
- [x] Manual node selection from tenant create flow.
- [x] Remote provisioning via `node-agent` API.
- [x] Token hardening: encrypted tokens in master DB, fallback for legacy `.env`.

## Phase A.1 (Next)
- [ ] Integrate `musedock-cloud` checkout/provisioning to call orchestrator service directly.
- [ ] Add retry queue for failed remote provisioning jobs.
- [ ] Add operation log table (`node_provision_logs`) and UI timeline.
- [ ] Add explicit rollback flow if remote creation succeeds but master registry fails.

## Phase A.2 (Hardening)
- [ ] One token per node with rotation window (active + next).
- [ ] IP allowlist validation and optional source signature checks.
- [ ] Node maintenance mode policies (deny new tenant creation to node).
- [ ] Capacity policy per plan (starter/pro/business -> node pools).

## Phase B (Roadmap only, not active now)
- [ ] Bridge to `musedock-panel` for standard hosting product.
- [ ] Catalog split: `cms_managed` vs `hosting_standard`.
- [ ] Unified order pipeline with provider-specific provisioning backend.

## Operational Notes
- Keep this plugin private and outside public repo exposure.
- Keep sensitive integration docs and deployment checklists here, not in public codebase.

# Node Agent Private Roadmap

Status date: 2026-04-28
Scope: private/internal plugin

## Current
- [x] Remote tenant provisioning API.
- [x] DB token auth (`node_agent_tokens`) with `.env` fallback.

## Next
- [ ] Add token CRUD endpoint for controlled rotation from master.
- [ ] Add request idempotency key support for tenant create calls.
- [ ] Add strict source IP allowlist and optional HMAC request signature.
- [ ] Add detailed provisioning logs and correlation IDs.

## Security Baseline
- Hash tokens only (no plaintext storage in DB).
- Keep fallback `.env` only during migration window.
- Enforce HTTPS and firewall policy between master and nodes.

# Changelog

All notable changes to MuseSpark MCP Bridge are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
- Snapshot-based one-click rollback for approved tasks (issue #1)
- Replaced third-party mascot asset with an original in-house mascot (IP-clean)

### Planned
- Chat gateway: Telegram control bot with inline approve/reject buttons
- WhatsApp Business Cloud API channel for customer-service automation (L2 autonomy)
- Per-domain autonomy levels (L0–L3) with guardrail engine
- PHPUnit + WordPress integration test harness
- Rate limiting on OAuth, MCP, and admin endpoints
- Removal of the development-only localhost client auto-registration
- wordpress.org submission package

## [0.2.0] - 2026-10-08

### Added
- Full MCP specification (2025-06-18) compliance: new Streamable HTTP
  endpoint `/wp-json/musespark-mcp/v1/mcp` implementing `initialize`,
  `notifications/initialized`, `ping`, `tools/list`, `tools/call`,
  `resources/list`, `resources/read`, `resources/templates/list`,
  `prompts/list`, and `prompts/get`
- MCP-standard tool results: `content[]` blocks, `structuredContent`,
  and `isError` semantics (tool errors are results, not JSON-RPC errors)
- Tool annotations: `readOnlyHint`, `destructiveHint`, `openWorldHint`
- Resources: `musespark://report/daily`, `musespark://woo/low-stock`,
  `musespark://chat/policy`
- Prompts: `daily_briefing`, `product_description`, `customer_service_reply`
- Protocol version negotiation (2025-06-18 / 2025-03-26 / 2024-11-05)
- Embedded OAuth 2.1 authorization server:
  - RFC 9728 protected-resource metadata and `WWW-Authenticate` challenge on 401
  - RFC 8414 authorization-server metadata at `/.well-known/` paths
  - RFC 7591 dynamic client registration
  - Authorization-code grant with mandatory PKCE (S256) and a consent screen
  - Refresh-token rotation and a revocation endpoint
  - Dual authentication: OAuth 2.1 opaque tokens plus legacy scoped JWT
- `wc_create_product` tool (write, confirmation-gated)
- Admin UI: task filter tabs (Pending / Completed / Failed / All), error
  column, and informative approve/reject error messages

### Changed
- JSON Schema normalization: empty `properties` now serialize as `{}`
  instead of `[]`, fixing strict MCP client validation
- Approval flow restores requester scopes from the task record; the original
  scopes remain stored in `requested_scopes` as an audit trail
- Scope check is skipped for approved executions (already validated when the
  task was enqueued); capability checks still apply to the approver

### Fixed
- Fatal errors from missing PSR-4 imports (`AdminApi`, `McpServer`)
- Invalid `//` comments inside `CREATE TABLE` SQL that broke fresh installs
- `TaskRepository::create()` silently returning `0` on INSERT failure
  (now throws a `RuntimeException` with the database error)
- Dashboard approval failing with "Token does not have required scope"
- Admin UI swallowing API error messages on approve/reject failures

### Verified
- Official MCP Inspector v2.10.1: complete OAuth 2.1 flow over HTTPS
  (discovery → dynamic registration → consent → token exchange → connected)
- Meta Muse Code (Muse Spark 1.3): live sessions creating WooCommerce
  products and content drafts through the approval queue

## [0.1.0] - 2026-10-06

### Added
- Initial private prototype
- JSON-RPC MCP endpoint (`/rpc`) with `tools/list` and `tools/call`
- JWT bearer authentication with scopes (firebase/php-jwt)
- Seven business tools: `wp_list_posts`, `wp_create_post`,
  `seo_set_post_meta`, `wc_list_products`, `wc_update_stock`,
  `newsletter_add_subscriber`, `report_daily_summary`
- Autonomy policy modes: `read_only`, `draft_only`, `require_confirmation`
- Task approval queue with admin REST API and Vue 3 approval UI
- Audit logging (requests, approvals, failures)
- Admin SPA (Vue 3 + TypeScript + Pinia + Tailwind CSS, Vite build):
  dashboard, token management, task queue, logs, settings
- WP-CLI command: `wp musespark generate-token`
- Database schema: logs, tasks, revoked JWTs, token metadata

[Unreleased]: https://github.com/joniilmanahmi00-collab/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/joniilmanahmi00-collab/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/joniilmanahmi00-collab/releases/tag/v0.1.0

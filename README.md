# MuseSpark MCP Bridge

> Turn your WordPress + WooCommerce store into a Model Context Protocol (MCP)
> server — so AI agents like Meta Muse Spark can safely run your business.

![Protocol](https://img.shields.io/badge/MCP-2025--06--18-blue)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4)
![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green)
![Status](https://img.shields.io/badge/Status-Prototype%20%7C%20Hardening-orange)

## What is this?

MuseSpark MCP Bridge exposes your WordPress business (posts, Yoast SEO,
WooCommerce products & stock, newsletter, daily reports) as standardized MCP
tools, with a built-in **OAuth 2.1 authorization server** and a
**human-in-the-loop approval queue** for every sensitive write action.

## Features

- ✅ MCP spec **2025-06-18**: `initialize`, `ping`, `tools/*`, `resources/*`,
  `prompts/*` over Streamable HTTP
- ✅ **OAuth 2.1 AS embedded**: RFC 9728 resource metadata, RFC 8414 discovery,
  RFC 7591 dynamic client registration, PKCE S256, consent screen,
  refresh-token rotation, revocation
- ✅ Legacy scoped **JWT bearer** support
- ✅ 8 business tools: content, Yoast SEO, WooCommerce, newsletter, reporting
- ✅ Autonomy policy engine: `read_only` / `draft_only` / `require_confirmation`
- ✅ Admin SPA (Vue 3 + TypeScript + Tailwind): tokens, approvals, logs, settings
- ✅ All tokens & codes stored **hashed**; full audit logging

## Verified With

- Official **MCP Inspector** v2.10.1 — connected over HTTPS with full OAuth flow
- **Meta Muse Code** (Muse Spark 1.3) — live sessions creating real products
  and content drafts through the approval queue

## Quick Start

```bash
composer install --no-dev -o
cd admin-ui && npm install && npm run build && cd ..
wp plugin activate musespark-mcp
wp musespark generate-token --user_id=1 --scopes='*' --ttl=3600
```

Point any MCP client at:

```
https://your-site.test/wp-json/musespark-mcp/v1/mcp
```

…or let the client discover OAuth automatically (401 → well-known → consent).

## Tools

| Tool | Access | Domain |
|---|---|---|
| `wp_list_posts` | read | content |
| `wp_create_post` | write | content |
| `seo_set_post_meta` | write | seo |
| `wc_list_products` | read | woo |
| `wc_create_product` | write | woo |
| `wc_update_stock` | write | woo |
| `newsletter_add_subscriber` | write | newsletter |
| `report_daily_summary` | read | report |

## Security Model

- Write actions obey the policy mode; `require_confirmation` routes them to an
  admin approval queue (dashboard or, soon, chat buttons)
- Scopes are validated at request time and recorded per task (audit trail)
- OAuth tokens/codes hashed at rest; PKCE mandatory; refresh rotation
- `localhost` auto-registration is **development-only** and will be removed
  before the 1.0 release (see Roadmap)

## Status & Roadmap

**Current: private prototype (v0.2.x), unpublished.** Functionally verified
locally; hardening in progress before the public release:

- [ ] PHPUnit + integration test harness
- [ ] Rate limiting & removal of dev-only auto-registration
- [ ] Chat gateway (Telegram control bot + WhatsApp Cloud API)
- [ ] Autonomy levels L0–L3 per domain
- [ ] wordpress.org submission package

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
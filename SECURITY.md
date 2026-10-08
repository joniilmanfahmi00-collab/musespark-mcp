# Security Policy

MuseSpark MCP Bridge gives AI agents controlled access to live business data.
We treat security reports with the urgency that responsibility deserves.

## Supported Versions

| Version | Supported          | Notes |
|---------|--------------------|-------|
| 0.2.x   | ✅ Security fixes   | Prototype hardening phase |
| 0.1.x   | ❌ End of life      | Superseded by 0.2.0 |
| < 0.1   | ❌ End of life      | Never published |

## Reporting a Vulnerability

<!-- TODO: replace with your real security contact before publishing -->
Email: **joniilmanfahmi22@gmail.com**
(Subject prefix: `[MCP-SEC]`)

Please include:
1. Description and impact of the vulnerability
2. Affected version(s) and environment (WP/Woo/PHP versions)
3. Minimal proof-of-concept (code or request trace)
4. Any suggested mitigation, if you have one

### What to Expect

| Step | Timeline |
|------|----------|
| Acknowledgement | ≤ 48 hours |
| Triage & severity rating | ≤ 5 business days |
| Fix for critical/high | target ≤ 30 days |
| Coordinated disclosure | 90 days after report (or on fix release) |

We will credit reporters in the release notes unless you prefer anonymity.
**Safe harbor:** good-faith research against your own installation is welcome;
never test against installations you do not own.

## Scope

**In scope:** authentication/authorization bypass, privilege or scope
escalation, approval-queue bypass, token/secret leakage, SQL injection,
XSS/CSRF in the admin UI, SSRF via tool handlers, OAuth flow weaknesses
(PKCE, redirect validation, token rotation).

**Out of scope:** vulnerabilities in WordPress core, WooCommerce, or other
plugins; rate-limiting absence in 0.x (tracked publicly as hardening);
social engineering; physical access attacks.

## Known Development-Only Features (0.x)

These exist intentionally in the prototype and **must not run in production**:

1. **Localhost client auto-registration**
   (`OAuthServer::autoRegisterLocalhostClient`) — auto-creates OAuth clients
   whose redirect URI is loopback, to ease MCP Inspector testing. Gate or
   remove it before exposing the plugin to untrusted networks.
2. **Verbose OAuth debug logging** (`error_log` in the authorize flow) —
   disable `WP_DEBUG_LOG` in production.
3. **SSL requirement toggle** — keep `musespark_mcp_require_ssl = 1` outside
   local development.

All three are scheduled for removal/gating in the 1.0 hardening release
(see CHANGELOG → Unreleased).

## Security Design Overview

- OAuth 2.1: mandatory PKCE (S256), single-use authorization codes,
  refresh-token rotation, exact redirect-URI matching (loopback relaxation
  limited to the dev auto-registration path)
- All OAuth tokens/codes and client secrets stored **SHA-256 hashed**
- Legacy JWT: HS256, short TTL, audience binding, revocation table
- Scopes validated per tool; write actions obey the policy mode and the
  human approval queue; approver capability always re-checked
- Requester scopes are persisted per task (`requested_scopes`) as an
  audit trail for approved executions
- Database access exclusively via `$wpdb->prepare()`; output escaped

## Hardening Checklist for Deployers

- [ ] Serve exclusively over HTTPS (Let's Encrypt or equivalent)
- [ ] Keep `musespark_mcp_require_ssl` enabled
- [ ] Rotate the JWT secret if the site URL or hosting changes
- [ ] Use least-privilege WordPress accounts for token holders
- [ ] Review the approval queue daily in `require_confirmation` mode
- [ ] Keep WordPress, WooCommerce, and PHP up to date
- [ ] Disable `WP_DEBUG_LOG` and remove dev-only features before launch

Thank you for helping keep UMKM businesses safe. 🙏
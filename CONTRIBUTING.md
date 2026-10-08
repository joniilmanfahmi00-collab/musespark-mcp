# Contributing to MuseSpark MCP Bridge

Hatur nuhun! 🙏 (Thank you!) This plugin lets AI agents safely run real
businesses — so contributions carry real-world weight. Please read this guide
before opening issues or pull requests.

## Code of Conduct

Be kind, be precise, be patient. We follow the
[Contributor Covenant v2.1](https://www.contributor-covenant.org/version/2/1/code_of_conduct/).
Report unacceptable behavior to the maintainers (see SECURITY.md for contact).

## What We're Looking For

- **New MCP tools** for WordPress/WooCommerce/SEO/newsletter domains
- **Chat gateways** (Telegram, WhatsApp Cloud API) and autonomy policies
- **Tests** — the project currently lacks an automated harness; this is the
  highest-impact area right now
- **Docs** (installation, OAuth flows, deployment) and translations
- **Security hardening** (rate limiting, monitoring, secret management)

## Development Setup

Requirements: PHP ≥ 8.2, WordPress ≥ 6.5, WooCommerce ≥ 9, Node ≥ 20,
Composer 2, MySQL/MariaDB. Laragon, LocalWP, or wp-env all work.

```bash
git clone REPO_URL musespark-mcp
cd musespark-mcp
composer install
cd admin-ui && npm install && npm run build && cd ..

# Activate (creates tables)
wp plugin activate musespark-mcp

# Issue a dev token
wp musespark generate-token --user_id=1 --scopes='*' --ttl=3600
```

Verify your build with the official MCP Inspector:

```bash
npx @modelcontextprotocol/inspector
# Transport: Streamable HTTP
# URL: https://your-host/wp-json/musespark-mcp/v1/mcp
```

## Coding Standards

### PHP

1. PSR-12, `declare(strict_types=1);` in every file, typed properties/returns
2. All SQL via `$wpdb->prepare();` never interpolate input into queries
3. Sanitize on input (`sanitize_text_field`, `absint`, `esc_url_raw`), escape on output (`esc_html`, `esc_attr`, `wp_json_encode`)
4. Tokens/secrets: store **hashed** (`hash('sha256', …)`), never plaintext
5. Every write tool must declare access, domain, scope, capability,and confirmation_required

### TypeScript / Vue
`strict: true`; Vue 3 `<script setup lang="ts">`; Pinia stores for state
Tailwind utility classes preferred over scoped CSS

**Adding a New Tool**

1. Register it in the matching `src/Tools/*.php` class:

```php
ToolRegistry::add([
    'name' => 'wc_update_price',
    'description' => 'Update a WooCommerce product price.',
    'capability' => 'manage_woocommerce',
    'scope' => 'woo:write',
    'access' => 'write',
    'domain' => 'woo',
    'confirmation_required' => true,
    'inputSchema' => [
        'type' => 'object',
        'properties' => [
            'product_id' => ['type' => 'integer'],
            'regular_price' => ['type' => 'string'],
        ],
        'required' => ['product_id', 'regular_price'],
    ],
    'handler' => function (array $args): array {
        // 1. Validate & sanitize arguments
        // 2. Perform the action via official WooCommerce APIs
        // 3. Return a plain array (wrapped into MCP content[] automatically)
    },
]);
```

2. Add the scope to OAuthServer::scopes() if it introduces a new one
3. Document it in docs/tools-reference.md
4. Add a test (once the harness lands) or a manual verification note in the PR

## Pull Request Process

1. Fork, then branch: feat/…, fix/…, docs/…, test/…, chore/…
2. Keep PRs small and single-purpose; describe why, not just what
3. Run `php -l` on touched files and npm run build in admin-ui
4. CI (PHP lint + frontend build) must pass
5. Use [Conventional Commits](https://www.conventionalcommits.org/?spm=a2ty_o01.29997173.0.0.3405c921Z2ZQh2): `feat:`, `fix:`, `docs:`, `test:`, `refactor:`, `chore:`, `security:`
6. A maintainer reviews within ~7 days; security-sensitive PRs get two reviews

## Issues
- Use the bug-report template; include WP/Woo/PHP versions and debug.log excerpts
- Feature requests: describe the business scenario, not just the API shape
- Never disclose vulnerabilities in public issues — see SECURITY.md

## License

By contributing you agree your work is released under
**GPL-2.0-or-later**, the same license as this project.
<?php

declare(strict_types=1);

namespace MuseSparkMCP\Api;

use WP_REST_Request;
use WP_REST_Response;

class OAuthServer
{
    public const ACCESS_TTL = 3600;        // 1 jam
    public const REFRESH_TTL = 2592000;    // 30 poé
    public const CODE_TTL = 300;           // 5 menit

    public function register(): void
    {
        $ns = 'musespark-mcp/v1/oauth';

        register_rest_route($ns, '/protected-resource-metadata', [
            'methods' => 'GET',
            'callback' => [$this, 'protectedResource'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/authorization-server-metadata', [
            'methods' => 'GET',
            'callback' => [$this, 'asMetadata'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/register', [
            'methods' => 'POST',
            'callback' => [$this, 'registerClient'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/token', [
            'methods' => 'POST',
            'callback' => [$this, 'token'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/revoke', [
            'methods' => 'POST',
            'callback' => [$this, 'revoke'],
            'permission_callback' => '__return_true',
        ]);
    }

    // ================= WELL-KNOWN METADATA (RFC 8414 / RFC 9728) =================

    public function maybeHandleWellKnown(): void
    {
        $path = (string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $path = rtrim($path, '/');

        $isAs = str_starts_with($path, '/.well-known/oauth-authorization-server')
            || $path === '/.well-known/openid-configuration';

        $isPr = str_starts_with($path, '/.well-known/oauth-protected-resource');

        if (!$isAs && !$isPr) {
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, OPTIONS');
            status_header(204);
            exit;
        }

        $data = $isAs ? $this->asMetadataArray() : $this->protectedResourceArray();

        status_header(200);
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Cache-Control: public, max-age=300');

        echo wp_json_encode($data);
        exit;
    }

    public function asMetadata(WP_REST_Request $r): WP_REST_Response
    {
        return new WP_REST_Response($this->asMetadataArray(), 200);
    }

    public function protectedResource(WP_REST_Request $r): WP_REST_Response
    {
        return new WP_REST_Response($this->protectedResourceArray(), 200);
    }

    private function asMetadataArray(): array
    {
        $base = rest_url('musespark-mcp/v1/oauth');

        return [
            'issuer' => site_url(),
            'authorization_endpoint' => home_url('/?musespark_oauth=authorize'),
            'token_endpoint' => $base . '/token',
            'registration_endpoint' => $base . '/register',
            'revocation_endpoint' => $base . '/revoke',
            'scopes_supported' => $this->scopes(),
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none', 'client_secret_post'],
        ];
    }

    private function protectedResourceArray(): array
    {
        return [
            'resource' => rest_url('musespark-mcp/v1/mcp'),
            'authorization_servers' => [site_url()],
            'bearer_methods_supported' => ['header'],
            'scopes_supported' => $this->scopes(),
        ];
    }

    private function scopes(): array
    {
        return [
            'content:read',
            'content:write',
            'seo:write',
            'woo:read',
            'woo:write',
            'newsletter:write',
            'report:read',
        ];
    }

    // ================= DYNAMIC CLIENT REGISTRATION (RFC 7591) =================

    public function registerClient(WP_REST_Request $r): WP_REST_Response
    {
        global $wpdb;

        $redirectUris = $r->get_param('redirect_uris');
        $redirectUris = is_array($redirectUris) ? array_map('esc_url_raw', $redirectUris) : [];

        if (empty($redirectUris)) {
            return new WP_REST_Response(['error' => 'invalid_redirect_uri'], 400);
        }

        $authMethod = $r->get_param('token_endpoint_auth_method') ?: 'none';

        if (!in_array($authMethod, ['none', 'client_secret_post'], true)) {
            return new WP_REST_Response(['error' => 'unsupported_auth_method'], 400);
        }

        $clientId = 'msm_' . bin2hex(random_bytes(12));
        $secretPlain = null;
        $secretHash = null;

        if ($authMethod === 'client_secret_post') {
            $secretPlain = bin2hex(random_bytes(24));
            $secretHash = hash('sha256', $secretPlain);
        }

        $clientName = sanitize_text_field($r->get_param('client_name') ?: 'Unnamed Client');

        $wpdb->insert($wpdb->prefix . 'musespark_mcp_oauth_clients', [
            'client_id' => $clientId,
            'client_secret_hash' => $secretHash,
            'client_name' => $clientName,
            'redirect_uris' => wp_json_encode($redirectUris),
            'token_endpoint_auth_method' => $authMethod,
            'created_at' => current_time('mysql'),
        ], ['%s', '%s', '%s', '%s', '%s', '%s']);

        $response = [
            'client_id' => $clientId,
            'client_id_issued_at' => time(),
            'client_name' => $clientName,
            'redirect_uris' => $redirectUris,
            'token_endpoint_auth_method' => $authMethod,
        ];

        if ($secretPlain) {
            $response['client_secret'] = $secretPlain;
        }

        return new WP_REST_Response($response, 201);
    }

    // ================= AUTHORIZATION ENDPOINT (CONSENT SCREEN) =================

    public function maybeHandleAuthorize(): void
    {
        if (get_query_var('musespark_oauth') !== 'authorize') {
            return;
        }

        error_log('=== OAUTH AUTHORIZE DEBUG ===');
        error_log('$_GET params: ' . wp_json_encode($_GET, JSON_UNESCAPED_SLASHES));

        $params = [
            'client_id' => sanitize_text_field($_GET['client_id'] ?? ''),
            'redirect_uri' => esc_url_raw(wp_unslash($_GET['redirect_uri'] ?? '')),
            'scope' => sanitize_text_field($_GET['scope'] ?? ''),
            'state' => sanitize_text_field($_GET['state'] ?? ''),
            'code_challenge' => sanitize_text_field($_GET['code_challenge'] ?? ''),
            'code_challenge_method' => sanitize_text_field($_GET['code_challenge_method'] ?? ''),
        ];

        $client = $this->findClient($params['client_id']);
        
        // Auto-register pikeun localhost testing (development only!)
        if (!$client && $this->isLocalhost($params['redirect_uri'])) {
            error_log('AUTO-REGISTER: Client not found, creating for localhost testing');
            $client = $this->autoRegisterLocalhostClient($params['client_id'], $params['redirect_uri']);
        }
        
        $allowed = $client ? (json_decode((string) $client->redirect_uris, true) ?: []) : [];

        error_log('Received client_id: ' . $params['client_id']);
        error_log('Received redirect_uri: ' . $params['redirect_uri']);
        error_log('Client found: ' . ($client ? 'YES' : 'NO'));
        if ($client) {
            error_log('Allowed redirect_uris: ' . wp_json_encode($allowed, JSON_UNESCAPED_SLASHES));
        }

        if (!$client || !self::redirectUriMatches($params['redirect_uri'], $allowed)) {
            $errorMsg = "Invalid client_id or redirect_uri.\n\n";
            $errorMsg .= "Received client_id: " . ($params['client_id'] ?: '(empty)') . "\n";
            $errorMsg .= "Received redirect_uri: " . ($params['redirect_uri'] ?: '(empty)') . "\n";
            if ($client) {
                $errorMsg .= "Allowed URIs: " . implode(', ', $allowed);
            } else {
                $errorMsg .= "Client not found in database";
            }
            error_log('OAUTH VALIDATION FAILED: ' . $errorMsg);
            wp_die(nl2br(esc_html($errorMsg)), 400);
        }

        if ($params['code_challenge_method'] !== 'S256' || $params['code_challenge'] === '') {
            wp_die('PKCE with S256 is required (OAuth 2.1).', 400);
        }

        if (!is_user_logged_in()) {
            $back = home_url('/?' . http_build_query(array_merge(
                ['musespark_oauth' => 'authorize'],
                array_map('sanitize_text_field', $_GET)
            )));
            wp_redirect(wp_login_url($back));
            exit;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->handleApprove($params);
            exit;
        }

        $this->renderConsent($params);
        exit;
    }

    /**
     * Cek naha URL téh localhost (pikeun auto-register testing)
     */
    private function isLocalhost(string $url): bool
    {
        $host = wp_parse_url($url, PHP_URL_HOST);
        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    /**
     * Auto-register client pikeun localhost testing (development only!)
     */
    private function autoRegisterLocalhostClient(string $clientId, string $redirectUri): ?object
    {
        global $wpdb;

        $wpdb->insert($wpdb->prefix . 'musespark_mcp_oauth_clients', [
            'client_id' => $clientId,
            'client_secret_hash' => null,
            'client_name' => 'Auto-registered localhost client',
            'redirect_uris' => wp_json_encode([$redirectUri]),
            'token_endpoint_auth_method' => 'none',
            'created_at' => current_time('mysql'),
        ], ['%s', '%s', '%s', '%s', '%s', '%s']);

        return $this->findClient($clientId);
    }

    /**
     * Match redirect_uri kalayan toleransi pikeun localhost (port béda)
     */
    private static function redirectUriMatches(string $requested, array $allowed): bool
    {
        if (in_array($requested, $allowed, true)) {
            return true;
        }

        $reqHost = wp_parse_url($requested, PHP_URL_HOST);
        if ($reqHost !== 'localhost' && $reqHost !== '127.0.0.1' && $reqHost !== '::1') {
            return false;
        }

        $reqPath = wp_parse_url($requested, PHP_URL_PATH) ?: '/';

        foreach ($allowed as $allowedUri) {
            $allowedHost = wp_parse_url($allowedUri, PHP_URL_HOST);
            $allowedPath = wp_parse_url($allowedUri, PHP_URL_PATH) ?: '/';

            if ($reqHost === $allowedHost && $reqPath === $allowedPath) {
                return true;
            }
        }

        return false;
    }

    private function handleApprove(array $p): void
    {
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'musespark_oauth_approve')) {
            wp_die('Nonce verification failed.', 403);
        }

        if (($_POST['decision'] ?? '') === 'deny') {
            $this->redirectError($p, 'access_denied');
        }

        $code = bin2hex(random_bytes(32));

        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'musespark_mcp_oauth_codes', [
            'code_hash' => hash('sha256', $code),
            'client_id' => $p['client_id'],
            'user_id' => get_current_user_id(),
            'redirect_uri' => $p['redirect_uri'],
            'scopes' => $p['scope'],
            'code_challenge' => $p['code_challenge'],
            'expires_at' => gmdate('Y-m-d H:i:s', time() + self::CODE_TTL),
            'used' => 0,
            'created_at' => current_time('mysql'),
        ], ['%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s']);

        $this->redirectWithCode($p, $code);
    }

    private function renderConsent(array $p): void
    {
        $scopeList = $p['scope'] === '' ? [] : explode(' ', $p['scope']);
        $action = esc_url(home_url('/?' . http_build_query(
            array_map('sanitize_text_field', $_GET)
        )));
        ?>
        <!DOCTYPE html>
        <html><head><meta charset="utf-8"><title>Izinkan Akses — MuseSpark MCP</title>
        <style>
            body{font-family:system-ui;background:#f3f4f6;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
            .card{background:#fff;padding:32px;border-radius:12px;max-width:480px;box-shadow:0 4px 20px rgba(0,0,0,.08)}
            h1{font-size:20px;margin:0 0 8px}
            p{color:#6b7280;font-size:14px}
            ul{margin:16px 0;padding-left:20px;font-size:14px}
            button{padding:10px 20px;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px}
            .yes{background:#4f46e5;color:#fff}
            .no{background:#e5e7eb;color:#374151;margin-left:8px}
        </style></head><body>
        <div class="card">
            <h1>🔐 Izinkan akses ka bisnis anjeun?</h1>
            <p>Aplikasi <strong><?php echo esc_html($p['client_id']); ?></strong> menta ijin:</p>
            <ul>
                <?php foreach ($scopeList as $s) : ?>
                    <li><code><?php echo esc_html($s); ?></code></li>
                <?php endforeach; ?>
            </ul>
            <p>⚠️ Ngan approve upami anjeun percanten ka aplikasi ieu.</p>
            <form method="post" action="<?php echo $action; ?>">
                <?php wp_nonce_field('musespark_oauth_approve'); ?>
                <button class="yes" name="decision" value="approve">✅ Approve</button>
                <button class="no" name="decision" value="deny">Tolak</button>
            </form>
        </div></body></html>
        <?php
    }

    private function redirectWithCode(array $p, string $code): void
    {
        $url = add_query_arg(['code' => $code, 'state' => $p['state']], $p['redirect_uri']);
        wp_redirect($url);
        exit;
    }

    private function redirectError(array $p, string $error): void
    {
        $url = add_query_arg(['error' => $error, 'state' => $p['state']], $p['redirect_uri']);
        wp_redirect($url);
        exit;
    }

    // ================= TOKEN ENDPOINT =================

    public function token(WP_REST_Request $r): WP_REST_Response
    {
        return match ($r->get_param('grant_type')) {
            'authorization_code' => $this->grantCode($r),
            'refresh_token' => $this->grantRefresh($r),
            default => new WP_REST_Response(['error' => 'unsupported_grant_type'], 400),
        };
    }

    private function grantCode(WP_REST_Request $r): WP_REST_Response
    {
        global $wpdb;

        $code = (string) $r->get_param('code');
        $verifier = (string) $r->get_param('code_verifier');
        $clientId = (string) $r->get_param('client_id');
        $redirectUri = esc_url_raw((string) $r->get_param('redirect_uri'));

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}musespark_mcp_oauth_codes WHERE code_hash = %s LIMIT 1",
            hash('sha256', $code)
        ));

        if (!$row
            || (int) $row->used === 1
            || strtotime($row->expires_at) < time()
            || $row->client_id !== $clientId
            || $row->redirect_uri !== $redirectUri) {
            return new WP_REST_Response(['error' => 'invalid_grant'], 400);
        }

        $calc = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        if (!hash_equals($row->code_challenge, $calc)) {
            return new WP_REST_Response([
                'error' => 'invalid_grant',
                'error_description' => 'PKCE verification failed',
            ], 400);
        }

        $wpdb->update(
            $wpdb->prefix . 'musespark_mcp_oauth_codes',
            ['used' => 1],
            ['id' => (int) $row->id],
            ['%d'],
            ['%d']
        );

        return new WP_REST_Response(
            $this->issueTokens($clientId, (int) $row->user_id, (string) $row->scopes),
            200
        );
    }

    private function grantRefresh(WP_REST_Request $r): WP_REST_Response
    {
        global $wpdb;

        $refresh = (string) $r->get_param('refresh_token');

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}musespark_mcp_oauth_tokens
             WHERE refresh_token_hash = %s AND revoked = 0 LIMIT 1",
            hash('sha256', $refresh)
        ));

        if (!$row || ($row->refresh_expires_at && strtotime($row->refresh_expires_at) < time())) {
            return new WP_REST_Response(['error' => 'invalid_grant'], 400);
        }

        $newAccess = bin2hex(random_bytes(32));
        $newRefresh = bin2hex(random_bytes(32));

        $wpdb->update($wpdb->prefix . 'musespark_mcp_oauth_tokens', [
            'access_token_hash' => hash('sha256', $newAccess),
            'refresh_token_hash' => hash('sha256', $newRefresh),
            'access_expires_at' => gmdate('Y-m-d H:i:s', time() + self::ACCESS_TTL),
            'refresh_expires_at' => gmdate('Y-m-d H:i:s', time() + self::REFRESH_TTL),
        ], ['id' => (int) $row->id], ['%s', '%s', '%s', '%s'], ['%d']);

        return new WP_REST_Response([
            'access_token' => $newAccess,
            'token_type' => 'Bearer',
            'expires_in' => self::ACCESS_TTL,
            'refresh_token' => $newRefresh,
            'scope' => $row->scopes,
        ], 200);
    }

    private function issueTokens(string $clientId, int $userId, string $scopes): array
    {
        global $wpdb;

        $access = bin2hex(random_bytes(32));
        $refresh = bin2hex(random_bytes(32));

        $wpdb->insert($wpdb->prefix . 'musespark_mcp_oauth_tokens', [
            'access_token_hash' => hash('sha256', $access),
            'refresh_token_hash' => hash('sha256', $refresh),
            'client_id' => $clientId,
            'user_id' => $userId,
            'scopes' => $scopes,
            'access_expires_at' => gmdate('Y-m-d H:i:s', time() + self::ACCESS_TTL),
            'refresh_expires_at' => gmdate('Y-m-d H:i:s', time() + self::REFRESH_TTL),
            'revoked' => 0,
            'created_at' => current_time('mysql'),
        ], ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s']);

        return [
            'access_token' => $access,
            'token_type' => 'Bearer',
            'expires_in' => self::ACCESS_TTL,
            'refresh_token' => $refresh,
            'scope' => $scopes,
        ];
    }

    public function revoke(WP_REST_Request $r): WP_REST_Response
    {
        global $wpdb;

        $token = (string) $r->get_param('token');
        $hash = hash('sha256', $token);

        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}musespark_mcp_oauth_tokens
             SET revoked = 1
             WHERE access_token_hash = %s OR refresh_token_hash = %s",
            $hash,
            $hash
        ));

        return new WP_REST_Response(null, 200);
    }

    // ================= 401 CHALLENGE (RFC 9728) =================

    public function addWwwAuthenticate($response, $server, $request)
    {
        if (str_contains((string) $request->get_route(), 'musespark-mcp/v1/mcp')
            && $response->get_status() === 401) {
            $response->header(
                'WWW-Authenticate',
                'Bearer resource_metadata="' . rest_url('musespark-mcp/v1/oauth/protected-resource-metadata') . '"'
            );
        }

        return $response;
    }

    // ================= HELPERS =================

    private function findClient(string $clientId): ?object
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}musespark_mcp_oauth_clients WHERE client_id = %s LIMIT 1",
            $clientId
        ));

        return $row ?: null;
    }
}
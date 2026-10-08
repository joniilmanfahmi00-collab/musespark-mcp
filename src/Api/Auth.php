<?php

declare(strict_types=1);

namespace MuseSparkMCP\Api;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use WP_Error;
use WP_REST_Request;

class Auth
{
    public static array $scopes = [];

    public static function verify(WP_REST_Request $request): bool|WP_Error
    {
        try {
            if (!self::sslOk()) {
                return new WP_Error(
                    'musespark_mcp_ssl_required',
                    'HTTPS is required.',
                    ['status' => 403]
                );
            }

            $header = (string) $request->get_header('Authorization');

            if (!preg_match('/Bearer\s+(\S+)/i', $header, $matches)) {
                return new WP_Error(
                    'musespark_mcp_missing_token',
                    'Missing Bearer token.',
                    ['status' => 401]
                );
            }

            $token = $matches[1];

            // Token OAuth opaque (tanpa titik) vs JWT (dua titik)
            if (substr_count($token, '.') !== 2) {
                return self::verifyOAuthToken($token);
            }

            $decoded = JWT::decode($token, new Key(self::secret(), 'HS256'));

            $aud = (array) ($decoded->aud ?? []);

            if (!in_array('musespark-mcp', $aud, true)) {
                return new WP_Error(
                    'musespark_mcp_invalid_aud',
                    'Invalid token audience.',
                    ['status' => 401]
                );
            }

            $userId = absint($decoded->sub ?? 0);
            $user = get_userdata($userId);

            if (!$user) {
                return new WP_Error(
                    'musespark_mcp_invalid_user',
                    'Invalid token subject.',
                    ['status' => 401]
                );
            }

            $jti = (string) ($decoded->jti ?? '');

            if ($jti && self::isRevoked($jti)) {
                return new WP_Error(
                    'musespark_mcp_token_revoked',
                    'Token has been revoked.',
                    ['status' => 401]
                );
            }

            wp_set_current_user($userId);

            self::$scopes = array_map('strval', (array) ($decoded->scopes ?? []));

            return true;
        } catch (\Throwable $e) {
            return new WP_Error(
                'musespark_mcp_invalid_token',
                'Invalid token: ' . $e->getMessage(),
                ['status' => 401]
            );
        }
    }

    /**
     * Verifikasi access token OAuth 2.1 (opaque token)
     */
    private static function verifyOAuthToken(string $token): bool|WP_Error
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}musespark_mcp_oauth_tokens
             WHERE access_token_hash = %s AND revoked = 0 LIMIT 1",
            hash('sha256', $token)
        ));

        if (!$row || strtotime($row->access_expires_at) < time()) {
            return new WP_Error(
                'musespark_mcp_invalid_token',
                'Invalid or expired OAuth access token.',
                ['status' => 401]
            );
        }

        wp_set_current_user((int) $row->user_id);
        self::$scopes = array_values(array_filter(explode(' ', (string) $row->scopes)));

        return true;
    }

    public static function secret(): string
    {
        if (defined('MUSESPARK_MCP_JWT_SECRET') && MUSESPARK_MCP_JWT_SECRET) {
            return (string) MUSESPARK_MCP_JWT_SECRET;
        }

        $secret = get_option('musespark_mcp_jwt_secret');

        if (!$secret) {
            throw new \RuntimeException('JWT secret is not configured.');
        }

        return (string) $secret;
    }

    public static function hasScope(string $scope): bool
    {
        if (in_array('*', self::$scopes, true)) {
            return true;
        }

        if (in_array($scope, self::$scopes, true)) {
            return true;
        }

        if (str_contains($scope, ':')) {
            [$group, $action] = explode(':', $scope, 2);

            if ($action === 'read' && in_array($group . ':write', self::$scopes, true)) {
                return true;
            }
        }

        return false;
    }

    private static function sslOk(): bool
    {
        $requireSsl = get_option('musespark_mcp_require_ssl', '1');

        if ($requireSsl !== '1') {
            return true;
        }

        return is_ssl();
    }

    private static function isRevoked(string $jti): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'musespark_mcp_revoked';

        $found = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$table} WHERE jti = %s LIMIT 1", $jti)
        );

        return !empty($found);
    }
}
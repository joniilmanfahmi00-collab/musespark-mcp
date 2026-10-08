<?php

declare(strict_types=1);

namespace MuseSparkMCP\Support;

use Firebase\JWT\JWT;
use MuseSparkMCP\Api\Auth;

class JwtService
{
    public static function issue(int $userId, array $scopes = [], int $ttl = 900): string
    {
        $now = time();

        $payload = [
            'iss' => site_url(),
            'aud' => 'musespark-mcp',
            'sub' => $userId,
            'jti' => wp_generate_uuid4(),
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $ttl,
            'scopes' => $scopes,
        ];

        return JWT::encode($payload, Auth::secret(), 'HS256');
    }
}
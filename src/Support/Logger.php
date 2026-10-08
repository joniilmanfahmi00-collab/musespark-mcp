<?php

declare(strict_types=1);

namespace MuseSparkMCP\Support;

class Logger
{
    public static function log(
        string $status,
        ?string $method = null,
        ?string $tool = null,
        ?string $message = null
    ): void {
        global $wpdb;

        $table = $wpdb->prefix . 'musespark_mcp_logs';

        $wpdb->insert(
            $table,
            [
                'jti' => null,
                'user_id' => get_current_user_id() ?: null,
                'method' => $method,
                'tool' => $tool,
                'status' => $status,
                'message' => $message,
                'ip' => self::ip(),
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    private static function ip(): string
    {
        return sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
    }
}
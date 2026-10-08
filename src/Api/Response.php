<?php

declare(strict_types=1);

namespace MuseSparkMCP\Api;

use WP_REST_Response;

class Response
{
    public static function success(mixed $id, mixed $result): WP_REST_Response
    {
        return new WP_REST_Response([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ], 200);
    }

    public static function error(mixed $id, int $code, string $message): WP_REST_Response
    {
        return new WP_REST_Response([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], 200);
    }
}
<?php

declare(strict_types=1);

namespace MuseSparkMCP\Api;

use MuseSparkMCP\Support\JwtService;
use WP_REST_Request;
use WP_REST_Response;

class AdminApi
{
    public function register(): void
    {
        // Tokens
        register_rest_route('musespark-mcp/v1', '/admin/tokens', [
            'methods' => 'GET',
            'callback' => [$this, 'listTokens'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route('musespark-mcp/v1', '/admin/tokens', [
            'methods' => 'POST',
            'callback' => [$this, 'createToken'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route('musespark-mcp/v1', '/admin/tokens/(?P<id>\d+)/revoke', [
            'methods' => 'POST',
            'callback' => [$this, 'revokeToken'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        // Tasks
        register_rest_route('musespark-mcp/v1', '/admin/tasks', [
            'methods' => 'GET',
            'callback' => [$this, 'listTasks'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route('musespark-mcp/v1', '/admin/tasks/(?P<id>\d+)/reject', [
            'methods' => 'POST',
            'callback' => [$this, 'rejectTask'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        // Logs
        register_rest_route('musespark-mcp/v1', '/admin/logs', [
            'methods' => 'GET',
            'callback' => [$this, 'listLogs'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        // Settings
        register_rest_route('musespark-mcp/v1', '/admin/settings', [
            'methods' => 'GET',
            'callback' => [$this, 'getSettings'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route('musespark-mcp/v1', '/admin/settings', [
            'methods' => 'POST',
            'callback' => [$this, 'updateSettings'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        // Dashboard
        register_rest_route('musespark-mcp/v1', '/admin/dashboard', [
            'methods' => 'GET',
            'callback' => [$this, 'getDashboard'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
    }

    public function listTokens(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $table = $wpdb->prefix . 'musespark_mcp_tokens';

        // Note: Anjeun peryogi nyieun tabel tokens upami can aya
        // Ieu placeholder, kedah ditambihan di Activator

        $tokens = [];

        return new WP_REST_Response($tokens, 200);
    }

    public function createToken(WP_REST_Request $request): WP_REST_Response
    {
        $name = sanitize_text_field($request->get_param('name'));
        $scopes = $request->get_param('scopes') ?? [];
        $ttl = absint($request->get_param('ttl') ?? 3600);

        $userId = get_current_user_id();

        try {
            $token = JwtService::issue($userId, $scopes, $ttl);

            // Simpen token ka database (placeholder)
            // Anjeun peryogi nyimpen metadata token

            return new WP_REST_Response([
                'id' => 1, // Placeholder
                'name' => $name,
                'scopes' => $scopes,
                'token' => $token,
            ], 200);
        } catch (\Throwable $e) {
            return new WP_REST_Response([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function revokeToken(WP_REST_Request $request): WP_REST_Response
    {
        $id = absint($request->get_param('id'));

        // Placeholder: revoke token logic

        return new WP_REST_Response(['success' => true], 200);
    }

    public function listTasks(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $table = $wpdb->prefix . 'musespark_mcp_tasks';
        $status = $request->get_param('status');

        $where = $status ? $wpdb->prepare('WHERE status = %s', $status) : '';

        $tasks = $wpdb->get_results(
            "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT 50"
        );

        return new WP_REST_Response($tasks, 200);
    }

    public function rejectTask(WP_REST_Request $request): WP_REST_Response
    {
        $id = absint($request->get_param('id'));

        global $wpdb;

        $table = $wpdb->prefix . 'musespark_mcp_tasks';

        $wpdb->update(
            $table,
            [
                'status' => 'rejected',
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );

        return new WP_REST_Response(['success' => true], 200);
    }

    public function listLogs(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $table = $wpdb->prefix . 'musespark_mcp_logs';
        $page = absint($request->get_param('page') ?? 1);
        $perPage = absint($request->get_param('per_page') ?? 20);
        $offset = ($page - 1) * $perPage;

        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $perPage,
                $offset
            )
        );

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        return new WP_REST_Response([
            'logs' => $logs,
            'total' => $total,
        ], 200);
    }

    public function getSettings(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response([
            'mode' => get_option('musespark_mcp_mode', 'draft_only'),
            'require_ssl' => get_option('musespark_mcp_require_ssl', '1') === '1',
        ], 200);
    }

    public function updateSettings(WP_REST_Request $request): WP_REST_Response
    {
        $mode = sanitize_text_field($request->get_param('mode'));
        $requireSsl = (bool) $request->get_param('require_ssl');

        if (!in_array($mode, ['read_only', 'draft_only', 'require_confirmation'], true)) {
            $mode = 'draft_only';
        }

        update_option('musespark_mcp_mode', $mode);
        update_option('musespark_mcp_require_ssl', $requireSsl ? '1' : '0');

        return new WP_REST_Response(['success' => true], 200);
    }

    public function getDashboard(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $logsTable = $wpdb->prefix . 'musespark_mcp_logs';
        $tasksTable = $wpdb->prefix . 'musespark_mcp_tasks';

        $totalLogs = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$logsTable}");
        $pendingTasks = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$tasksTable} WHERE status = %s", 'pending')
        );

        return new WP_REST_Response([
            'total_tokens' => 0, // Placeholder
            'pending_tasks' => $pendingTasks,
            'total_logs' => $totalLogs,
            'mode' => get_option('musespark_mcp_mode', 'draft_only'),
        ], 200);
    }
}
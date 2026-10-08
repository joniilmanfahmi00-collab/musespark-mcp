<?php

declare(strict_types=1);

namespace MuseSparkMCP\Api;

use MuseSparkMCP\Mcp\ToolRegistry;
use MuseSparkMCP\Models\TaskRepository;
use MuseSparkMCP\Support\Logger;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class McpEndpoint
{
    public function register(): void
    {
        register_rest_route('musespark-mcp/v1', '/rpc', [
            'methods' => 'POST',
            'callback' => [$this, 'handle'],
            'permission_callback' => [Auth::class, 'verify'],
        ]);

        register_rest_route('musespark-mcp/v1', '/admin/tasks/(?P<id>\d+)/approve', [
            'methods' => 'POST',
            'callback' => [$this, 'approveTask'],
            'permission_callback' => function (): bool {
                return current_user_can('manage_options');
            },
        ]);
    }

    public function handle(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $rawBody = (string) $request->get_body();
        $body = json_decode($rawBody, true);

        if (!is_array($body)) {
            return Response::error(null, -32700, 'Parse error');
        }

        $jsonrpc = $body['jsonrpc'] ?? null;
        $method = $body['method'] ?? null;
        $params = $body['params'] ?? [];
        $id = $body['id'] ?? null;

        if ($jsonrpc !== '2.0') {
            return Response::error($id, -32600, 'Invalid Request');
        }

        Logger::log('received', $method, $params['name'] ?? null);

        return match ($method) {
            'tools/list' => $this->toolsList($id),
            'tools/call' => $this->toolsCall($id, $params),
            default => Response::error($id, -32601, 'Method not found'),
        };
    }

    public function approveTask(WP_REST_Request $request): WP_REST_Response
    {
        $taskId = absint($request->get_param('id'));

        try {
            $result = TaskRepository::approve($taskId);

            return new WP_REST_Response([
                'success' => true,
                'task_id' => $taskId,
                'result' => $result,
            ], 200);
        } catch (\Throwable $e) {
            return new WP_REST_Response([
                'success' => false,
                'task_id' => $taskId,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    private function toolsList(mixed $id): WP_REST_Response
    {
        return Response::success($id, [
            'tools' => ToolRegistry::all(),
        ]);
    }

    private function toolsCall(mixed $id, array $params): WP_REST_Response
    {
        $name = $params['name'] ?? '';
        $arguments = $params['arguments'] ?? [];

        if (!$name) {
            return Response::error($id, -32602, 'Tool name is required.');
        }

        try {
            $result = ToolRegistry::call($name, $arguments);

            Logger::log('success', 'tools/call', $name);

            return Response::success($id, [
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            Logger::log('error', 'tools/call', $name, $e->getMessage());

            return Response::error($id, -32000, $e->getMessage());
        }
    }
}
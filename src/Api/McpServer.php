<?php

declare(strict_types=1);

namespace MuseSparkMCP\Api;

use MuseSparkMCP\Mcp\ToolRegistry;
use WP_REST_Request;
use WP_REST_Response;

class McpServer
{
    const PROTOCOL_VERSION = '2025-06-18';

    public function register(): void
    {
        register_rest_route('musespark-mcp/v1', '/mcp', [
            'methods' => 'POST',
            'callback' => [$this, 'handle'],
            'permission_callback' => [Auth::class, 'verify'],
        ]);
    }

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        $body = json_decode((string) $request->get_body(), true);

        if (!is_array($body)) {
            return $this->error(null, -32700, 'Parse error');
        }

        $method = $body['method'] ?? '';
        $id = $body['id'] ?? null;
        $params = $body['params'] ?? [];

        // Notification (tanpa id) → 202 Accepted, teu perlu response body
        if ($id === null) {
            return new WP_REST_Response(null, 202);
        }

        switch ($method) {
            case 'initialize':
                $response = $this->result($id, $this->initialize($params));
                $response->header('Mcp-Session-Id', $this->createSession($params));
                return $response;

            case 'ping':
                return $this->result($id, new \stdClass());

            case 'tools/list':
                return $this->result($id, ['tools' => ToolRegistry::allMcp()]);

            case 'tools/call':
                return $this->result($id, $this->toolsCall($params));

            case 'resources/list':
                return $this->result($id, $this->resourcesList());

            case 'resources/read':
                return $this->result($id, $this->resourcesRead($params));

            case 'prompts/list':
                return $this->result($id, $this->promptsList());

            case 'prompts/get':
                return $this->result($id, $this->promptsGet($params));

            default:
                return $this->error($id, -32601, 'Method not found: ' . $method);
        }
    }

    /**
     * Handshake MCP: negosiasi versi protokol + deklarasi capabilities
     */
    private function initialize(array $params): array
    {
        $clientVersion = $params['protocolVersion'] ?? '';
        $supported = ['2025-06-18', '2025-03-26', '2024-11-05'];

        return [
            'protocolVersion' => in_array($clientVersion, $supported, true)
                ? $clientVersion
                : self::PROTOCOL_VERSION,
            'capabilities' => [
                'tools' => ['listChanged' => false],
                'resources' => ['subscribe' => false, 'listChanged' => false],
                'prompts' => ['listChanged' => false],
            ],
            'serverInfo' => [
                'name' => 'musespark-mcp',
                'title' => 'MuseSpark MCP Bridge for WordPress & WooCommerce',
                'version' => MUSESPARK_MCP_VERSION,
            ],
            'instructions' => 'Server MCP pikeun otomasi bisnis UMKM: '
                . 'kontén, SEO, WooCommerce, newsletter, sareng laporan. '
                . 'Write actions tunduk kana policy mode (draft_only / require_confirmation).',
        ];
    }

    /**
     * Format hasil MCP standar: content[] blocks + structuredContent
     */
    private function toolsCall(array $params): array
    {
        $name = $params['name'] ?? '';
        $args = $params['arguments'] ?? [];

        try {
            $data = ToolRegistry::call($name, $args);

            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                    ],
                ],
                'structuredContent' => $data,
                'isError' => false,
            ];
        } catch (\Throwable $e) {
            // Tool error = result kalayan isError:true (lain JSON-RPC error)
            return [
                'content' => [
                    ['type' => 'text', 'text' => 'Tool error: ' . $e->getMessage()],
                ],
                'isError' => true,
            ];
        }
    }

    private function resourcesList(): array
    {
        return ['resources' => [
            [
                'uri' => 'musespark://report/daily',
                'name' => 'Daily Business Report',
                'mimeType' => 'application/json',
                'description' => 'Ringkasan bisnis poéan: posts, produk, stock.',
            ],
            [
                'uri' => 'musespark://woo/low-stock',
                'name' => 'Low Stock Products',
                'mimeType' => 'application/json',
                'description' => 'Produk anu stock na kritis.',
            ],
            [
                'uri' => 'musespark://chat/policy',
                'name' => 'Customer Service Policy',
                'mimeType' => 'text/markdown',
                'description' => 'Panduan tone, FAQ, sareng aturan eskalasi pikeun chat agent.',
            ],
        ]];
    }

    private function resourcesRead(array $params): array
    {
        $uri = $params['uri'] ?? '';

        $data = match ($uri) {
            'musespark://report/daily' => $this->dailyReport(),
            'musespark://woo/low-stock' => $this->lowStock(),
            'musespark://chat/policy' => $this->chatPolicy(),
            default => null,
        };

        if ($data === null) {
            return ['contents' => []];
        }

        $isJson = !str_ends_with($uri, 'policy');

        return ['contents' => [[
            'uri' => $uri,
            'mimeType' => $isJson ? 'application/json' : 'text/markdown',
            'text' => $isJson ? wp_json_encode($data) : $data,
        ]]];
    }

    private function promptsList(): array
    {
        return ['prompts' => [
            [
                'name' => 'daily_briefing',
                'description' => 'Briefing bisnis poéan pikeun owner UMKM',
                'arguments' => [],
            ],
            [
                'name' => 'product_description',
                'description' => 'Jieun deskripsi produk anu narik',
                'arguments' => [
                    ['name' => 'product_name', 'description' => 'Ngaran produk', 'required' => true],
                    ['name' => 'tone', 'description' => 'friendly / professional / playful', 'required' => false],
                ],
            ],
            [
                'name' => 'customer_service_reply',
                'description' => 'Bales pesen pelanggan dumasar policy',
                'arguments' => [
                    ['name' => 'message', 'required' => true],
                ],
            ],
        ]];
    }

    private function promptsGet(array $params): array
    {
        $name = $params['name'] ?? '';
        $args = $params['arguments'] ?? [];

        $text = match ($name) {
            'daily_briefing' => "Anjeun asisten bisnis UMKM. Maca resource musespark://report/daily, "
                . "teras jieun briefing: (1) penjualan & order, (2) stock kritis, "
                . "(3) kontén anu perlu aksi, (4) 3 rekomendasi prioritas poé ieu.",
            'product_description' => "Jieun deskripsi produk WooCommerce pikeun \""
                . ($args['product_name'] ?? 'produk') . "\" kalayan tone "
                . ($args['tone'] ?? 'friendly') . ". Sertakeun: kaunggulan, spesifikasi, "
                . "call-to-action. Maksimal 700 karakter.",
            'customer_service_reply' => "Pesen pelanggan: \"" . ($args['message'] ?? '') . "\"\n\n"
                . "Maca resource musespark://chat/policy heula. Bales kalayan basa anu sopan "
                . "sareng hangat. Upami pesen ngeunaan refund/komplain serius/hukum, "
                . "ULAH bales langsung — panggil tool chat_escalate_to_human.",
            default => null,
        };

        if ($text === null) {
            return ['messages' => []];
        }

        return [
            'description' => 'Prompt template MuseSpark',
            'messages' => [
                ['role' => 'user', 'content' => ['type' => 'text', 'text' => $text]],
            ],
        ];
    }

    // --- Helpers (implementasi ringkes, tiasa dipindah ka Services) ---

    private function dailyReport(): array
    {
        $counts = wp_count_posts('post');
        return [
            'date' => current_time('Y-m-d H:i:s'),
            'posts' => ['draft' => (int) $counts->draft, 'publish' => (int) $counts->publish],
        ];
    }

    private function lowStock(): array
    {
        if (!function_exists('wc_get_products')) return [];
        return wc_get_products([
            'limit' => 20, 'stock' => 'low', 'return' => 'ids',
        ]);
    }

    private function chatPolicy(): string
    {
        return get_option('musespark_mcp_chat_policy',
            "# Policy Chat Agent\n- Tone: sopan, hangat, basa kasawargaan\n"
            . "- Boleh: info stock, status order, jam buka, cara pesan\n"
            . "- Eskalasi ka manusa: refund, komplain produk, ancaman hukum, pesen ambang 2x\n");
    }

    private function createSession(array $params): string
    {
        $sid = wp_generate_uuid4();
        set_transient('musespark_mcp_session_' . $sid, [
            'client' => $params['clientInfo'] ?? [],
            'created' => time(),
        ], DAY_IN_SECONDS);
        return $sid;
    }

    private function result(mixed $id, mixed $result): WP_REST_Response
    {
        return new WP_REST_Response(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result], 200);
    }

    private function error(mixed $id, int $code, string $message): WP_REST_Response
    {
        return new WP_REST_Response([
            'jsonrpc' => '2.0', 'id' => $id,
            'error' => ['code' => $code, 'message' => $message],
        ], 200);
    }
}
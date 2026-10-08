<?php

declare(strict_types=1);

namespace MuseSparkMCP\Tools;

use MuseSparkMCP\Mcp\ToolRegistry;
use MuseSparkMCP\Support\Policy;

class ReportTools
{
    public static function register(): void
    {
        ToolRegistry::add([
            'name' => 'report_daily_summary',
            'description' => 'Get a simple daily business summary.',
            'capability' => 'read',
            'scope' => 'report:read',
            'access' => 'read',
            'domain' => 'report',
            'inputSchema' => [
            'type' => 'object',
                      'properties' => new \stdClass(),   // <-- encode jadi {} sanés []
            ],
            'handler' => function (): array {
                $postCounts = wp_count_posts('post');
                $productCounts = post_type_exists('product') ? wp_count_posts('product') : null;

                $summary = [
                    'date' => current_time('Y-m-d H:i:s'),
                    'policy_mode' => Policy::mode(),
                    'posts' => [
                        'draft' => (int) ($postCounts->draft ?? 0),
                        'pending' => (int) ($postCounts->pending ?? 0),
                        'publish' => (int) ($postCounts->publish ?? 0),
                    ],
                ];

                if (post_type_exists('product')) {
                    $summary['woo'] = [
                        'products_publish' => (int) ($productCounts->publish ?? 0),
                    ];

                    if (function_exists('wc_get_products')) {
                        $outOfStock = wc_get_products([
                            'limit' => 20,
                            'stock_status' => 'outofstock',
                            'return' => 'ids',
                        ]);

                        $summary['woo']['out_of_stock_sample'] = $outOfStock;
                        $summary['woo']['out_of_stock_count'] = count($outOfStock);
                    }
                }

                return $summary;
            },
        ]);
    }
}
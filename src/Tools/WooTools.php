<?php 

declare(strict_types=1);

namespace MuseSparkMCP\Tools;

use MuseSparkMCP\Mcp\ToolRegistry;

class WooTools
{
    public static function register(): void
    {
        ToolRegistry::add([
            'name' => 'wc_list_products',
            'description' => 'List WooCommerce products.',
            'capability' => 'manage_woocommerce',
            'scope' => 'woo:read',
            'access' => 'read',
            'domain' => 'woo',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'limit' => ['type' => 'integer'],
                    'page' => ['type' => 'integer'],
                ],
            ],
            'handler' => function (array $args): array {
                if (!function_exists('wc_get_products')) {
                    throw new \RuntimeException('WooCommerce is not active.');
                }

                $limit = min(50, absint($args['limit'] ?? 10));
                $page = max(1, absint($args['page'] ?? 1));

                $products = wc_get_products([
                    'limit' => $limit,
                    'page' => $page,
                    'status' => 'publish',
                    'orderby' => 'date',
                    'order' => 'DESC',
                ]);

                $items = [];

                foreach ($products as $product) {
                    $items[] = [
                        'id' => $product->get_id(),
                        'name' => $product->get_name(),
                        'sku' => $product->get_sku(),
                        'price' => $product->get_price(),
                        'stock_quantity' => $product->get_stock_quantity(),
                        'stock_status' => $product->get_stock_status(),
                    ];
                }

                return [
                    'page' => $page,
                    'limit' => $limit,
                    'products' => $items,
                ];
            },
        ]);

        ToolRegistry::add([
            'name' => 'wc_update_stock',
            'description' => 'Update WooCommerce product stock quantity.',
            'capability' => 'manage_woocommerce',
            'scope' => 'woo:write',
            'access' => 'write',
            'domain' => 'woo',
            'confirmation_required' => true,
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'product_id' => ['type' => 'integer'],
                    'stock_quantity' => ['type' => 'integer'],
                ],
                'required' => ['product_id', 'stock_quantity'],
            ],
            'handler' => function (array $args): array {
                if (!function_exists('wc_get_product')) {
                    throw new \RuntimeException('WooCommerce is not active.');
                }

                $productId = absint($args['product_id']);
                $stock = absint($args['stock_quantity']);

                $product = wc_get_product($productId);

                if (!$product) {
                    throw new \RuntimeException('Product not found.');
                }

                $product->set_stock_quantity($stock);
                $product->save();

                return [
                    'product_id' => $productId,
                    'stock_quantity' => $stock,
                    'updated_at' => current_time('c'),
                ];
            },
        ]);

        ToolRegistry::add([
            'name' => 'wc_create_product',
            'description' => 'Create a new WooCommerce product with full details.',
            'capability' => 'manage_woocommerce',
            'scope' => 'woo:write',
            'access' => 'write',
            'domain' => 'woo',
            'confirmation_required' => true,
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'description' => 'Product name'],
                    'description' => ['type' => 'string'],
                    'short_description' => ['type' => 'string'],
                    'regular_price' => ['type' => 'string'],
                    'sale_price' => ['type' => 'string'],
                    'sku' => ['type' => 'string'],
                    'stock_quantity' => ['type' => 'integer'],
                    'status' => [
                        'type' => 'string',
                        'enum' => ['draft', 'pending', 'publish'],
                        'default' => 'publish',
                    ],
                    'categories' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                    'tags' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                    'weight' => ['type' => 'string'],
                    'image_url' => ['type' => 'string'],
                    'meta_data' => [
                        'type' => 'object',
                        'description' => 'Custom meta data key-value pairs',
                    ],
                ],
                'required' => ['name', 'regular_price'],
            ],
            'handler' => function (array $args): array {
                if (!function_exists('wc_get_product')) {
                    throw new \RuntimeException('WooCommerce is not active.');
                }

                // Jieun produk anyar
                $product = new \WC_Product_Simple();
                $product->set_name(sanitize_text_field($args['name']));
                
                if (!empty($args['description'])) {
                    $product->set_description(wp_kses_post($args['description']));
                }
                
                if (!empty($args['short_description'])) {
                    $product->set_short_description(wp_kses_post($args['short_description']));
                }
                
                $product->set_regular_price((string) $args['regular_price']);
                
                if (!empty($args['sale_price'])) {
                    $product->set_sale_price((string) $args['sale_price']);
                }
                
                if (!empty($args['sku'])) {
                    $product->set_sku(sanitize_text_field($args['sku']));
                }
                
                if (isset($args['stock_quantity'])) {
                    $product->set_stock_quantity(absint($args['stock_quantity']));
                    $product->set_manage_stock(true);
                }
                
                $status = $args['status'] ?? 'publish';
                $product->set_status($status);
                
                if (!empty($args['weight'])) {
                    $product->set_weight(sanitize_text_field($args['weight']));
                }
                
                // Simpen heula pikeun meunangkeun ID
                $productId = $product->save();
                
                if (!$productId) {
                    throw new \RuntimeException('Failed to create product.');
                }

                // Atur kategori
                if (!empty($args['categories'])) {
                    $categoryIds = [];
                    foreach ($args['categories'] as $categoryName) {
                        $term = term_exists($categoryName, 'product_cat');
                        if (!$term) {
                            $term = wp_insert_term($categoryName, 'product_cat');
                        }
                        if (!is_wp_error($term)) {
                            $categoryIds[] = (int) ($term['term_id'] ?? $term);
                        }
                    }
                    if (!empty($categoryIds)) {
                        wp_set_object_terms($productId, $categoryIds, 'product_cat');
                    }
                }

                // Atur tags
                if (!empty($args['tags'])) {
                    $tagIds = [];
                    foreach ($args['tags'] as $tagName) {
                        $term = term_exists($tagName, 'product_tag');
                        if (!$term) {
                            $term = wp_insert_term($tagName, 'product_tag');
                        }
                        if (!is_wp_error($term)) {
                            $tagIds[] = (int) ($term['term_id'] ?? $term);
                        }
                    }
                    if (!empty($tagIds)) {
                        wp_set_object_terms($productId, $tagIds, 'product_tag');
                    }
                }

                // Simpen meta data custom
                if (!empty($args['meta_data']) && is_array($args['meta_data'])) {
                    foreach ($args['meta_data'] as $key => $value) {
                        update_post_meta($productId, sanitize_key($key), sanitize_text_field($value));
                    }
                }

                // Set featured image (upami aya URL)
                if (!empty($args['image_url'])) {
                    // Note: Ieu butuh media_sideload_image, pikeun samentawis simpen URL di meta
                    update_post_meta($productId, '_musespark_pending_image', esc_url_raw($args['image_url']));
                }

                return [
                    'product_id' => $productId,
                    'name' => $args['name'],
                    'sku' => $args['sku'] ?? null,
                    'regular_price' => $args['regular_price'],
                    'sale_price' => $args['sale_price'] ?? null,
                    'stock_quantity' => $args['stock_quantity'] ?? null,
                    'status' => $status,
                    'permalink' => get_permalink($productId),
                    'categories' => $args['categories'] ?? [],
                    'tags' => $args['tags'] ?? [],
                    'created_at' => current_time('c'),
                ];
            },
        ]);
    }
}
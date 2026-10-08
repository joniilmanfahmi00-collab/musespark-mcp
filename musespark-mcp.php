<?php
/**
 * Plugin Name: MuseSpark MCP Bridge
 * Description: MCP bridge for WordPress, WooCommerce, Yoast SEO, CRM, newsletter, and business automation.
 * Version: 0.1.0
 * Requires PHP: 8.2
 * Author: Aa Ilman
 * Text Domain: musespark-mcp
 */

declare(strict_types=1);

namespace MuseSparkMCP;

use MuseSparkMCP\Support\JwtService;

defined('ABSPATH') || exit;

define('MUSESPARK_MCP_VERSION', '0.1.0');
define('MUSESPARK_MCP_FILE', __FILE__);
define('MUSESPARK_MCP_PATH', plugin_dir_path(__FILE__));
define('MUSESPARK_MCP_URL', plugin_dir_url(__FILE__));

$autoload = MUSESPARK_MCP_PATH . 'vendor/autoload.php';

if (file_exists($autoload)) {
    require_once $autoload;
}

add_action('before_woocommerce_init', function (): void {
    if (class_exists('Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            MUSESPARK_MCP_FILE,
            true
        );
    }
});

register_activation_hook(__FILE__, [Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [Deactivator::class, 'deactivate']);

add_action('plugins_loaded', function (): void {
    if (!class_exists('Firebase\JWT\JWT')) {
        add_action('admin_notices', function (): void {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('MuseSpark MCP requires composer autoload. Please run: composer install --no-dev -o', 'musespark-mcp');
            echo '</p></div>';
        });

        return;
    }

    (new Plugin())->boot();
});

if (defined('WP_CLI') && constant('WP_CLI')) {
    add_action('plugins_loaded', function (): void {
        \WP_CLI::add_command('musespark generate-token', function ($args, $assoc): void {
            $userId = absint($assoc['user_id'] ?? 0);
            $ttl = absint($assoc['ttl'] ?? 900);
            $scopes = $assoc['scopes'] ?? '*';

            if (!$userId) {
                \WP_CLI::error('Parameter --user_id wajib diisi.');
            }

            $scopesArray = array_filter(array_map('trim', explode(',', $scopes)));

            try {
                $token = JwtService::issue($userId, $scopesArray, $ttl);
                \WP_CLI::success($token);
            } catch (\Throwable $e) {
                \WP_CLI::error($e->getMessage());
            }
        }, [
            'shortdesc' => 'Generate JWT token for MuseSpark MCP.',
            'synopsis' => [
                [
                    'type' => 'assoc',
                    'name' => 'user_id',
                    'optional' => false,
                ],
                [
                    'type' => 'assoc',
                    'name' => 'ttl',
                    'optional' => true,
                    'default' => 900,
                ],
                [
                    'type' => 'assoc',
                    'name' => 'scopes',
                    'optional' => true,
                    'default' => '*',
                ],
            ],
        ]);
    }, 20);
}
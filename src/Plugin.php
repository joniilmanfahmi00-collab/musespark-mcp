<?php

declare(strict_types=1);

namespace MuseSparkMCP;

use MuseSparkMCP\Admin\Menu;
use MuseSparkMCP\Api\AdminApi;
use MuseSparkMCP\Api\McpEndpoint;
use MuseSparkMCP\Api\McpServer;
use MuseSparkMCP\Api\OAuthServer;
use MuseSparkMCP\Tools\NewsletterTools;
use MuseSparkMCP\Tools\PostTools;
use MuseSparkMCP\Tools\ReportTools;
use MuseSparkMCP\Tools\SeoTools;
use MuseSparkMCP\Tools\WooTools;

class Plugin
{
    public function boot(): void
    {
        $oauth = new OAuthServer();

        add_action('init', [$this, 'loadTextdomain']);
        add_action('rest_api_init', [$this, 'registerRoutes']);
        add_action('admin_menu', [Menu::class, 'register']);
        add_action('admin_post_musespark_mcp_dashboard', [Menu::class, 'renderStandalone']);

        // Query var pikeun authorization endpoint (consent screen)
        add_filter('query_vars', function (array $vars): array {
            $vars[] = 'musespark_oauth';
            return $vars;
        });

        // Well-known metadata di root situs (RFC 8414 / RFC 9728)
        add_action('template_redirect', [$oauth, 'maybeHandleWellKnown'], 0);

        // Authorization endpoint (login + consent screen)
        add_action('template_redirect', [$oauth, 'maybeHandleAuthorize']);

        // Tambahkeun header WWW-Authenticate dina 401 di /mcp
        add_filter('rest_post_dispatch', [$oauth, 'addWwwAuthenticate'], 10, 3);

        $this->registerTools();
    }

    public function loadTextdomain(): void
    {
        load_plugin_textdomain(
            'musespark-mcp',
            false,
            dirname(plugin_basename(MUSESPARK_MCP_FILE)) . '/languages'
        );
    }

    public function registerRoutes(): void
    {
        (new McpEndpoint())->register();   // /rpc (legacy, dashboard urang)
        (new McpServer())->register();     // /mcp (MCP standar)
        (new OAuthServer())->register();   // /oauth/* (OAuth 2.1)
        (new AdminApi())->register();      // /admin/* (dashboard Vue)
    }

    private function registerTools(): void
    {
        PostTools::register();
        SeoTools::register();
        WooTools::register();
        NewsletterTools::register();
        ReportTools::register();
    }
}
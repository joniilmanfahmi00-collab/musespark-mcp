<?php

declare(strict_types=1);

namespace MuseSparkMCP\Admin;

class Menu
{
    public static function register(): void
    {
        add_menu_page(
            'MuseSpark MCP',
            'MuseSpark MCP',
            'manage_options',
            'musespark-mcp',
            [self::class, 'render'],
            'dashicons-smiley',
            80
        );

    }

    public static function render(): void
    {
        $src = esc_url(admin_url('admin-post.php?action=musespark_mcp_dashboard'));
        ?>
        <style>
            body.toplevel_page_musespark-mcp #wpcontent { padding-left: 0; }
            body.toplevel_page_musespark-mcp #wpbody-content { padding-bottom: 0; }
            body.toplevel_page_musespark-mcp #wpfooter { display: none; }
            #musespark-mcp-dashboard {
                display: block;
                width: 100%;
                border: 0;
                background: #070a12; /* sarua jeung bg shell, ngarah teu aya kedip bodas */
            }
        </style>
        <iframe
            id="musespark-mcp-dashboard"
            src="<?php echo $src; ?>"
            title="MuseSpark MCP Dashboard"
        ></iframe>
        <script>
        (() => {
            const frame = document.getElementById('musespark-mcp-dashboard');
            if (!frame) return;

            const origin = window.location.origin;
            const adminBarHeight = () => document.getElementById('wpadminbar')?.offsetHeight ?? 0;
            const floor = () => Math.max(window.innerHeight - adminBarHeight(), 480);

            const applyFloor = () => {
                const f = floor();
                frame.style.minHeight = f + 'px';
                frame.contentWindow?.postMessage({ type: 'musespark-mcp:request-height', floor: f }, origin);
            };

            window.addEventListener('message', (event) => {
                if (
                    event.source !== frame.contentWindow ||
                    event.origin !== origin ||
                    event.data?.type !== 'musespark-mcp:resize' ||
                    !Number.isFinite(event.data.height)
                ) return;

                frame.style.height = Math.max(Math.ceil(event.data.height), floor()) + 'px';
            });

            window.addEventListener('resize', applyFloor);
            frame.addEventListener('load', applyFloor);
            applyFloor();
        })();
        </script>
        <?php
    }

    public static function renderStandalone(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to access this page.', 'musespark-mcp'));
        }

        self::renderStandalonePage();
    }

    private static function renderStandalonePage(): void
    {
        $manifestPath = MUSESPARK_MCP_PATH . 'dist/.vite/manifest.json';

        if (!file_exists($manifestPath)) {
            wp_die(esc_html__('MuseSpark MCP: build the admin UI before opening this page.', 'musespark-mcp'));
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        $entry = is_array($manifest) ? ($manifest['index.html'] ?? null) : null;

        if (!is_array($entry) || empty($entry['file'])) {
            wp_die(esc_html__('MuseSpark MCP: the admin UI build manifest is invalid.', 'musespark-mcp'));
        }

        echo '<!DOCTYPE html><html lang="en"><head>';
        echo '<meta charset="UTF-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>MuseSpark MCP</title>';

        foreach (($entry['css'] ?? []) as $css) {
            echo '<link rel="stylesheet" href="' . esc_url(MUSESPARK_MCP_URL . 'dist/' . $css) . '">';
        }

        echo '<style>body{margin:0;padding:0}</style>';
        echo '</head><body class="musespark-mcp-embedded"><div id="app"></div>';
        echo '<script>window.MusesparkMCP=' . wp_json_encode([
            'restUrl' => esc_url_raw(rest_url('musespark-mcp/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'adminUrl' => admin_url(),
            'assetBaseUrl' => MUSESPARK_MCP_URL . 'dist/',
        ]) . ';</script>';
        echo '<script type="module" src="' . esc_url(MUSESPARK_MCP_URL . 'dist/' . $entry['file']) . '"></script>';
        echo '</body></html>';
        exit;
    }
}
<?php

declare(strict_types=1);

namespace MuseSparkMCP\Tools;

use MuseSparkMCP\Mcp\ToolRegistry;
use MuseSparkMCP\Support\Policy;

class SeoTools
{
    public static function register(): void
    {
        ToolRegistry::add([
            'name' => 'seo_set_post_meta',
            'description' => 'Set Yoast SEO meta title and meta description for a post.',
            'capability' => 'edit_posts',
            'scope' => 'seo:write',
            'access' => 'write',
            'domain' => 'seo',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'post_id' => ['type' => 'integer'],
                    'meta_title' => ['type' => 'string'],
                    'meta_description' => ['type' => 'string'],
                ],
                'required' => ['post_id'],
            ],
            'handler' => function (array $args): array {
                $postId = absint($args['post_id']);
                $post = get_post($postId);

                if (!$post) {
                    throw new \RuntimeException('Post not found.');
                }

                if (Policy::mode() === 'draft_only' && $post->post_status !== 'draft') {
                    throw new \RuntimeException('Draft-only mode only allows SEO editing for draft posts.');
                }

                $updated = [];

                if (isset($args['meta_title'])) {
                    $title = sanitize_text_field($args['meta_title']);

                    if (defined('WPSEO_VERSION')) {
                        update_post_meta($postId, '_yoast_wpseo_title', $title);
                    } else {
                        update_post_meta($postId, '_musespark_seo_title', $title);
                    }

                    $updated['meta_title'] = $title;
                }

                if (isset($args['meta_description'])) {
                    $description = sanitize_textarea_field($args['meta_description']);

                    if (defined('WPSEO_VERSION')) {
                        update_post_meta($postId, '_yoast_wpseo_metadesc', $description);
                    } else {
                        update_post_meta($postId, '_musespark_seo_description', $description);
                    }

                    $updated['meta_description'] = $description;
                }

                return [
                    'post_id' => $postId,
                    'updated' => $updated,
                    'seo_plugin' => defined('WPSEO_VERSION') ? 'yoast' : 'fallback',
                ];
            },
        ]);
    }
}
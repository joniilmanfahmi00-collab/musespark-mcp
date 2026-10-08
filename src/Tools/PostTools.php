<?php

declare(strict_types=1);

namespace MuseSparkMCP\Tools;

use MuseSparkMCP\Mcp\ToolRegistry;

class PostTools
{
    public static function register(): void
    {
        ToolRegistry::add([
            'name' => 'wp_list_posts',
            'description' => 'List WordPress posts.',
            'capability' => 'read',
            'scope' => 'content:read',
            'access' => 'read',
            'domain' => 'content',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'per_page' => ['type' => 'integer'],
                    'page' => ['type' => 'integer'],
                ],
            ],
            'handler' => function (array $args): array {
                $perPage = min(50, absint($args['per_page'] ?? 10));
                $page = max(1, absint($args['page'] ?? 1));

                $query = new \WP_Query([
                    'post_type' => 'post',
                    'posts_per_page' => $perPage,
                    'paged' => $page,
                    'post_status' => ['draft', 'pending', 'publish'],
                    'orderby' => 'date',
                    'order' => 'DESC',
                ]);

                $posts = [];

                foreach ($query->posts as $post) {
                    $posts[] = [
                        'id' => $post->ID,
                        'title' => get_the_title($post),
                        'status' => $post->post_status,
                        'date' => $post->post_date,
                        'permalink' => get_permalink($post),
                    ];
                }

                return [
                    'total' => (int) $query->found_posts,
                    'page' => $page,
                    'per_page' => $perPage,
                    'posts' => $posts,
                ];
            },
        ]);

        ToolRegistry::add([
            'name' => 'wp_create_post',
            'description' => 'Create a WordPress post. In draft-only mode, status is forced to draft.',
            'capability' => 'edit_posts',
            'scope' => 'content:write',
            'access' => 'write',
            'domain' => 'content',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                    'content' => ['type' => 'string'],
                    'excerpt' => ['type' => 'string'],
                    'status' => [
                        'type' => 'string',
                        'enum' => ['draft', 'pending', 'publish'],
                    ],
                ],
                'required' => ['title', 'content'],
            ],
            'handler' => function (array $args): array {
                $postId = wp_insert_post([
                    'post_title' => sanitize_text_field($args['title']),
                    'post_content' => wp_kses_post($args['content']),
                    'post_excerpt' => sanitize_textarea_field($args['excerpt'] ?? ''),
                    'post_status' => $args['status'] ?? 'draft',
                    'post_type' => 'post',
                ], true);

                if (is_wp_error($postId)) {
                    throw new \RuntimeException($postId->get_error_message());
                }

                return [
                    'post_id' => $postId,
                    'status' => get_post_status($postId),
                    'permalink' => get_permalink($postId),
                ];
            },
        ]);
    }
}
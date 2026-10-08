<?php

declare(strict_types=1);

namespace MuseSparkMCP\Tools;

use MuseSparkMCP\Mcp\ToolRegistry;
use MuseSparkMCP\Newsletter\TheNewsletterPluginProvider;

class NewsletterTools
{
    public static function register(): void
    {
        ToolRegistry::add([
            'name' => 'newsletter_add_subscriber',
            'description' => 'Add a subscriber using The Newsletter Plugin.',
            'capability' => 'manage_options',
            'scope' => 'newsletter:write',
            'access' => 'write',
            'domain' => 'newsletter',
            'confirmation_required' => true,
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'email' => ['type' => 'string'],
                    'name' => ['type' => 'string'],
                ],
                'required' => ['email'],
            ],
            'handler' => function (array $args): array {
                return TheNewsletterPluginProvider::addSubscriber($args);
            },
        ]);
    }
}
<?php

declare(strict_types=1);

namespace MuseSparkMCP\Mcp;

use MuseSparkMCP\Api\Auth;
use MuseSparkMCP\Models\TaskRepository;
use MuseSparkMCP\Support\Policy;

class ToolRegistry
{
    private static array $tools = [];

    public static function add(array $tool): void
    {
        if (empty($tool['name']) || empty($tool['handler'])) {
            throw new \InvalidArgumentException('Tool must have name and handler.');
        }

        $defaults = [
            'description' => '',
            'capability' => 'read',
            'scope' => 'read',
            'access' => 'read',
            'domain' => 'general',
            'confirmation_required' => false,
            'inputSchema' => [],
        ];

        self::$tools[$tool['name']] = array_merge($defaults, $tool);
    }

    public static function all(): array
    {
        return array_values(array_map(function (array $tool): array {
            return [
                'name' => $tool['name'],
                'description' => $tool['description'],
                'access' => $tool['access'],
                'domain' => $tool['domain'],
                'inputSchema' => self::normalizeSchema($tool['inputSchema']),
            ];
        }, self::$tools));
    }

    public static function allMcp(): array
    {
        return array_values(array_map(function (array $tool): array {
            return [
                'name' => $tool['name'],
                'title' => $tool['description'],
                'description' => $tool['description'],
                'inputSchema' => self::normalizeSchema($tool['inputSchema']),
                'annotations' => [
                    'readOnlyHint' => ($tool['access'] === 'read'),
                    'destructiveHint' => false,
                    'openWorldHint' => false,
                ],
            ];
        }, self::$tools));
    }

    /**
     * Normalisasi JSON Schema: pastikan properties nyaéta object, sanés array kosong
     */
    private static function normalizeSchema(array $schema): array
    {
        if (isset($schema['properties'])
            && is_array($schema['properties'])
            && $schema['properties'] === []) {
            $schema['properties'] = new \stdClass();
        }

        return $schema;
    }

    public static function call(string $name, array $arguments, bool $approved = false): mixed
    {
        if (!isset(self::$tools[$name])) {
            throw new \RuntimeException("Tool {$name} is not registered.");
        }

        $tool = self::$tools[$name];

        if (!current_user_can($tool['capability'])) {
            throw new \RuntimeException('Permission denied.');
        }

        if (!$approved && !Auth::hasScope($tool['scope'])) {
            throw new \RuntimeException('Token does not have required scope.');
        }

        if ($tool['access'] === 'write') {
            if (!Policy::writeAllowed($tool)) {
                throw new \RuntimeException('Write action is disabled by current policy mode.');
            }

            if (!$approved && Policy::needsConfirmation($tool)) {
                $taskId = TaskRepository::create($name, $arguments, get_current_user_id());

                return [
                    'status' => 'pending_confirmation',
                    'task_id' => $taskId,
                    'message' => 'This action requires admin approval.',
                ];
            }
        }

        if ($name === 'wp_create_post' && Policy::forceDraft()) {
            $arguments['status'] = 'draft';
        }

        return call_user_func($tool['handler'], $arguments);
    }
}
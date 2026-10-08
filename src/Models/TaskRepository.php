<?php

declare(strict_types=1);

namespace MuseSparkMCP\Models;

use MuseSparkMCP\Mcp\ToolRegistry;

class TaskRepository
{
    public static function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'musespark_mcp_tasks';
    }

    public static function create(string $tool, array $payload, int $userId): int
    {
        global $wpdb;

        $scopes = \MuseSparkMCP\Api\Auth::$scopes;

        $result = $wpdb->insert(
            self::table(),
            [
                'tool' => $tool,
                'payload' => wp_json_encode($payload),
                'status' => 'pending',
                'requested_by' => $userId,
                'requested_scopes' => wp_json_encode($scopes),
                'approved_by' => null,
                'result' => null,
                'error' => null,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        // Cek upami INSERT gagal
        if ($result === false) {
            $dbError = $wpdb->last_error ?: 'Unknown database error';
            throw new \RuntimeException('Failed to create task: ' . $dbError);
        }

        $taskId = (int) $wpdb->insert_id;

        if ($taskId === 0) {
            throw new \RuntimeException('Task created but insert_id is 0 — data corruption detected.');
        }

        return $taskId;
    }

    public static function get(int $id): ?object
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . self::table() . " WHERE id = %d LIMIT 1",
                $id
            )
        );

        return $row ?: null;
    }

    public static function update(int $id, array $data): void
    {
        global $wpdb;

        $data['updated_at'] = current_time('mysql');

        $formats = [];

        foreach ($data as $key => $value) {
            if (in_array($key, ['id', 'requested_by', 'approved_by'], true)) {
                $formats[] = '%d';
            } else {
                $formats[] = '%s';
            }
        }

        $wpdb->update(
            self::table(),
            $data,
            ['id' => $id],
            $formats,
            ['%d']
        );
    }

    public static function approve(int $id): mixed
    {
        $task = self::get($id);

        if (!$task) {
            throw new \RuntimeException('Task not found.');
        }

        if ($task->status !== 'pending') {
            throw new \RuntimeException('Task is not pending.');
        }

        // Restore scope token ti task
        $scopes = json_decode($task->requested_scopes ?? '[]', true) ?: [];
        \MuseSparkMCP\Api\Auth::$scopes = $scopes;  // <-- TAMBAHAN

        self::update($id, [
            'status' => 'processing',
            'approved_by' => get_current_user_id(),
        ]);

        $payload = json_decode((string) $task->payload, true) ?: [];

        try {
            $result = ToolRegistry::call($task->tool, $payload, true);

            self::update($id, [
                'status' => 'completed',
                'result' => wp_json_encode($result),
            ]);

            return $result;
        } catch (\Throwable $e) {
            self::update($id, [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
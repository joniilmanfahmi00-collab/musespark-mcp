<?php

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('musespark_mcp_jwt_secret');
delete_option('musespark_mcp_mode');
delete_option('musespark_mcp_require_ssl');

global $wpdb;

$tables = [
    $wpdb->prefix . 'musespark_mcp_logs',
    $wpdb->prefix . 'musespark_mcp_tasks',
    $wpdb->prefix . 'musespark_mcp_revoked',
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}
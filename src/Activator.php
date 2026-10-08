<?php

declare(strict_types=1);

namespace MuseSparkMCP;

class Activator
{
    public static function activate(): void
    {
        self::createTables();
        self::createOptions();
        self::migrateExistingTasks();

        flush_rewrite_rules();
    }

    private static function createTables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $wpdb->get_charset_collate();

        $logsTable = $wpdb->prefix . 'musespark_mcp_logs';
        $tasksTable = $wpdb->prefix . 'musespark_mcp_tasks';
        $revokedTable = $wpdb->prefix . 'musespark_mcp_revoked';
        $tokensTable = $wpdb->prefix . 'musespark_mcp_tokens';
        $clientsTable = $wpdb->prefix . 'musespark_mcp_oauth_clients';
        $codesTable = $wpdb->prefix . 'musespark_mcp_oauth_codes';
        $oauthTokensTable = $wpdb->prefix . 'musespark_mcp_oauth_tokens';

        dbDelta("CREATE TABLE {$logsTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            jti VARCHAR(64) NULL,
            user_id BIGINT UNSIGNED NULL,
            method VARCHAR(100) NULL,
            tool VARCHAR(191) NULL,
            status VARCHAR(20) NULL,
            message TEXT NULL,
            ip VARCHAR(45) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY method (method),
            KEY tool (tool),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$tasksTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tool VARCHAR(191) NOT NULL,
            payload LONGTEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            requested_by BIGINT UNSIGNED NULL,
            requested_scopes TEXT NULL,
            approved_by BIGINT UNSIGNED NULL,
            result LONGTEXT NULL,
            error LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY tool (tool),
            KEY created_at (created_at)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$revokedTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            jti VARCHAR(64) NOT NULL,
            expired_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY jti (jti)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$tokensTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(191) NOT NULL,
            scopes TEXT NULL,
            expires_at DATETIME NULL,
            last_used_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$clientsTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id VARCHAR(191) NOT NULL,
            client_secret_hash CHAR(64) NULL,
            client_name VARCHAR(191) NOT NULL,
            redirect_uris LONGTEXT NOT NULL,
            token_endpoint_auth_method VARCHAR(64) NOT NULL DEFAULT 'none',
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY client_id (client_id)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$codesTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            code_hash CHAR(64) NOT NULL,
            client_id VARCHAR(191) NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            redirect_uri TEXT NOT NULL,
            scopes TEXT NULL,
            code_challenge VARCHAR(128) NOT NULL,
            expires_at DATETIME NOT NULL,
            used TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY code_hash (code_hash)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$oauthTokensTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            access_token_hash CHAR(64) NOT NULL,
            refresh_token_hash CHAR(64) NULL,
            client_id VARCHAR(191) NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            scopes TEXT NULL,
            access_expires_at DATETIME NOT NULL,
            refresh_expires_at DATETIME NULL,
            revoked TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY access_token_hash (access_token_hash),
            KEY refresh_token_hash (refresh_token_hash)
        ) {$charsetCollate};");
    }

    private static function createOptions(): void
    {
        if (!get_option('musespark_mcp_jwt_secret')) {
            add_option(
                'musespark_mcp_jwt_secret',
                wp_generate_password(64, true, true)
            );
        }

        if (!get_option('musespark_mcp_mode')) {
            add_option('musespark_mcp_mode', 'draft_only');
        }

        if (!get_option('musespark_mcp_require_ssl')) {
            add_option('musespark_mcp_require_ssl', '1');
        }
    }

    private static function migrateExistingTasks(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'musespark_mcp_tasks';

        $columnExists = $wpdb->get_var(
            $wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'requested_scopes')
        );

        if (!$columnExists) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN requested_scopes TEXT NULL AFTER requested_by");
            $wpdb->query("UPDATE {$table} SET requested_scopes = '[\"*\"]' WHERE requested_scopes IS NULL");
        }
    }
}
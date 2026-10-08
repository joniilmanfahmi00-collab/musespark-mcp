<?php

declare(strict_types=1);

namespace MuseSparkMCP\Support;

class Policy
{
    public static function mode(): string
    {
        $mode = get_option('musespark_mcp_mode', 'draft_only');

        if (!in_array($mode, ['read_only', 'draft_only', 'require_confirmation'], true)) {
            return 'draft_only';
        }

        return $mode;
    }

    public static function writeAllowed(array $tool): bool
    {
        $access = $tool['access'] ?? 'read';

        if ($access !== 'write') {
            return true;
        }

        $mode = self::mode();

        if ($mode === 'read_only') {
            return false;
        }

        if ($mode === 'draft_only') {
            $domain = $tool['domain'] ?? '';

            return in_array($domain, ['content', 'seo'], true);
        }

        if ($mode === 'require_confirmation') {
            return true;
        }

        return false;
    }

    public static function needsConfirmation(array $tool): bool
    {
        $access = $tool['access'] ?? 'read';

        if ($access !== 'write') {
            return false;
        }

        if (self::mode() === 'require_confirmation') {
            return true;
        }

        return !empty($tool['confirmation_required']);
    }

    public static function forceDraft(): bool
    {
        return self::mode() === 'draft_only';
    }

    public static function autonomyConfig(): array
    {
        $default = [
            'report'      => ['level' => 3],
            'chat'        => ['level' => 2, 'escalate_on' => ['refund', 'komplain', 'pengacara']],
            'content'     => ['level' => 2, 'force_draft' => true],
            'seo'         => ['level' => 2],
            'woo'         => ['level' => 2, 'rules' => [
                'wc_update_stock' => ['max_delta_pct' => 20, 'daily_cap' => 10],
                'wc_update_price' => ['level' => 1],   // override → approve
            ]],
            'newsletter'  => ['level' => 1],
        ];

        return get_option('musespark_mcp_autonomy', $default);
    }

    /**
     * Mutuskeun: 'auto' | 'confirm' | 'deny'
     */
    public static function decide(array $tool, array $args): string
    {
        $domain = $tool['domain'] ?? 'general';
        $config = self::autonomyConfig()[$domain] ?? ['level' => 1];
        $level = $config['level'];

        // Override per-tool
        if (isset($config['rules'][$tool['name']]['level'])) {
            $level = $config['rules'][$tool['name']]['level'];
        }

        if ($tool['access'] === 'read') return 'auto';

        return match (true) {
            $level >= 2 => self::checkGuardrails($tool, $args, $config) ? 'auto' : 'confirm',
            $level === 1 => 'confirm',
            default => 'deny',
        };
    }

    private static function checkGuardrails(array $tool, array $args, array $config): bool
    {
        $rules = $config['rules'][$tool['name']] ?? [];

        // Conto: delta stock teu boleh > 20%
        if (isset($rules['max_delta_pct']) && isset($args['product_id'])) {
            $product = function_exists('wc_get_product') ? wc_get_product((int) $args['product_id']) : null;
            if ($product && $product->get_stock_quantity() !== null) {
                $old = (int) $product->get_stock_quantity();
                $new = (int) ($args['stock_quantity'] ?? 0);
                if ($old > 0 && abs($new - $old) / $old * 100 > $rules['max_delta_pct']) {
                    return false; // anomali → confirm
                }
            }
        }

        return true;
    }
}
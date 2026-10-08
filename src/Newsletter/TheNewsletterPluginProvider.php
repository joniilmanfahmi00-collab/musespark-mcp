<?php

declare(strict_types=1);

namespace MuseSparkMCP\Newsletter;

class TheNewsletterPluginProvider
{
    public static function available(): bool
    {
        return class_exists('Newsletter');
    }

    public static function addSubscriber(array $data): array
    {
        if (!self::available()) {
            throw new \RuntimeException('The Newsletter Plugin is not active.');
        }

        $email = sanitize_email($data['email'] ?? '');
        $name = sanitize_text_field($data['name'] ?? '');

        if (!is_email($email)) {
            throw new \RuntimeException('Invalid email address.');
        }

        $newsletter = \Newsletter::instance();

        /**
         * Catatan:
         * Ieu adapter perlu dites deui dumasar versi The Newsletter Plugin.
         * Upami method API na béda, urang tinggal saluyukeun bagian ieu.
         */
        if (method_exists($newsletter, 'add_user')) {
            $result = $newsletter->add_user([
                'email' => $email,
                'name' => $name,
            ]);

            return [
                'provider' => 'the-newsletter-plugin',
                'email' => $email,
                'name' => $name,
                'result' => $result,
            ];
        }

        throw new \RuntimeException('The Newsletter Plugin API is not recognized. Adapter needs update.');
    }
}
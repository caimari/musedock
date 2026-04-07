<?php

namespace Shop\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;

class SettingsController
{
    private function checkPermission(): void
    {
        if (!userCan('settings.manage')) {
            flash('error', 'No tienes permiso para acceder a la configuración.');
            header('Location: /musedock/dashboard');
            exit;
        }
    }

    public function index()
    {
        $this->checkPermission();

        $settings = [
            'stripe_secret_key' => setting('shop_stripe_secret_key', ''),
            'stripe_publishable_key' => setting('shop_stripe_publishable_key', ''),
            'stripe_webhook_secret' => setting('shop_stripe_webhook_secret', ''),
            'default_currency' => setting('shop_default_currency', 'eur'),
            'default_tax_rate' => setting('shop_default_tax_rate', '21'),
            'order_email' => setting('shop_order_email', ''),
        ];

        echo View::renderModule('musedock-shop', 'superadmin.shop.settings.index', [
            'title' => __('shop.settings_page.title'),
            'shopSettings' => $settings,
        ]);
    }

    public function update()
    {
        $this->checkPermission();

        $fields = [
            'shop_stripe_secret_key',
            'shop_stripe_publishable_key',
            'shop_stripe_webhook_secret',
            'shop_default_currency',
            'shop_default_tax_rate',
            'shop_order_email',
        ];

        $pdo = Database::connect();

        foreach ($fields as $field) {
            $postKey = str_replace('shop_', '', $field);
            $value = $_POST[$postKey] ?? '';

            // Check if setting exists
            $stmt = $pdo->prepare("SELECT id FROM settings WHERE key = :key");
            $stmt->execute(['key' => $field]);

            if ($stmt->fetchColumn()) {
                $pdo->prepare("UPDATE settings SET value = :value WHERE key = :key")
                    ->execute(['value' => $value, 'key' => $field]);
            } else {
                $pdo->prepare("INSERT INTO settings (key, value, created_at) VALUES (:key, :value, NOW())")
                    ->execute(['key' => $field, 'value' => $value]);
            }
        }

        flash('success', __('shop.settings_page.saved'));
        header('Location: ' . route('shop.settings.index'));
        exit;
    }
}

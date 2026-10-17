<?php

namespace Screenart\Musedock\Services\Notifications;

use PDO;
use Screenart\Musedock\Database;
use Screenart\Musedock\Mail\Mailer;

/**
 * Fuente única de los avisos del CMS a la plataforma (Ajustes → Avisos).
 *
 * Cada parte del CMS (núcleo, módulos, plugins) declara sus tipos de aviso y los envía con send(); aquí se decide
 * a quién va, en qué idioma y si está silenciado:
 *  - Destinatarios: lista propia (email + idioma). Si está vacía, los superadmins con el idioma de su perfil.
 *  - Por tipo: activo/silenciado y, opcionalmente, solo algunos destinatarios.
 *  - Idioma: cada aviso trae su texto en español e inglés; cada destinatario lo recibe en el suyo (si falta, español).
 *
 * Uso desde un plugin o módulo:
 *   NotificationCenter::register('mi_plugin_x', ['es' => 'Nombre', 'en' => 'Name', 'desc_es' => '…', 'desc_en' => '…']);
 *   NotificationCenter::send('mi_plugin_x', ['es' => ['subject' => '…', 'html' => '…'], 'en' => ['subject' => '…', 'html' => '…']]);
 *
 * No es para correos a clientes o usuarios (activaciones, contraseñas…): solo avisos a quien administra la plataforma.
 */
class NotificationCenter
{
    public const LOCALES = ['es' => 'Español', 'en' => 'English'];

    /** Tipos del núcleo; los módulos y plugins añaden los suyos con register(). */
    private static array $types = [
        'edge_guard' => [
            'es' => 'Bloqueos de Cloudflare', 'en' => 'Cloudflare blocks', 'group' => 'Dominios',
            'desc_es' => 'Proxy quitado o restaurado por bloqueos de IPs de Cloudflare, y principio y fin de cada bloqueo.',
            'desc_en' => 'Proxy turned off or back on because of blocked Cloudflare IPs, and start and end of each block.',
        ],
        'domain_expiry' => [
            'es' => 'Caducidad de dominios', 'en' => 'Domain expiry', 'group' => 'Dominios',
            'desc_es' => 'Dominios de tus sitios a punto de caducar (60, 30, 15 y 7 días) o ya caducados.',
            'desc_en' => 'Domains of your sites about to expire (60, 30, 15 and 7 days) or already expired.',
        ],
        'seo_drops' => [
            'es' => 'Caídas de SEO', 'en' => 'SEO drops', 'group' => 'Search Console',
            'desc_es' => 'Resumen diario de caídas relevantes de clics o posiciones en Google.',
            'desc_en' => 'Daily summary of relevant drops in Google clicks or positions.',
        ],
        'security_bruteforce' => [
            'es' => 'Ataques de fuerza bruta', 'en' => 'Brute-force attacks', 'group' => 'Seguridad',
            'desc_es' => 'Muchos intentos fallidos de acceso contra una cuenta (se bloquea temporalmente).',
            'desc_en' => 'Many failed login attempts against an account (temporarily locked).',
        ],
    ];

    private static ?PDO $pdo = null;
    private static bool $ready = false;

    public static function register(string $key, array $def): void
    {
        self::$types[$key] = $def + ['group' => 'Otros', 'es' => $key, 'en' => $key, 'desc_es' => '', 'desc_en' => ''];
    }

    public static function types(): array
    {
        return self::$types;
    }

    private static function db(): PDO
    {
        self::$pdo ??= Database::connect();
        if (!self::$ready) {
            self::ensureTables(self::$pdo);
            self::$ready = true;
        }

        return self::$pdo;
    }

    private static function ensureTables(PDO $pdo): void
    {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $pk = $driver === 'pgsql' ? 'SERIAL PRIMARY KEY' : ($driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY');
        $small = $driver === 'pgsql' ? 'SMALLINT' : ($driver === 'sqlite' ? 'INTEGER' : 'TINYINT(1)');
        $ts = $driver === 'mysql' ? 'DATETIME' : 'TIMESTAMP';
        $pdo->exec("CREATE TABLE IF NOT EXISTS notification_recipients (id $pk, email VARCHAR(255) NOT NULL UNIQUE, name VARCHAR(255) NULL, locale VARCHAR(5) NOT NULL DEFAULT 'es', enabled $small NOT NULL DEFAULT 1, created_at $ts NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS notification_types (type_key VARCHAR(80) PRIMARY KEY, enabled $small NOT NULL DEFAULT 1, recipients TEXT NULL, updated_at $ts NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS notification_log (id $pk, type_key VARCHAR(80) NOT NULL, recipient VARCHAR(255) NOT NULL, locale VARCHAR(5) NOT NULL, subject VARCHAR(255) NOT NULL, ok $small NOT NULL, created_at $ts NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        try {
            $pdo->exec($driver === 'pgsql' ? 'ALTER TABLE super_admins ADD COLUMN IF NOT EXISTS locale VARCHAR(5) NULL' : 'ALTER TABLE super_admins ADD COLUMN locale VARCHAR(5) NULL');
        } catch (\Throwable $e) {
            // ya existe
        }
    }

    /**
     * Trae una vez los destinatarios que se guardaban en el antiguo campo «Enviar los avisos a» de Bloqueos Cloudflare
     * (la lista común arranca con ellos, en español) y vacía ese ajuste.
     */
    public static function importLegacy(): void
    {
        $db = self::db();
        try {
            $st = $db->prepare("SELECT v FROM edge_guard_settings WHERE k = 'notify_to'");
            $st->execute();
            $raw = (string) ($st->fetchColumn() ?: '');
        } catch (\Throwable $e) {
            return; // sin tabla del escudo
        }
        if (trim($raw) === '') {
            return;
        }
        foreach (preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                self::addRecipient($email, '', 'es');
            }
        }
        $db->prepare("DELETE FROM edge_guard_settings WHERE k = 'notify_to'")->execute();
    }

    // ------------------------------------------------------------------ configuración

    public static function recipients(): array
    {
        return self::db()->query('SELECT * FROM notification_recipients ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function addRecipient(string $email, string $name, string $locale): void
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Dirección de correo no válida.');
        }
        $locale = isset(self::LOCALES[$locale]) ? $locale : 'es';
        $st = self::db()->prepare('SELECT id FROM notification_recipients WHERE email = ?');
        $st->execute([$email]);
        if ($id = $st->fetchColumn()) {
            self::db()->prepare('UPDATE notification_recipients SET name = ?, locale = ?, enabled = 1 WHERE id = ?')->execute([trim($name) ?: null, $locale, $id]);

            return;
        }
        self::db()->prepare('INSERT INTO notification_recipients (email, name, locale, enabled) VALUES (?, ?, ?, 1)')->execute([$email, trim($name) ?: null, $locale]);
    }

    public static function updateRecipient(int $id, string $locale, bool $enabled): void
    {
        self::db()->prepare('UPDATE notification_recipients SET locale = ?, enabled = ? WHERE id = ?')
            ->execute([isset(self::LOCALES[$locale]) ? $locale : 'es', $enabled ? 1 : 0, $id]);
    }

    public static function deleteRecipient(int $id): void
    {
        self::db()->prepare('DELETE FROM notification_recipients WHERE id = ?')->execute([$id]);
    }

    /** @return array{enabled:bool, recipients:?int[]} recipients null = todos */
    public static function typeSettings(string $type): array
    {
        $st = self::db()->prepare('SELECT enabled, recipients FROM notification_types WHERE type_key = ?');
        $st->execute([$type]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) {
            return ['enabled' => true, 'recipients' => null];
        }
        $ids = $r['recipients'] === null || $r['recipients'] === '' ? null : array_map('intval', array_filter(explode(',', (string) $r['recipients'])));

        return ['enabled' => (bool) (int) $r['enabled'], 'recipients' => $ids];
    }

    /** @param int[]|null $recipients null = todos los destinatarios */
    public static function saveType(string $type, bool $enabled, ?array $recipients): void
    {
        $db = self::db();
        $db->prepare('DELETE FROM notification_types WHERE type_key = ?')->execute([$type]);
        $db->prepare('INSERT INTO notification_types (type_key, enabled, recipients, updated_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)')
            ->execute([$type, $enabled ? 1 : 0, $recipients === null ? null : implode(',', array_map('intval', $recipients))]);
    }

    public static function log(int $limit = 50): array
    {
        return self::db()->query('SELECT * FROM notification_log ORDER BY id DESC LIMIT ' . (int) $limit)->fetchAll(PDO::FETCH_ASSOC);
    }

    // ------------------------------------------------------------------ envío

    /**
     * A quién va un tipo de aviso ahora mismo.
     * @return array<int, array{email:string, locale:string}> vacío si el tipo está silenciado
     */
    public static function recipientsFor(string $type): array
    {
        $set = self::typeSettings($type);
        if (!$set['enabled']) {
            return [];
        }
        $all = array_values(array_filter(self::recipients(), fn ($r) => (int) $r['enabled'] === 1));
        if ($all) {
            if ($set['recipients'] !== null) {
                $all = array_values(array_filter($all, fn ($r) => in_array((int) $r['id'], $set['recipients'], true)));
            }

            return array_map(fn ($r) => ['email' => $r['email'], 'locale' => $r['locale'] ?: 'es'], $all);
        }
        // Sin destinatarios configurados: los superadmins, con el idioma de su perfil
        $out = [];
        foreach (self::db()->query("SELECT email, locale FROM super_admins WHERE email IS NOT NULL AND email <> ''")->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $out[] = ['email' => $a['email'], 'locale' => isset(self::LOCALES[$a['locale'] ?? '']) ? $a['locale'] : 'es'];
        }

        return $out;
    }

    /**
     * Envía un aviso. $messages: ['es' => ['subject' => …, 'html' => …, 'text' => …?], 'en' => […]] (con uno basta).
     * @return int correos enviados
     */
    public static function send(string $type, array $messages, string $fromName = 'MuseDock Avisos'): int
    {
        $sent = 0;
        $fallback = $messages['es'] ?? reset($messages);
        if (!$fallback) {
            return 0;
        }
        foreach (self::recipientsFor($type) as $r) {
            $m = $messages[$r['locale']] ?? $fallback;
            $locale = isset($messages[$r['locale']]) ? $r['locale'] : (isset($messages['es']) ? 'es' : (string) array_key_first($messages));
            $text = $m['text'] ?? trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '</p>', '</li>', '</h4>', '</tr>'], "\n", (string) $m['html'])), ENT_QUOTES, 'UTF-8'));
            ob_start(); // algunos transportes escriben el diálogo SMTP por pantalla
            try {
                $ok = Mailer::send($r['email'], (string) $m['subject'], (string) $m['html'], $text, null, $fromName);
            } catch (\Throwable $e) {
                $ok = false;
            }
            ob_end_clean();
            $sent += $ok ? 1 : 0;
            try {
                self::db()->prepare('INSERT INTO notification_log (type_key, recipient, locale, subject, ok) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$type, $r['email'], $locale, mb_substr((string) $m['subject'], 0, 250), $ok ? 1 : 0]);
            } catch (\Throwable $e) {
                // el registro nunca impide el aviso
            }
        }
        try {
            self::db()->prepare('DELETE FROM notification_log WHERE created_at < ?')->execute([gmdate('Y-m-d H:i:s', time() - 90 * 86400)]);
        } catch (\Throwable $e) {
        }

        return $sent;
    }

    /** Idioma de los avisos de un superadmin (perfil). */
    public static function superadminLocale(int $id): string
    {
        $st = self::db()->prepare('SELECT locale FROM super_admins WHERE id = ?');
        $st->execute([$id]);
        $l = (string) ($st->fetchColumn() ?: '');

        return isset(self::LOCALES[$l]) ? $l : 'es';
    }

    public static function setSuperadminLocale(int $id, string $locale): void
    {
        self::db()->prepare('UPDATE super_admins SET locale = ? WHERE id = ?')->execute([isset(self::LOCALES[$locale]) ? $locale : 'es', $id]);
    }
}

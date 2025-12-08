<?php

namespace Screenart\Musedock\Security;

use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;

/**
 * Web Application Firewall (WAF)
 *
 * Protección contra:
 * - SQL Injection
 * - XSS (Cross-Site Scripting)
 * - LFI/RFI (Local/Remote File Inclusion)
 * - Command Injection
 * - Path Traversal
 * - Protocol Attacks
 * - Bad Bots
 */
class WAF
{
    // Patrones maliciosos SQL Injection
    private const SQL_PATTERNS = [
        '/(\%27)|(\')|(\-\-)|(\%23)|(#)/i',
        '/((\%3D)|(=))[^\n]*((\%27)|(\')|(\-\-)|(\%3B)|(;))/i',
        '/\w*((\%27)|(\'))((\%6F)|o|(\%4F))((\%72)|r|(\%52))/i',
        '/((\%27)|(\'))union/i',
        '/exec(\s|\+)+(s|x)p\w+/i',
        '/UNION(\s+)SELECT/i',
        '/UNION(\s+)ALL(\s+)SELECT/i',
        '/INTO(\s+)OUTFILE/i',
        '/INTO(\s+)DUMPFILE/i',
        '/LOAD_FILE/i',
        '/BENCHMARK\s*\(/i',
        '/SLEEP\s*\(/i',
        '/WAITFOR(\s+)DELAY/i',
    ];

    // Patrones XSS
    private const XSS_PATTERNS = [
        '/<script\b[^>]*>(.*?)<\/script>/is',
        '/javascript\s*:/i',
        '/vbscript\s*:/i',
        '/on\w+\s*=\s*["\'][^"\']*["\']/i',
        '/expression\s*\([^)]*\)/i',
        '/<\s*img[^>]+\bonerror\s*=/i',
        '/<\s*svg[^>]+\bonload\s*=/i',
        '/<\s*body[^>]+\bonload\s*=/i',
        '/<\s*iframe/i',
        '/<\s*object/i',
        '/<\s*embed/i',
        '/<\s*link[^>]+\bhref\s*=\s*["\']javascript:/i',
    ];

    // Patrones de inclusión de archivos
    private const LFI_PATTERNS = [
        '/\.\.\//i',
        '/\.\.\\\/i',
        '/%2e%2e%2f/i',
        '/%2e%2e\//i',
        '/\.\.%2f/i',
        '/%2e%2e%5c/i',
        '/etc\/passwd/i',
        '/etc\/shadow/i',
        '/proc\/self/i',
        '/windows\/system32/i',
    ];

    // Patrones de inyección de comandos
    private const CMD_PATTERNS = [
        '/;\s*(ls|cat|wget|curl|bash|sh|nc|netcat)\b/i',
        '/\|\s*(ls|cat|wget|curl|bash|sh|nc|netcat)\b/i',
        '/`[^`]*`/',
        '/\$\([^)]*\)/',
        '/\$\{[^}]*\}/',
        '/\beval\s*\(/i',
        '/\bexec\s*\(/i',
        '/\bsystem\s*\(/i',
        '/\bpassthru\s*\(/i',
        '/\bshell_exec\s*\(/i',
        '/\bpopen\s*\(/i',
        '/\bproc_open\s*\(/i',
    ];

    // Patrones de protocolo sospechoso
    private const PROTOCOL_PATTERNS = [
        '/^(file|gopher|dict|php|data|glob|expect|phar):/i',
    ];

    // User agents de bots maliciosos
    private const BAD_BOTS = [
        'sqlmap',
        'nikto',
        'nessus',
        'nmap',
        'masscan',
        'dirbuster',
        'gobuster',
        'wpscan',
        'acunetix',
        'netsparker',
        'burpsuite',
        'havij',
        'pangolin',
    ];

    private static bool $enabled = true;
    private static array $violations = [];

    /**
     * Ejecutar el WAF
     */
    public static function protect(): bool
    {
        if (!self::$enabled) {
            return true;
        }

        // Verificar User-Agent
        if (!self::checkUserAgent()) {
            self::block('Bad Bot Detected');
            return false;
        }

        // Verificar URI
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (!self::checkInput($uri, 'URI')) {
            self::block('Malicious URI');
            return false;
        }

        // Verificar Query String
        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        if (!self::checkInput($queryString, 'Query String')) {
            self::block('Malicious Query String');
            return false;
        }

        // Verificar POST data
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            foreach ($_POST as $key => $value) {
                if (is_string($value) && !self::checkInput($value, "POST[{$key}]")) {
                    self::block('Malicious POST Data');
                    return false;
                }
            }
        }

        // Verificar Headers
        if (!self::checkHeaders()) {
            self::block('Malicious Headers');
            return false;
        }

        // Verificar Cookies
        foreach ($_COOKIE as $key => $value) {
            if (is_string($value) && !self::checkInput($value, "Cookie[{$key}]")) {
                self::block('Malicious Cookie');
                return false;
            }
        }

        return true;
    }

    /**
     * Verificar un input contra todos los patrones
     */
    private static function checkInput(string $input, string $source): bool
    {
        if (empty($input)) {
            return true;
        }

        $decoded = urldecode($input);
        $decoded = html_entity_decode($decoded);

        // SQL Injection
        foreach (self::SQL_PATTERNS as $pattern) {
            if (preg_match($pattern, $decoded)) {
                self::logViolation('SQL Injection', $source, $pattern);
                return false;
            }
        }

        // XSS
        foreach (self::XSS_PATTERNS as $pattern) {
            if (preg_match($pattern, $decoded)) {
                self::logViolation('XSS', $source, $pattern);
                return false;
            }
        }

        // LFI/RFI
        foreach (self::LFI_PATTERNS as $pattern) {
            if (preg_match($pattern, $decoded)) {
                self::logViolation('LFI/RFI', $source, $pattern);
                return false;
            }
        }

        // Command Injection
        foreach (self::CMD_PATTERNS as $pattern) {
            if (preg_match($pattern, $decoded)) {
                self::logViolation('Command Injection', $source, $pattern);
                return false;
            }
        }

        // Protocol attacks
        foreach (self::PROTOCOL_PATTERNS as $pattern) {
            if (preg_match($pattern, $decoded)) {
                self::logViolation('Protocol Attack', $source, $pattern);
                return false;
            }
        }

        return true;
    }

    /**
     * Verificar User-Agent
     */
    private static function checkUserAgent(): bool
    {
        $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');

        if (empty($ua)) {
            // User-Agent vacío es sospechoso pero no bloqueamos
            return true;
        }

        foreach (self::BAD_BOTS as $bot) {
            if (str_contains($ua, $bot)) {
                self::logViolation('Bad Bot', 'User-Agent', $bot);
                return false;
            }
        }

        return true;
    }

    /**
     * Verificar headers sospechosos
     */
    private static function checkHeaders(): bool
    {
        $headers = getallheaders();

        foreach ($headers as $name => $value) {
            // Skip algunos headers comunes
            if (in_array(strtolower($name), ['host', 'connection', 'accept', 'accept-language', 'accept-encoding'])) {
                continue;
            }

            if (!self::checkInput($value, "Header[{$name}]")) {
                return false;
            }
        }

        return true;
    }

    /**
     * Registrar violación de seguridad
     */
    private static function logViolation(string $type, string $source, string $pattern): void
    {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ip = trim(explode(',', $ip)[0]);

        $violation = [
            'type' => $type,
            'source' => $source,
            'pattern' => $pattern,
            'ip' => $ip,
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        self::$violations[] = $violation;

        // Loguear
        Logger::log("WAF BLOCK: {$type} from {$ip} in {$source}", 'WARNING', $violation);

        // Guardar en BD si existe tabla
        self::saveToDatabase($violation);
    }

    /**
     * Guardar violación en base de datos
     */
    private static function saveToDatabase(array $violation): void
    {
        try {
            $db = Database::connect();
            $stmt = $db->prepare("
                INSERT INTO waf_logs (type, source, ip_address, uri, method, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $violation['type'],
                $violation['source'],
                $violation['ip'],
                substr($violation['uri'], 0, 500),
                $violation['method'],
            ]);
        } catch (\Exception $e) {
            // Tabla puede no existir, ignorar
        }
    }

    /**
     * Bloquear request
     */
    private static function block(string $reason): void
    {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');

        // Log del bloqueo
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        Logger::log("WAF blocked request from {$ip}: {$reason}", 'WARNING');

        echo '<!DOCTYPE html><html><head><title>403 Forbidden</title></head>';
        echo '<body style="font-family:sans-serif;text-align:center;padding:50px;">';
        echo '<h1>403 Forbidden</h1>';
        echo '<p>Your request has been blocked by the security system.</p>';
        echo '<p>If you believe this is an error, please contact the administrator.</p>';
        echo '<p style="color:#999;font-size:12px;">Reference: ' . substr(md5(time() . $ip), 0, 8) . '</p>';
        echo '</body></html>';

        exit;
    }

    /**
     * Habilitar/deshabilitar WAF
     */
    public static function setEnabled(bool $enabled): void
    {
        self::$enabled = $enabled;
    }

    /**
     * Obtener violaciones detectadas
     */
    public static function getViolations(): array
    {
        return self::$violations;
    }

    /**
     * Añadir patrón personalizado
     */
    public static function addPattern(string $type, string $pattern): void
    {
        // Implementar si se necesita extensibilidad
    }
}

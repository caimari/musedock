<?php
namespace Screenart\Musedock\Security;

use Screenart\Musedock\Logger;

class ThemeSecurityValidator
{
    /**
     * Funciones PHP prohibidas en temas
     */
    const FORBIDDEN_FUNCTIONS = [
        'eval', 'exec', 'system', 'shell_exec', 'passthru',
        'proc_open', 'popen', 'pcntl_exec', 'assert',
        'create_function', 'include', 'include_once',
        'require', 'require_once', // Solo permitidos en bootstrap.php controlado
        'file_get_contents', 'file_put_contents', 'fopen', 'fwrite',
        'unlink', 'rmdir', 'rename', 'copy', 'move_uploaded_file'
    ];

    /**
     * Patrones peligrosos en CSS
     */
    const DANGEROUS_CSS_PATTERNS = [
        '/<script/i',
        '/javascript:/i',
        '/expression\s*\(/i', // IE CSS expressions
        '/@import.*url\(/i',  // Imports externos
        '/behavior\s*:/i',    // IE behaviors
        '/-moz-binding/i',    // Firefox XBL
    ];

    /**
     * Patrones peligrosos en JavaScript
     */
    const DANGEROUS_JS_PATTERNS = [
        '/eval\s*\(/i',
        '/Function\s*\(/i',
        '/setTimeout\s*\(\s*["\']/',  // setTimeout con string
        '/setInterval\s*\(\s*["\']/', // setInterval con string
        '/document\.write/i',
        '/innerHTML\s*=/i',  // XSS risk
        '/outerHTML\s*=/i',
        '/\.location\s*=/i', // Redirect hijacking
        '/XMLHttpRequest/i', // Sin control
        '/fetch\s*\(/i',     // Sin control
    ];

    /**
     * Validar tema completo
     */
    public static function validate(string $themePath): array {
        $errors = [];
        $warnings = [];
        $score = 100;

        Logger::info("ThemeSecurityValidator: Validando tema en {$themePath}");

        // 1. Estructura básica
        if (!file_exists($themePath . '/theme.json')) {
            $errors[] = "Falta theme.json";
            return [
                'valid' => false,
                'critical' => true,
                'errors' => $errors,
                'warnings' => [],
                'security_score' => 0
            ];
        }

        // 2. Validar theme.json
        $metadata = json_decode(file_get_contents($themePath . '/theme.json'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errors[] = "theme.json inválido: " . json_last_error_msg();
            $score -= 20;
        }

        if (empty($metadata['name'])) {
            $errors[] = "Falta campo 'name' en theme.json";
            $score -= 10;
        }

        // 3. Validar archivos PHP
        $phpFiles = self::recursiveGlob($themePath, '*.php');
        foreach ($phpFiles as $file) {
            $result = self::validatePHPFile($file);
            $errors = array_merge($errors, $result['errors']);
            $warnings = array_merge($warnings, $result['warnings']);
            $score -= $result['penalty'];
        }

        // 4. Validar archivos CSS
        $cssFiles = self::recursiveGlob($themePath, '*.css');
        foreach ($cssFiles as $file) {
            $result = self::validateCSSFile($file);
            $errors = array_merge($errors, $result['errors']);
            $warnings = array_merge($warnings, $result['warnings']);
            $score -= $result['penalty'];
        }

        // 5. Validar archivos JavaScript
        $jsFiles = self::recursiveGlob($themePath, '*.js');
        foreach ($jsFiles as $file) {
            $result = self::validateJSFile($file);
            $errors = array_merge($errors, $result['errors']);
            $warnings = array_merge($warnings, $result['warnings']);
            $score -= $result['penalty'];
        }

        // 6. Validar permisos de archivos
        $result = self::validatePermissions($themePath);
        $warnings = array_merge($warnings, $result['warnings']);

        $score = max(0, min(100, $score));
        $hasCriticalErrors = !empty(array_filter($errors, fn($e) => strpos($e, 'CRÍTICO') !== false));

        return [
            'valid' => empty($errors) || ($score >= 60 && !$hasCriticalErrors),
            'critical' => $hasCriticalErrors,
            'errors' => $errors,
            'warnings' => $warnings,
            'security_score' => $score
        ];
    }

    /**
     * Validar archivo PHP
     */
    private static function validatePHPFile(string $file): array {
        $errors = [];
        $warnings = [];
        $penalty = 0;

        $content = file_get_contents($file);
        $basename = basename($file);

        // Detectar funciones prohibidas
        foreach (self::FORBIDDEN_FUNCTIONS as $func) {
            if (preg_match('/\b' . preg_quote($func, '/') . '\s*\(/i', $content)) {
                $errors[] = "CRÍTICO: Función prohibida '{$func}' en {$basename}";
                $penalty += 50; // Penalización severa
            }
        }

        // Detectar acceso a variables superglobales sin sanitizar
        if (preg_match('/\$_(GET|POST|REQUEST|COOKIE|SERVER)\[/i', $content)) {
            if (!preg_match('/(htmlspecialchars|filter_var|filter_input|SafeHtml::clean)/i', $content)) {
                $warnings[] = "Variables superglobales sin sanitizar en {$basename}";
                $penalty += 10;
            }
        }

        // Detectar SQL directo (debería usar QueryBuilder)
        if (preg_match('/(mysqli_query|mysql_query|PDO::query|->query\()/i', $content)) {
            $errors[] = "Query SQL directo detectado en {$basename} (usar QueryBuilder)";
            $penalty += 20;
        }

        // Detectar include/require dinámicos
        if (preg_match('/(include|require).*\$/', $content)) {
            $errors[] = "CRÍTICO: Include/require dinámico en {$basename}";
            $penalty += 40;
        }

        return compact('errors', 'warnings', 'penalty');
    }

    /**
     * Validar archivo CSS
     */
    private static function validateCSSFile(string $file): array {
        $errors = [];
        $warnings = [];
        $penalty = 0;

        $content = file_get_contents($file);
        $basename = basename($file);

        foreach (self::DANGEROUS_CSS_PATTERNS as $pattern) {
            if (preg_match($pattern, $content)) {
                $errors[] = "Patrón peligroso en CSS: {$basename}";
                $penalty += 30;
            }
        }

        // Detectar URLs externas (warning, no error)
        if (preg_match_all('/url\s*\(\s*["\']?(https?:\/\/[^)]+)/i', $content, $matches)) {
            $warnings[] = "URLs externas en {$basename}: " . implode(', ', array_unique($matches[1]));
            $penalty += 5;
        }

        return compact('errors', 'warnings', 'penalty');
    }

    /**
     * Validar archivo JavaScript
     */
    private static function validateJSFile(string $file): array {
        $errors = [];
        $warnings = [];
        $penalty = 0;

        $content = file_get_contents($file);
        $basename = basename($file);

        foreach (self::DANGEROUS_JS_PATTERNS as $pattern) {
            if (preg_match($pattern, $content)) {
                $errors[] = "Patrón peligroso en JS: {$basename}";
                $penalty += 25;
            }
        }

        // Detectar localStorage/sessionStorage (warning)
        if (preg_match('/(localStorage|sessionStorage)\./i', $content)) {
            $warnings[] = "Uso de localStorage en {$basename} (revisar datos sensibles)";
            $penalty += 5;
        }

        return compact('errors', 'warnings', 'penalty');
    }

    /**
     * Validar permisos de archivos
     */
    private static function validatePermissions(string $themePath): array {
        $warnings = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($themePath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            $perms = fileperms($file->getPathname());

            // Archivos no deben ser ejecutables
            if ($perms & 0111) {
                $warnings[] = "Archivo ejecutable detectado: " . $file->getFilename();
            }

            // Archivos no deben ser escribibles por otros
            if ($perms & 0002) {
                $warnings[] = "Archivo escribible por todos: " . $file->getFilename();
            }
        }

        return compact('warnings');
    }

    /**
     * Helper: Buscar archivos recursivamente con patrón
     */
    private static function recursiveGlob(string $directory, string $pattern): array {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && fnmatch($pattern, $file->getFilename())) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}

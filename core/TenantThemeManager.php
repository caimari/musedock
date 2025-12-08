<?php
namespace Screenart\Musedock;

use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;
use Screenart\Musedock\Security\ThemeSecurityValidator;

class TenantThemeManager
{
    /**
     * Directorio base de temas por tenant
     */
    public static function getThemesPath(int $tenantId): string {
        return APP_ROOT . "/storage/tenants/{$tenantId}/themes";
    }

    /**
     * Obtener ruta del tema activo (global o custom)
     */
    public static function getActiveThemePath(int $tenantId): string {
        $tenant = Database::query(
            "SELECT theme_type, custom_theme_slug, theme FROM tenants WHERE id = :id",
            ['id' => $tenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        if ($tenant['theme_type'] === 'custom' && $tenant['custom_theme_slug']) {
            // Tema personalizado del tenant
            $customPath = self::getThemesPath($tenantId) . '/' . $tenant['custom_theme_slug'];

            if (is_dir($customPath)) {
                return $customPath;
            }

            Logger::warning("TenantThemeManager: Tema custom no encontrado, usando fallback", [
                'tenant_id' => $tenantId,
                'custom_slug' => $tenant['custom_theme_slug']
            ]);
        }

        // Tema global (del sistema)
        $globalSlug = $tenant['theme'] ?? 'default';
        return APP_ROOT . "/themes/{$globalSlug}";
    }

    /**
     * Sincronizar temas del tenant desde disco
     */
    public static function syncTenantThemes(int $tenantId): array {
        $themesDir = self::getThemesPath($tenantId);
        $db = Database::connect();

        Logger::info("TenantThemeManager: Sincronizando temas del tenant {$tenantId}");

        $results = [
            'registered' => [],
            'updated' => [],
            'errors' => [],
            'validation_warnings' => []
        ];

        // Crear directorio si no existe
        if (!is_dir($themesDir)) {
            mkdir($themesDir, 0755, true);
            Logger::info("TenantThemeManager: Directorio de temas creado para tenant {$tenantId}");
        }

        // Escanear directorio de temas
        foreach (glob($themesDir . '/*', GLOB_ONLYDIR) as $themePath) {
            $slug = basename($themePath);
            $metadataFile = $themePath . '/theme.json';

            if (!file_exists($metadataFile)) {
                Logger::warning("TenantThemeManager: No existe theme.json en {$slug}");
                $results['errors'][] = "Tema {$slug}: Falta theme.json";
                continue;
            }

            // VALIDACIÓN DE SEGURIDAD (CRÍTICO)
            $validation = ThemeSecurityValidator::validate($themePath);

            if (!$validation['valid']) {
                Logger::error("TenantThemeManager: Tema {$slug} no pasó validación de seguridad", $validation['errors']);
                $results['errors'][] = "Tema {$slug}: " . implode(', ', $validation['errors']);

                // Registrar pero marcar como NO validado
                self::registerTheme($tenantId, $slug, $themePath, false, $validation);
                continue;
            }

            // Registrar tema validado
            self::registerTheme($tenantId, $slug, $themePath, true, $validation);

            if (!empty($validation['warnings'])) {
                $results['validation_warnings'][] = "Tema {$slug}: " . implode(', ', $validation['warnings']);
            }

            $results['registered'][] = $slug;
        }

        return $results;
    }

    /**
     * Registrar tema en base de datos
     */
    private static function registerTheme(int $tenantId, string $slug, string $themePath, bool $validated, array $validation): void {
        $db = Database::connect();
        $metadata = json_decode(file_get_contents($themePath . '/theme.json'), true);

        try {
            $stmt = $db->prepare("SELECT id FROM tenant_themes WHERE tenant_id = :tenant_id AND slug = :slug");
            $stmt->execute(['tenant_id' => $tenantId, 'slug' => $slug]);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$existing) {
                // Nuevo tema
                $stmt = $db->prepare("
                    INSERT INTO tenant_themes (
                        tenant_id, slug, name, description, version, author,
                        screenshot, active, validated, security_score, validation_errors
                    ) VALUES (
                        :tenant_id, :slug, :name, :description, :version, :author,
                        :screenshot, :active, :validated, :security_score, :validation_errors
                    )
                ");

                $stmt->execute([
                    'tenant_id' => $tenantId,
                    'slug' => $slug,
                    'name' => $metadata['name'] ?? ucfirst($slug),
                    'description' => $metadata['description'] ?? '',
                    'version' => $metadata['version'] ?? '1.0.0',
                    'author' => $metadata['author'] ?? 'Unknown',
                    'screenshot' => $metadata['screenshot'] ?? null,
                    'active' => 0, // Desactivado por defecto
                    'validated' => (int)$validated,
                    'security_score' => $validation['security_score'] ?? null,
                    'validation_errors' => json_encode($validation['errors'] ?? [])
                ]);

                Logger::info("TenantThemeManager: Tema {$slug} registrado para tenant {$tenantId}");
            } else {
                // Actualizar validación
                $stmt = $db->prepare("
                    UPDATE tenant_themes
                    SET validated = :validated,
                        security_score = :security_score,
                        validation_errors = :validation_errors,
                        updated_at = NOW()
                    WHERE tenant_id = :tenant_id AND slug = :slug
                ");

                $stmt->execute([
                    'tenant_id' => $tenantId,
                    'slug' => $slug,
                    'validated' => (int)$validated,
                    'security_score' => $validation['security_score'] ?? null,
                    'validation_errors' => json_encode($validation['errors'] ?? [])
                ]);
            }

        } catch (\Exception $e) {
            Logger::error("TenantThemeManager: Error al registrar tema {$slug}", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Instalar tema desde ZIP
     */
    public static function install(int $tenantId, string $zipPath): array {
        $themesDir = self::getThemesPath($tenantId);

        try {
            // Extraer a temporal
            $tempDir = sys_get_temp_dir() . '/musedock_theme_' . uniqid();
            mkdir($tempDir, 0755, true);

            $zip = new \ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new \Exception("No se pudo abrir el archivo ZIP");
            }

            $zip->extractTo($tempDir);
            $zip->close();

            // Validar estructura básica
            if (!file_exists($tempDir . '/theme.json')) {
                throw new \Exception("El tema no contiene theme.json");
            }

            // VALIDACIÓN DE SEGURIDAD ANTES DE INSTALAR
            $validation = ThemeSecurityValidator::validate($tempDir);

            if (!$validation['valid'] && $validation['critical']) {
                throw new \Exception(
                    "Tema rechazado por seguridad: " . implode(', ', $validation['errors'])
                );
            }

            // Leer metadata
            $metadata = json_decode(file_get_contents($tempDir . '/theme.json'), true);
            $slug = $metadata['slug'] ?? basename($tempDir);

            // Mover a directorio final
            $finalDir = $themesDir . '/' . $slug;
            if (is_dir($finalDir)) {
                throw new \Exception("El tema ya existe. Desinstálalo primero.");
            }

            rename($tempDir, $finalDir);

            // Sincronizar con BD
            self::syncTenantThemes($tenantId);

            Logger::info("TenantThemeManager: Tema {$slug} instalado para tenant {$tenantId}");

            return [
                'success' => true,
                'slug' => $slug,
                'name' => $metadata['name'],
                'validated' => $validation['valid'],
                'security_score' => $validation['security_score'],
                'warnings' => $validation['warnings'] ?? []
            ];

        } catch (\Exception $e) {
            Logger::error("TenantThemeManager: Error al instalar tema", ['error' => $e->getMessage()]);

            if (isset($tempDir) && is_dir($tempDir)) {
                self::deleteDirectory($tempDir);
            }

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Activar tema personalizado
     */
    public static function activate(int $tenantId, string $slug): bool {
        $db = Database::connect();

        try {
            // Verificar que el tema existe y está validado
            $stmt = $db->prepare("
                SELECT * FROM tenant_themes
                WHERE tenant_id = :tenant_id AND slug = :slug
            ");
            $stmt->execute(['tenant_id' => $tenantId, 'slug' => $slug]);
            $theme = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$theme) {
                throw new \Exception("Tema no encontrado");
            }

            if (!$theme['validated']) {
                throw new \Exception("Tema no validado. No se puede activar por seguridad.");
            }

            // Desactivar tema activo actual (si existe)
            $db->prepare("UPDATE tenant_themes SET active = 0 WHERE tenant_id = :tenant_id")
               ->execute(['tenant_id' => $tenantId]);

            // Activar nuevo tema
            $db->prepare("UPDATE tenant_themes SET active = 1 WHERE tenant_id = :tenant_id AND slug = :slug")
               ->execute(['tenant_id' => $tenantId, 'slug' => $slug]);

            // Actualizar tenant
            $db->prepare("
                UPDATE tenants
                SET theme_type = 'custom', custom_theme_slug = :slug
                WHERE id = :tenant_id
            ")->execute(['tenant_id' => $tenantId, 'slug' => $slug]);

            Logger::info("TenantThemeManager: Tema {$slug} activado para tenant {$tenantId}");

            return true;

        } catch (\Exception $e) {
            Logger::error("TenantThemeManager: Error al activar tema", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Volver a tema global
     */
    public static function useGlobalTheme(int $tenantId, string $globalSlug): bool {
        $db = Database::connect();

        try {
            // Desactivar temas custom
            $db->prepare("UPDATE tenant_themes SET active = 0 WHERE tenant_id = :tenant_id")
               ->execute(['tenant_id' => $tenantId]);

            // Actualizar tenant a tema global
            $db->prepare("
                UPDATE tenants
                SET theme_type = 'global', custom_theme_slug = NULL, theme = :slug
                WHERE id = :tenant_id
            ")->execute(['tenant_id' => $tenantId, 'slug' => $globalSlug]);

            Logger::info("TenantThemeManager: Tenant {$tenantId} cambiado a tema global {$globalSlug}");

            return true;

        } catch (\Exception $e) {
            Logger::error("TenantThemeManager: Error al cambiar a tema global", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Desinstalar tema
     */
    public static function uninstall(int $tenantId, string $slug): bool {
        $db = Database::connect();
        $themePath = self::getThemesPath($tenantId) . '/' . $slug;

        try {
            // Verificar que no está activo
            $stmt = $db->prepare("SELECT active FROM tenant_themes WHERE tenant_id = :tenant_id AND slug = :slug");
            $stmt->execute(['tenant_id' => $tenantId, 'slug' => $slug]);
            $theme = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($theme && $theme['active']) {
                throw new \Exception("No puedes desinstalar el tema activo. Activa otro tema primero.");
            }

            // Eliminar de BD
            $db->prepare("DELETE FROM tenant_themes WHERE tenant_id = :tenant_id AND slug = :slug")
               ->execute(['tenant_id' => $tenantId, 'slug' => $slug]);

            // Eliminar archivos
            if (is_dir($themePath)) {
                self::deleteDirectory($themePath);
            }

            Logger::info("TenantThemeManager: Tema {$slug} desinstalado del tenant {$tenantId}");
            return true;

        } catch (\Exception $e) {
            Logger::error("TenantThemeManager: Error al desinstalar tema", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Helper: Eliminar directorio recursivamente
     */
    private static function deleteDirectory(string $dir): bool {
        if (!is_dir($dir)) return false;

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? self::deleteDirectory($path) : unlink($path);
        }

        return rmdir($dir);
    }
}

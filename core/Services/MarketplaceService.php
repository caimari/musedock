<?php

namespace Screenart\Musedock\Services;

use Screenart\Musedock\Database;
use Screenart\Musedock\Logger;

/**
 * Servicio de Marketplace para módulos, plugins y temas
 * Similar a WordPress pero para MuseDock
 *
 * API Central: api.musedock.org
 */
class MarketplaceService
{
    // URL base de la API del Marketplace
    private const API_BASE = 'https://api.musedock.org/v1/marketplace';

    // Tipos de items disponibles
    public const TYPE_MODULE = 'module';
    public const TYPE_PLUGIN = 'plugin';
    public const TYPE_THEME = 'theme';

    /**
     * Buscar items en el marketplace
     */
    public static function search(string $query, string $type = null, array $filters = []): array
    {
        $params = [
            'q' => $query,
            'type' => $type,
            'page' => $filters['page'] ?? 1,
            'per_page' => $filters['per_page'] ?? 12,
            'sort' => $filters['sort'] ?? 'popular', // popular, newest, updated, rating
            'category' => $filters['category'] ?? null,
            'compatible_version' => UpdateService::getCurrentVersion()['core'],
        ];

        return self::fetchFromAPI('/search', $params) ?? [
            'items' => [],
            'total' => 0,
            'page' => 1,
            'total_pages' => 0,
        ];
    }

    /**
     * Obtener items destacados
     */
    public static function getFeatured(string $type = null, int $limit = 6): array
    {
        return self::fetchFromAPI('/featured', [
            'type' => $type,
            'limit' => $limit,
        ]) ?? [];
    }

    /**
     * Obtener items populares
     */
    public static function getPopular(string $type = null, int $limit = 12): array
    {
        return self::fetchFromAPI('/popular', [
            'type' => $type,
            'limit' => $limit,
        ]) ?? [];
    }

    /**
     * Obtener categorías disponibles
     */
    public static function getCategories(string $type = null): array
    {
        return self::fetchFromAPI('/categories', ['type' => $type]) ?? [];
    }

    /**
     * Obtener detalles de un item
     */
    public static function getItemDetails(string $slug, string $type): ?array
    {
        return self::fetchFromAPI("/item/{$type}/{$slug}");
    }

    /**
     * Descargar e instalar un item del marketplace
     */
    public static function install(string $slug, string $type): array
    {
        $result = [
            'success' => false,
            'message' => '',
        ];

        try {
            // 1. Obtener información del item
            $item = self::getItemDetails($slug, $type);
            if (!$item) {
                throw new \Exception('Item no encontrado en el marketplace');
            }

            // 2. Verificar compatibilidad
            $currentVersion = UpdateService::getCurrentVersion()['core'];
            if (isset($item['min_version']) && version_compare($currentVersion, $item['min_version'], '<')) {
                throw new \Exception("Requiere MuseDock v{$item['min_version']} o superior");
            }

            // 3. Obtener URL de descarga (requiere autenticación en producción)
            $downloadUrl = self::getDownloadUrl($slug, $type);
            if (!$downloadUrl) {
                throw new \Exception('No se pudo obtener la URL de descarga');
            }

            // 4. Descargar el paquete
            $tempFile = self::downloadPackage($downloadUrl);

            // 5. Verificar integridad (checksum)
            if (isset($item['checksum'])) {
                $fileChecksum = hash_file('sha256', $tempFile);
                if ($fileChecksum !== $item['checksum']) {
                    unlink($tempFile);
                    throw new \Exception('Error de integridad del paquete');
                }
            }

            // 6. Extraer e instalar según el tipo
            switch ($type) {
                case self::TYPE_MODULE:
                    self::installModule($tempFile, $slug);
                    break;
                case self::TYPE_PLUGIN:
                    self::installPlugin($tempFile, $slug);
                    break;
                case self::TYPE_THEME:
                    self::installTheme($tempFile, $slug);
                    break;
            }

            // 7. Limpiar archivo temporal
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }

            // 8. Registrar instalación
            self::registerInstallation($slug, $type, $item['version'] ?? '1.0.0');

            $result['success'] = true;
            $result['message'] = ucfirst($type) . " '{$item['name']}' instalado correctamente";

            Logger::log("Marketplace: Installed {$type} '{$slug}'", 'INFO');

        } catch (\Exception $e) {
            $result['message'] = $e->getMessage();
            Logger::log("Marketplace install error: " . $e->getMessage(), 'ERROR');
        }

        return $result;
    }

    /**
     * Desinstalar un item
     */
    public static function uninstall(string $slug, string $type): array
    {
        $result = [
            'success' => false,
            'message' => '',
        ];

        try {
            $targetDir = self::getTargetDirectory($type) . '/' . $slug;

            if (!is_dir($targetDir)) {
                throw new \Exception(ucfirst($type) . " no encontrado");
            }

            // Verificar si tiene uninstaller
            $uninstaller = $targetDir . '/uninstall.php';
            if (file_exists($uninstaller)) {
                include $uninstaller;
            }

            // Eliminar directorio
            self::deleteDirectory($targetDir);

            // Eliminar de la base de datos
            self::unregisterInstallation($slug, $type);

            $result['success'] = true;
            $result['message'] = ucfirst($type) . " desinstalado correctamente";

            Logger::log("Marketplace: Uninstalled {$type} '{$slug}'", 'INFO');

        } catch (\Exception $e) {
            $result['message'] = $e->getMessage();
            Logger::log("Marketplace uninstall error: " . $e->getMessage(), 'ERROR');
        }

        return $result;
    }

    /**
     * Obtener items instalados desde el marketplace
     */
    public static function getInstalled(string $type = null): array
    {
        $db = Database::connect();

        $sql = "SELECT * FROM marketplace_items";
        $params = [];

        if ($type) {
            $sql .= " WHERE type = ?";
            $params[] = $type;
        }

        $sql .= " ORDER BY installed_at DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Verificar actualizaciones de items instalados
     */
    public static function checkItemUpdates(): array
    {
        $installed = self::getInstalled();
        $updates = [];

        foreach ($installed as $item) {
            $latest = self::getItemDetails($item['slug'], $item['type']);

            if ($latest && version_compare($latest['version'], $item['version'], '>')) {
                $updates[] = [
                    'slug' => $item['slug'],
                    'type' => $item['type'],
                    'name' => $item['name'],
                    'current_version' => $item['version'],
                    'latest_version' => $latest['version'],
                    'changelog' => $latest['changelog'] ?? null,
                ];
            }
        }

        return $updates;
    }

    /**
     * Actualizar un item instalado
     */
    public static function update(string $slug, string $type): array
    {
        // Básicamente reinstalar con la nueva versión
        return self::install($slug, $type);
    }

    // ========================================
    // MÉTODOS PARA DESARROLLADORES
    // ========================================

    /**
     * Publicar un item en el marketplace (para desarrolladores)
     * Requiere cuenta de desarrollador en musedock.org
     */
    public static function publish(string $packagePath, string $type, string $apiKey): array
    {
        $result = [
            'success' => false,
            'message' => '',
        ];

        try {
            // Validar que el paquete tenga la estructura correcta
            $validation = self::validatePackage($packagePath, $type);
            if (!$validation['valid']) {
                throw new \Exception($validation['error']);
            }

            // Subir al marketplace
            $response = self::uploadToAPI('/publish', [
                'type' => $type,
                'package' => base64_encode(file_get_contents($packagePath)),
            ], $apiKey);

            if ($response && $response['success']) {
                $result['success'] = true;
                $result['message'] = 'Publicado correctamente';
                $result['item_url'] = $response['url'] ?? null;
            } else {
                throw new \Exception($response['error'] ?? 'Error al publicar');
            }

        } catch (\Exception $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Validar estructura de un paquete antes de publicar
     */
    public static function validatePackage(string $packagePath, string $type): array
    {
        $result = ['valid' => false, 'error' => null];

        if (!file_exists($packagePath)) {
            $result['error'] = 'Archivo no encontrado';
            return $result;
        }

        $zip = new \ZipArchive();
        if ($zip->open($packagePath) !== true) {
            $result['error'] = 'No es un archivo ZIP válido';
            return $result;
        }

        // Verificar archivos requeridos según el tipo
        $requiredFiles = [
            self::TYPE_MODULE => ['module.json', 'routes.php'],
            self::TYPE_PLUGIN => ['plugin.json'],
            self::TYPE_THEME => ['theme.json', 'views/layouts/main.blade.php'],
        ];

        $required = $requiredFiles[$type] ?? [];
        $missing = [];

        foreach ($required as $file) {
            if ($zip->locateName($file) === false) {
                // Intentar con subdirectorio
                $found = false;
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = $zip->getNameIndex($i);
                    if (str_ends_with($name, '/' . $file)) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $missing[] = $file;
                }
            }
        }

        $zip->close();

        if (!empty($missing)) {
            $result['error'] = 'Archivos requeridos faltantes: ' . implode(', ', $missing);
            return $result;
        }

        $result['valid'] = true;
        return $result;
    }

    // ========================================
    // MÉTODOS PRIVADOS
    // ========================================

    private static function fetchFromAPI(string $endpoint, array $params = []): ?array
    {
        $url = self::API_BASE . $endpoint;

        if (!empty($params)) {
            $url .= '?' . http_build_query(array_filter($params));
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Accept: application/json',
                    'User-Agent: MuseDock-CMS/' . (UpdateService::getCurrentVersion()['core'] ?? '1.0.0'),
                ],
                'timeout' => 15,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            // En desarrollo, devolver datos de ejemplo
            return self::getMockData($endpoint, $params);
        }

        return json_decode($response, true);
    }

    private static function uploadToAPI(string $endpoint, array $data, string $apiKey): ?array
    {
        $url = self::API_BASE . $endpoint;

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Authorization: Bearer ' . $apiKey,
                ],
                'content' => json_encode($data),
                'timeout' => 60,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return null;
        }

        return json_decode($response, true);
    }

    private static function getDownloadUrl(string $slug, string $type): ?string
    {
        $response = self::fetchFromAPI("/download/{$type}/{$slug}");
        return $response['url'] ?? null;
    }

    private static function downloadPackage(string $url): string
    {
        $tempFile = sys_get_temp_dir() . '/musedock_' . uniqid() . '.zip';

        $context = stream_context_create([
            'http' => [
                'timeout' => 120,
            ],
        ]);

        $content = file_get_contents($url, false, $context);

        if ($content === false) {
            throw new \Exception('Error al descargar el paquete');
        }

        file_put_contents($tempFile, $content);

        return $tempFile;
    }

    private static function getTargetDirectory(string $type): string
    {
        $dirs = [
            self::TYPE_MODULE => APP_ROOT . '/modules',
            self::TYPE_PLUGIN => APP_ROOT . '/plugins',
            self::TYPE_THEME => APP_ROOT . '/themes',
        ];

        return $dirs[$type] ?? APP_ROOT . '/modules';
    }

    private static function installModule(string $zipFile, string $slug): void
    {
        $targetDir = self::getTargetDirectory(self::TYPE_MODULE) . '/' . $slug;
        self::extractZip($zipFile, $targetDir);

        // Ejecutar migraciones del módulo si existen
        $migrationsDir = $targetDir . '/migrations';
        if (is_dir($migrationsDir)) {
            exec('cd ' . escapeshellarg(APP_ROOT) . ' && php migrate --module=' . escapeshellarg($slug));
        }
    }

    private static function installPlugin(string $zipFile, string $slug): void
    {
        $targetDir = self::getTargetDirectory(self::TYPE_PLUGIN) . '/' . $slug;
        self::extractZip($zipFile, $targetDir);

        // Ejecutar instalador si existe
        $installer = $targetDir . '/install.php';
        if (file_exists($installer)) {
            include $installer;
        }
    }

    private static function installTheme(string $zipFile, string $slug): void
    {
        $targetDir = self::getTargetDirectory(self::TYPE_THEME) . '/' . $slug;
        self::extractZip($zipFile, $targetDir);
    }

    private static function extractZip(string $zipFile, string $targetDir): void
    {
        $zip = new \ZipArchive();

        if ($zip->open($zipFile) !== true) {
            throw new \Exception('No se pudo abrir el archivo ZIP');
        }

        // Crear directorio si no existe
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        // Extraer con validación de seguridad
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);

            // Prevenir path traversal
            if (strpos($filename, '..') !== false) {
                continue;
            }

            $zip->extractTo($targetDir, $filename);
        }

        $zip->close();
    }

    private static function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? self::deleteDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }

    private static function registerInstallation(string $slug, string $type, string $version): void
    {
        $db = Database::connect();

        // Crear tabla si no existe
        $db->exec("
            CREATE TABLE IF NOT EXISTS marketplace_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(100) NOT NULL,
                type VARCHAR(20) NOT NULL,
                name VARCHAR(255),
                version VARCHAR(20),
                installed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_item (slug, type)
            )
        ");

        $stmt = $db->prepare("
            INSERT INTO marketplace_items (slug, type, version, installed_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE version = ?, updated_at = NOW()
        ");

        $stmt->execute([$slug, $type, $version, $version]);
    }

    private static function unregisterInstallation(string $slug, string $type): void
    {
        $db = Database::connect();
        $stmt = $db->prepare("DELETE FROM marketplace_items WHERE slug = ? AND type = ?");
        $stmt->execute([$slug, $type]);
    }

    /**
     * Datos de ejemplo para desarrollo (cuando la API no está disponible)
     */
    private static function getMockData(string $endpoint, array $params = []): array
    {
        // Datos de ejemplo para desarrollo local
        if (str_contains($endpoint, '/popular') || str_contains($endpoint, '/search')) {
            return [
                'items' => [
                    [
                        'slug' => 'seo-toolkit',
                        'name' => 'SEO Toolkit',
                        'type' => 'module',
                        'description' => 'Herramientas completas de SEO para tu sitio',
                        'version' => '2.1.0',
                        'author' => 'MuseDock Team',
                        'downloads' => 15420,
                        'rating' => 4.8,
                        'price' => 0,
                        'thumbnail' => '/assets/img/marketplace/seo-toolkit.png',
                    ],
                    [
                        'slug' => 'ecommerce',
                        'name' => 'E-Commerce Pro',
                        'type' => 'module',
                        'description' => 'Tienda online completa con carrito y pagos',
                        'version' => '3.0.0',
                        'author' => 'MuseDock Team',
                        'downloads' => 8750,
                        'rating' => 4.9,
                        'price' => 49,
                        'thumbnail' => '/assets/img/marketplace/ecommerce.png',
                    ],
                    [
                        'slug' => 'starter-developer',
                        'name' => 'Developer Theme',
                        'type' => 'theme',
                        'description' => 'Tema moderno para portfolios de desarrolladores',
                        'version' => '1.5.0',
                        'author' => 'Community',
                        'downloads' => 3200,
                        'rating' => 4.5,
                        'price' => 0,
                        'thumbnail' => '/assets/img/marketplace/developer-theme.png',
                    ],
                ],
                'total' => 3,
                'page' => 1,
                'total_pages' => 1,
            ];
        }

        if (str_contains($endpoint, '/categories')) {
            return [
                ['slug' => 'content', 'name' => 'Contenido', 'count' => 45],
                ['slug' => 'ecommerce', 'name' => 'E-Commerce', 'count' => 23],
                ['slug' => 'seo', 'name' => 'SEO & Marketing', 'count' => 18],
                ['slug' => 'social', 'name' => 'Redes Sociales', 'count' => 12],
                ['slug' => 'security', 'name' => 'Seguridad', 'count' => 8],
                ['slug' => 'analytics', 'name' => 'Analíticas', 'count' => 15],
            ];
        }

        return [];
    }
}

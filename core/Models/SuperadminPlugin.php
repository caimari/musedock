<?php

namespace Screenart\Musedock\Models;

use Screenart\Musedock\Database;
use PDO;

class SuperadminPlugin
{
    public int $id;
    public string $slug;
    public string $name;
    public ?string $description;
    public string $version;
    public ?string $author;
    public ?string $author_url;
    public ?string $plugin_url;
    public string $path;
    public string $main_file;
    public ?string $namespace;
    public bool $is_active;
    public bool $is_installed;
    public bool $auto_activate;
    public ?string $requires_php;
    public ?string $requires_musedock;
    public ?array $dependencies;
    public ?array $settings;
    public ?string $installed_at;
    public ?string $activated_at;
    public string $created_at;
    public string $updated_at;

    /**
     * Crear un nuevo plugin
     */
    public static function create(array $data): ?self
    {
        $db = Database::connect();

        $sql = "INSERT INTO superadmin_plugins (
            slug, name, description, version, author, author_url, plugin_url,
            path, main_file, namespace, is_active, is_installed, auto_activate,
            requires_php, requires_musedock, dependencies, settings, installed_at
        ) VALUES (
            :slug, :name, :description, :version, :author, :author_url, :plugin_url,
            :path, :main_file, :namespace, :is_active, :is_installed, :auto_activate,
            :requires_php, :requires_musedock, :dependencies, :settings, :installed_at
        )";

        $stmt = $db->prepare($sql);

        $dependencies = isset($data['dependencies']) ? json_encode($data['dependencies']) : null;
        $settings = isset($data['settings']) ? json_encode($data['settings']) : null;

        // Convertir booleanos a enteros para compatibilidad PostgreSQL
        $isActive = isset($data['is_active']) ? ($data['is_active'] ? 1 : 0) : 0;
        $isInstalled = isset($data['is_installed']) ? ($data['is_installed'] ? 1 : 0) : 0;
        $autoActivate = isset($data['auto_activate']) ? ($data['auto_activate'] ? 1 : 0) : 0;

        $params = [
            'slug' => $data['slug'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'version' => $data['version'] ?? '1.0.0',
            'author' => $data['author'] ?? null,
            'author_url' => $data['author_url'] ?? null,
            'plugin_url' => $data['plugin_url'] ?? null,
            'path' => $data['path'],
            'main_file' => $data['main_file'],
            'namespace' => $data['namespace'] ?? null,
            'is_active' => $isActive,
            'is_installed' => $isInstalled,
            'auto_activate' => $autoActivate,
            'requires_php' => $data['requires_php'] ?? null,
            'requires_musedock' => $data['requires_musedock'] ?? null,
            'dependencies' => $dependencies,
            'settings' => $settings,
            'installed_at' => $data['installed_at'] ?? null
        ];

        if (!$stmt->execute($params)) {
            return null;
        }

        $id = (int) $db->lastInsertId();
        return self::find($id);
    }

    /**
     * Buscar plugin por ID
     */
    public static function find(int $id): ?self
    {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM superadmin_plugins WHERE id = ?");
        $stmt->execute([$id]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            return null;
        }

        return self::hydrate($data);
    }

    /**
     * Buscar plugin por slug
     */
    public static function findBySlug(string $slug): ?self
    {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM superadmin_plugins WHERE slug = ?");
        $stmt->execute([$slug]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            return null;
        }

        return self::hydrate($data);
    }

    /**
     * Obtener todos los plugins
     */
    public static function all(): array
    {
        $db = Database::connect();
        $stmt = $db->query("SELECT * FROM superadmin_plugins ORDER BY name ASC");

        $plugins = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $plugins[] = self::hydrate($row);
        }

        return $plugins;
    }

    /**
     * Obtener plugins activos
     */
    public static function getActive(): array
    {
        $db = Database::connect();
        $stmt = $db->query("
            SELECT * FROM superadmin_plugins
            WHERE is_active = 1 AND is_installed = 1
            ORDER BY name ASC
        ");

        $plugins = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $plugins[] = self::hydrate($row);
        }

        return $plugins;
    }

    /**
     * Obtener plugins instalados
     */
    public static function getInstalled(): array
    {
        $db = Database::connect();
        $stmt = $db->query("
            SELECT * FROM superadmin_plugins
            WHERE is_installed = 1
            ORDER BY name ASC
        ");

        $plugins = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $plugins[] = self::hydrate($row);
        }

        return $plugins;
    }

    /**
     * Actualizar plugin
     */
    public function update(array $data): bool
    {
        $db = Database::connect();

        $fields = [];
        $params = ['id' => $this->id];

        // Campos booleanos que deben convertirse a enteros para PostgreSQL
        $booleanFields = ['is_active', 'is_installed', 'auto_activate'];

        foreach ($data as $key => $value) {
            if (in_array($key, ['dependencies', 'settings']) && is_array($value)) {
                $value = json_encode($value);
            }

            // Convertir booleanos a enteros para compatibilidad PostgreSQL
            if (in_array($key, $booleanFields)) {
                $value = $value ? 1 : 0;
            }

            $fields[] = "$key = :$key";
            $params[$key] = $value;
        }

        $sql = "UPDATE superadmin_plugins SET " . implode(', ', $fields) . " WHERE id = :id";

        $stmt = $db->prepare($sql);
        $result = $stmt->execute($params);

        if ($result) {
            // Recargar datos
            $updated = self::find($this->id);
            if ($updated) {
                foreach (get_object_vars($updated) as $key => $value) {
                    $this->$key = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Activar plugin
     */
    public function activate(): bool
    {
        $result = $this->update([
            'is_active' => true,
            'activated_at' => date('Y-m-d H:i:s')
        ]);

        if ($result) {
            error_log("SuperadminPlugin: Plugin '{$this->slug}' activado");
        }

        return $result;
    }

    /**
     * Desactivar plugin
     */
    public function deactivate(): bool
    {
        $result = $this->update(['is_active' => false]);

        if ($result) {
            error_log("SuperadminPlugin: Plugin '{$this->slug}' desactivado");
        }

        return $result;
    }

    /**
     * Marcar como instalado
     */
    public function markAsInstalled(): bool
    {
        return $this->update([
            'is_installed' => true,
            'installed_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Desinstalar plugin
     */
    public function uninstall(): bool
    {
        $db = Database::connect();

        // Primero desactivar
        $this->deactivate();

        // Eliminar de la base de datos (CASCADE eliminará hooks, rutas, etc.)
        $stmt = $db->prepare("DELETE FROM superadmin_plugins WHERE id = ?");
        $result = $stmt->execute([$this->id]);

        if ($result) {
            error_log("SuperadminPlugin: Plugin '{$this->slug}' desinstalado");
        }

        return $result;
    }

    /**
     * Verificar si el plugin cumple con los requisitos
     */
    public function meetsRequirements(): array
    {
        $errors = [];

        // Verificar versión de PHP
        if ($this->requires_php && version_compare(PHP_VERSION, $this->requires_php, '<')) {
            $errors[] = "Requiere PHP {$this->requires_php} o superior. Actual: " . PHP_VERSION;
        }

        // Verificar versión de MuseDock
        if ($this->requires_musedock) {
            $musedockVersion = self::getMusedockVersion();
            if (version_compare($musedockVersion, $this->requires_musedock, '<')) {
                $errors[] = "Requiere MuseDock {$this->requires_musedock} o superior. Actual: {$musedockVersion}";
            }
        }

        // Verificar dependencias
        if ($this->dependencies) {
            foreach ($this->dependencies as $dependency => $version) {
                $depPlugin = self::findBySlug($dependency);

                if (!$depPlugin) {
                    $errors[] = "Requiere el plugin '{$dependency}' que no está instalado";
                } elseif (!$depPlugin->is_active) {
                    $errors[] = "Requiere el plugin '{$dependency}' activado";
                } elseif ($version && version_compare($depPlugin->version, $version, '<')) {
                    $errors[] = "Requiere '{$dependency}' versión {$version} o superior. Instalada: {$depPlugin->version}";
                }
            }
        }

        return $errors;
    }

    /**
     * Obtener la versión de MuseDock desde composer.json
     */
    private static function getMusedockVersion(): string
    {
        // Primero verificar constante definida
        if (defined('MUSEDOCK_VERSION')) {
            return MUSEDOCK_VERSION;
        }

        // Leer desde composer.json como fuente de verdad
        $composerFile = defined('APP_ROOT') ? APP_ROOT . '/composer.json' : dirname(__DIR__, 2) . '/composer.json';

        if (file_exists($composerFile)) {
            $composer = json_decode(file_get_contents($composerFile), true);
            if (isset($composer['version'])) {
                return $composer['version'];
            }
        }

        // Fallback
        return '1.0.0';
    }

    /**
     * Obtener configuración del plugin
     */
    public function getSetting(string $key, $default = null)
    {
        if (!$this->settings) {
            return $default;
        }

        return $this->settings[$key] ?? $default;
    }

    /**
     * Actualizar configuración del plugin
     */
    public function updateSetting(string $key, $value): bool
    {
        $settings = $this->settings ?? [];
        $settings[$key] = $value;

        return $this->update(['settings' => $settings]);
    }

    /**
     * Hidratar objeto desde array
     */
    private static function hydrate(array $data): self
    {
        $plugin = new self();

        $plugin->id = (int) $data['id'];
        $plugin->slug = $data['slug'];
        $plugin->name = $data['name'];
        $plugin->description = $data['description'];
        $plugin->version = $data['version'];
        $plugin->author = $data['author'];
        $plugin->author_url = $data['author_url'];
        $plugin->plugin_url = $data['plugin_url'];
        $plugin->path = $data['path'];
        $plugin->main_file = $data['main_file'];
        $plugin->namespace = $data['namespace'];
        $plugin->is_active = (bool) $data['is_active'];
        $plugin->is_installed = (bool) $data['is_installed'];
        $plugin->auto_activate = (bool) $data['auto_activate'];
        $plugin->requires_php = $data['requires_php'];
        $plugin->requires_musedock = $data['requires_musedock'];
        $plugin->dependencies = $data['dependencies'] ? json_decode($data['dependencies'], true) : null;
        $plugin->settings = $data['settings'] ? json_decode($data['settings'], true) : null;
        $plugin->installed_at = $data['installed_at'];
        $plugin->activated_at = $data['activated_at'];
        $plugin->created_at = $data['created_at'];
        $plugin->updated_at = $data['updated_at'];

        return $plugin;
    }

    /**
     * Convertir a array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'version' => $this->version,
            'author' => $this->author,
            'author_url' => $this->author_url,
            'plugin_url' => $this->plugin_url,
            'path' => $this->path,
            'main_file' => $this->main_file,
            'namespace' => $this->namespace,
            'is_active' => $this->is_active,
            'is_installed' => $this->is_installed,
            'auto_activate' => $this->auto_activate,
            'requires_php' => $this->requires_php,
            'requires_musedock' => $this->requires_musedock,
            'dependencies' => $this->dependencies,
            'settings' => $this->settings,
            'installed_at' => $this->installed_at,
            'activated_at' => $this->activated_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

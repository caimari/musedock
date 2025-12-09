<?php

namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Security\PermissionManager;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Logger;
use Screenart\Musedock\Helpers\BladeProtector;
use ZipArchive;

use Screenart\Musedock\Traits\RequiresPermission;
class ThemeController
{
    use RequiresPermission;

public function index()
{
    SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

    $pdo = Database::connect();

    $themesDir = __DIR__ . '/../../../themes';
    $folders = array_filter(glob($themesDir . '/*'), 'is_dir');

    $availableThemes = [];

    // Tema activo actual
    $stmt = $pdo->query("SELECT value FROM settings WHERE `key` = 'default_theme' LIMIT 1");
    $currentTheme = $stmt->fetchColumn();

    foreach ($folders as $folderPath) {
        $slug = basename($folderPath);

        if (str_starts_with($slug, 'shared') || str_starts_with($slug, 'tenant_')) {
            continue;
        }

        $homeView = "{$folderPath}/views/home.blade.php";
        $exists = file_exists($homeView);

        // Verificar si está en BD
        $stmt = $pdo->prepare("SELECT * FROM themes WHERE slug = ?");
        $stmt->execute([$slug]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            // Insertamos si no está registrado
            $stmt = $pdo->prepare("INSERT INTO themes (name, slug, active, installed_at) VALUES (?, ?, 0, NOW())");
            $stmt->execute([ucfirst($slug), $slug]);

            $row = [
                'name' => ucfirst($slug),
                'slug' => $slug,
                'active' => 0,
                'installed_at' => date('Y-m-d H:i:s'),
            ];
        }

        $row['status'] = !$exists
            ? 'incompleto: falta home.blade.php'
            : ($slug === $currentTheme ? 'activo' : 'ok');

        $availableThemes[] = $row;
    }

    return View::renderSuperadmin('themes.index', [
        'title' => 'Gestión de Temas',
        'themes' => $availableThemes,
        'currentTheme' => $currentTheme,
    ]);
}



public function activate()
{
    SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

    $slug = $_POST['theme'] ?? null;
    if (!$slug) {
        flash('error', 'Tema no especificado.');
        header('Location: /musedock/themes');
        exit;
    }

    $themeDir = __DIR__ . "/../../../themes/{$slug}";
    if (!is_dir($themeDir)) {
        flash('error', "El tema '{$slug}' no existe en el sistema de archivos.");
        header('Location: /musedock/themes');
        exit;
    }

    $pdo = Database::connect();

    // Verificar si el tema está registrado en la base de datos
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM themes WHERE slug = ?");
    $stmt->execute([$slug]);
    $exists = $stmt->fetchColumn();

    if (!$exists) {
        // Registrar automáticamente el tema si existe en disco
        $stmt = $pdo->prepare("INSERT INTO themes (name, slug, active, installed_at) VALUES (?, ?, 0, NOW())");
        $stmt->execute([ucfirst($slug), $slug]);
    }

    // Desactivar todos
    $pdo->exec("UPDATE themes SET active = 0");

    // Activar este
    $stmt = $pdo->prepare("UPDATE themes SET active = 1 WHERE slug = ?");
    $stmt->execute([$slug]);

    // Actualizar en settings
    $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE `key` = 'default_theme'");
    $stmt->execute([$slug]);

    flash('success', "Tema '{$slug}' activado correctamente.");
    header('Location: /musedock/themes');
    exit;
}



    public function create()
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');
        return View::renderSuperadmin('themes.create', [
            'title' => 'Crear Nuevo Tema',
        ]);
    }

    public function store()
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');

        if (!$name || !$slug) {
            flash('error', 'Nombre y slug del tema son obligatorios.');
            header('Location: /musedock/themes/create');
            exit;
        }

        $path = __DIR__ . "/../../../themes/{$slug}";
        if (file_exists($path)) {
            flash('error', 'Ya existe una carpeta con ese slug.');
            header('Location: /musedock/themes/create');
            exit;
        }

        mkdir($path);
        file_put_contents("{$path}/layout.blade.php", "<!-- Layout básico del tema {$name} -->\n<html><head><title>{{ title }}</title></head><body>{{ content }}</body></html>");

        $pdo = Database::connect();
        $stmt = $pdo->prepare("INSERT INTO themes (name, slug, active, installed_at) VALUES (?, ?, 0, NOW())");
        $stmt->execute([$name, $slug]);

        flash('success', 'Tema creado correctamente.');
        header('Location: /musedock/themes');
        exit;
    }

    public function upload()
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            flash('error', 'Método no permitido.');
            header('Location: /musedock/themes');
            exit;
        }

        if (!isset($_FILES['theme_zip']) || $_FILES['theme_zip']['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Debes seleccionar un archivo ZIP válido.');
            header('Location: /musedock/themes');
            exit;
        }

        $file = $_FILES['theme_zip'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($extension !== 'zip') {
            flash('error', 'El archivo debe ser un ZIP.');
            header('Location: /musedock/themes');
            exit;
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'theme_zip_');
        if (!move_uploaded_file($file['tmp_name'], $tmpFile)) {
            flash('error', 'No se pudo procesar el archivo subido.');
            header('Location: /musedock/themes');
            exit;
        }

        $zip = new ZipArchive();
        if ($zip->open($tmpFile) !== true) {
            unlink($tmpFile);
            flash('error', 'No se pudo abrir el ZIP del tema.');
            header('Location: /musedock/themes');
            exit;
        }

        $extractPath = sys_get_temp_dir() . '/theme_upload_' . uniqid();
        mkdir($extractPath, 0755, true);

        if (!$zip->extractTo($extractPath)) {
            $zip->close();
            unlink($tmpFile);
            $this->removeDirectory($extractPath);
            flash('error', 'No se pudo extraer el contenido del ZIP.');
            header('Location: /musedock/themes');
            exit;
        }
        $zip->close();
        unlink($tmpFile);

        $entries = array_values(array_filter(scandir($extractPath), function ($item) use ($extractPath) {
            return $item !== '.' && $item !== '..';
        }));

        if (count($entries) !== 1 || !is_dir($extractPath . '/' . $entries[0])) {
            $this->removeDirectory($extractPath);
            flash('error', 'El ZIP debe contener una única carpeta con el tema.');
            header('Location: /musedock/themes');
            exit;
        }

        $slug = strtolower(preg_replace('/[^a-z0-9-_]/i', '-', $entries[0]));
        if (empty($slug)) {
            $this->removeDirectory($extractPath);
            flash('error', 'El nombre de la carpeta del tema no es válido.');
            header('Location: /musedock/themes');
            exit;
        }

        $sourceFolder = $extractPath . '/' . $entries[0];
        if ($entries[0] !== $slug) {
            $sanitizedFolder = $extractPath . '/' . $slug;
            rename($sourceFolder, $sanitizedFolder);
            $sourceFolder = $sanitizedFolder;
        }

        $themesBase = realpath(APP_ROOT . '/themes');
        if ($themesBase === false) {
            $themesBase = APP_ROOT . '/themes';
        }
        $destinationPath = $themesBase . '/' . $slug;
        if (file_exists($destinationPath)) {
            $this->removeDirectory($extractPath);
            flash('error', "Ya existe un tema con el slug '{$slug}'.");
            header('Location: /musedock/themes');
            exit;
        }

        if (!rename($sourceFolder, $destinationPath)) {
            $this->removeDirectory($extractPath);
            flash('error', 'No se pudo mover el tema al directorio de temas.');
            header('Location: /musedock/themes');
            exit;
        }

        $this->removeDirectory($extractPath);

        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT id FROM themes WHERE slug = ?");
        $stmt->execute([$slug]);
        $exists = $stmt->fetchColumn();

        if (!$exists) {
            $displayName = ucfirst(str_replace(['-', '_'], ' ', $slug));
            $stmt = $pdo->prepare("INSERT INTO themes (name, slug, active, installed_at) VALUES (?, ?, 0, NOW())");
            $stmt->execute([$displayName, $slug]);
        }

        flash('success', "Tema '{$slug}' subido correctamente.");
        header('Location: /musedock/themes');
        exit;
    }

    public function download($slug)
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        $slug = trim($slug);
        $themesBase = realpath(APP_ROOT . '/themes');
        if ($themesBase === false) {
            $themesBase = APP_ROOT . '/themes';
        }
        $themePath = realpath($themesBase . '/' . $slug);

        if (!$themePath || !is_dir($themePath) || strpos($themePath, $themesBase) !== 0) {
            flash('error', 'El tema solicitado no existe.');
            header('Location: /musedock/themes');
            exit;
        }

        $zipPath = sys_get_temp_dir() . "/theme_{$slug}_" . uniqid() . ".zip";
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            flash('error', 'No se pudo generar el archivo ZIP.');
            header('Location: /musedock/themes');
            exit;
        }

        $zip->addEmptyDir($slug);
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($themePath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($themePath) + 1);
            $archivePath = $slug . '/' . $relativePath;

            if ($file->isDir()) {
                $zip->addEmptyDir($archivePath);
            } else {
                $zip->addFile($filePath, $archivePath);
            }
        }

        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $slug . '.zip"');
        header('Content-Length: ' . filesize($zipPath));
        readfile($zipPath);
        unlink($zipPath);
        exit;
    }

    public function destroy($slug)
    {
        SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            flash('error', 'Método no permitido.');
            header('Location: /musedock/themes');
            exit;
        }

        $slug = trim($slug);
        $password = $_POST['password'] ?? '';

        if (empty($slug)) {
            flash('error', 'Tema no especificado.');
            header('Location: /musedock/themes');
            exit;
        }

        if (!$this->verifyCurrentSuperadminPassword($password)) {
            flash('error', 'La contraseña es incorrecta.');
            header('Location: /musedock/themes');
            exit;
        }

        $pdo = Database::connect();
        $stmt = $pdo->query("SELECT value FROM settings WHERE `key` = 'default_theme' LIMIT 1");
        $currentTheme = $stmt->fetchColumn();

        if ($slug === $currentTheme) {
            flash('error', 'No puedes eliminar el tema activo.');
            header('Location: /musedock/themes');
            exit;
        }

        $themesBase = realpath(APP_ROOT . '/themes');
        if ($themesBase === false) {
            $themesBase = APP_ROOT . '/themes';
        }
        $themePath = realpath($themesBase . '/' . $slug);

        if (!$themePath || !is_dir($themePath) || strpos($themePath, $themesBase) !== 0) {
            flash('error', 'El tema no existe en el sistema de archivos.');
            header('Location: /musedock/themes');
            exit;
        }

        $this->removeDirectory($themePath);

        $stmt = $pdo->prepare("DELETE FROM themes WHERE slug = ?");
        $stmt->execute([$slug]);

        flash('success', "Tema '{$slug}' eliminado correctamente.");
        header('Location: /musedock/themes');
        exit;
    }

public function customize($slug)
{
    SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

    $themePath = realpath(__DIR__ . "/../../../themes/{$slug}");
    error_log("[CUSTOMIZE] Tema recibido: {$slug}");
    error_log("[CUSTOMIZE] Ruta absoluta del tema: {$themePath}");

    if (!$themePath || !is_dir($themePath)) {
        error_log("[CUSTOMIZE] Carpeta no encontrada o inválida.");
        flash('error', 'La carpeta del tema no existe.');
        header('Location: /musedock/themes');
        exit;
    }

    $pdo = Database::connect();
    $stmt = $pdo->prepare("SELECT name FROM themes WHERE slug = ?");
    $stmt->execute([$slug]);
    $name = $stmt->fetchColumn() ?: $slug;

    $editableFiles = [];

    // Etiquetas amigables para archivos conocidos
    $knownLabels = [
        'home.blade.php' => 'Página de inicio',
        'layouts/app.blade.php' => 'Layout principal',
        'layout.blade.php' => 'Layout alternativo',
        'partials/header.blade.php' => 'Header',
        'partials/footer.blade.php' => 'Footer',
        'partials/nav.blade.php' => 'Navegación',
        'partials/sidebar.blade.php' => 'Barra lateral',
        'pages/about.blade.php' => 'Página Sobre nosotros',
        'pages/contact.blade.php' => 'Página de contacto',
        'pages/services.blade.php' => 'Página de servicios',
        'blog/index.blade.php' => 'Blog - Listado',
        'blog/show.blade.php' => 'Blog - Artículo',
    ];

    // Escanear recursivamente todos los archivos .blade.php del tema
    $viewsPath = $themePath . '/views';
    if (is_dir($viewsPath)) {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($viewsPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $fullPath = $file->getRealPath();
                $relativePath = str_replace($viewsPath . '/', '', $fullPath);

                // Usar etiqueta conocida o generar una a partir del nombre del archivo
                $label = $knownLabels[$relativePath] ?? ucfirst(str_replace(['.blade.php', '/', '-', '_'], ['', ' > ', ' ', ' '], $relativePath));

                $editableFiles[] = [
                    'path' => $fullPath,
                    'label' => $label,
                    'relative' => $relativePath,
                ];
            }
        }

        // Ordenar: primero los archivos principales, luego layouts, partials, etc.
        usort($editableFiles, function($a, $b) {
            $order = ['home.blade.php' => 0, 'layouts/' => 1, 'partials/' => 2, 'pages/' => 3, 'blog/' => 4, 'components/' => 5];
            $aOrder = 99;
            $bOrder = 99;

            foreach ($order as $prefix => $pos) {
                if (str_starts_with($a['relative'], $prefix) || $a['relative'] === $prefix) $aOrder = $pos;
                if (str_starts_with($b['relative'], $prefix) || $b['relative'] === $prefix) $bOrder = $pos;
            }

            if ($aOrder !== $bOrder) return $aOrder - $bOrder;
            return strcmp($a['relative'], $b['relative']);
        });
    }

    return View::renderSuperadmin('themes.customize', [
        'title' => 'Editor Visual',
        'theme' => [
            'slug' => $slug,
            'name' => $name,
        ],
        'editableFiles' => $editableFiles,
    ]);
}

public function edit($slug)
{
    SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

    $fileParam = $_GET['file'] ?? null;
    error_log("[EDIT] Tema recibido: {$slug}");
    error_log("[EDIT] Archivo recibido: {$fileParam}");

    if (!$fileParam) {
        error_log("[EDIT] Falta parámetro 'file'.");
        flash('error', 'Falta el archivo a editar.');
        header('Location: /musedock/themes');
        exit;
    }

    // 🔒 SECURITY: Validación robusta contra Path Traversal
    $themePath = __DIR__ . "/../../../themes/{$slug}/views";

    // Verificar que el directorio del tema existe
    if (!is_dir($themePath)) {
        error_log("[EDIT] Tema no válido: {$slug}");
        flash('error', 'Tema no válido.');
        header('Location: /musedock/themes');
        exit;
    }

    // Resolver paths reales
    $realThemePath = realpath($themePath);
    $fullPath = realpath($themePath . '/' . ltrim($fileParam, '/'));

    error_log("[EDIT] Ruta base del tema: {$realThemePath}");
    error_log("[EDIT] Ruta absoluta del archivo: {$fullPath}");

    // Validación estricta con DIRECTORY_SEPARATOR
    if (!$fullPath || !$realThemePath || !str_starts_with($fullPath, $realThemePath . DIRECTORY_SEPARATOR)) {
        error_log("[EDIT] Acceso no permitido al archivo (path traversal detectado).");
        flash('error', 'Acceso denegado.');
        header('Location: /musedock/themes');
        exit;
    }

    // Verificar que existe y es un archivo (no directorio)
    if (!file_exists($fullPath) || !is_file($fullPath)) {
        error_log("[EDIT] Archivo no encontrado o no es un archivo válido: {$fullPath}");
        flash('error', 'Archivo no encontrado.');
        header('Location: /musedock/themes');
        exit;
    }

    $contents = file_get_contents($fullPath);

return View::renderSuperadmin('themes.edit', [
    'title'    => 'Editar archivo del tema',
    'slug'     => $slug,
    'path'     => $fileParam,
    'filename' => basename($fileParam),
    'filepath' => $fileParam,
    'content'  => $contents, // ← CAMBIAMOS la clave a 'content'
]);

}

public function update($slug)
{
    SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

    $fileParam = $_POST['filepath'] ?? null;
    $content   = $_POST['content'] ?? '';

    // 🔒 SECURITY: Validación robusta contra Path Traversal
    $themePath = __DIR__ . "/../../../themes/{$slug}/views";

    // Verificar que el directorio del tema existe
    if (!is_dir($themePath)) {
        flash('error', 'Tema no válido.');
        header("Location: /musedock/theme-editor/{$slug}/customize");
        exit;
    }

    // Resolver paths reales
    $realThemePath = realpath($themePath);
    $fullPath = realpath($themePath . '/' . ltrim($fileParam, '/'));

    // Validación estricta con DIRECTORY_SEPARATOR
    if (!$fileParam || !$fullPath || !$realThemePath || !str_starts_with($fullPath, $realThemePath . DIRECTORY_SEPARATOR)) {
        flash('error', 'Acceso denegado.');
        header("Location: /musedock/theme-editor/{$slug}/customize");
        exit;
    }

    // Verificar que existe y es un archivo
    if (!file_exists($fullPath) || !is_file($fullPath)) {
        flash('error', 'Archivo no encontrado.');
        header("Location: /musedock/theme-editor/{$slug}/customize");
        exit;
    }

    if (!is_writable($fullPath)) {
        flash('error', 'El archivo no es escribible.');
    } else {
        file_put_contents($fullPath, $content);
        flash('success', 'Archivo actualizado correctamente.');
    }

    return View::renderSuperadmin('themes.edit', [
        'title'    => 'Editar archivo del tema',
        'slug'     => $slug,
        'path'     => $fileParam,
        'filename' => basename($fileParam),
        'filepath' => $fileParam,
        'content'  => file_get_contents($fullPath),
    ]);
}

public function builder($slug)
{
    SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

    $fileParam = $_GET['file'] ?? null;

    Logger::log("[BUILDER] Tema recibido: {$slug}", 'DEBUG');
    Logger::log("[BUILDER] Archivo solicitado: {$fileParam}", 'DEBUG');

    if (!$fileParam) {
        flash('error', 'Falta el archivo a editar en el builder visual.');
        header('Location: /musedock/themes');
        exit;
    }

    // 🔒 SECURITY: Validación robusta contra Path Traversal
    $themePath = __DIR__ . "/../../../themes/{$slug}/views";

    // Verificar que el directorio del tema existe
    if (!is_dir($themePath)) {
        Logger::log("[BUILDER] Tema no válido: {$slug}", 'ERROR');
        flash('error', 'Tema no válido.');
        header('Location: /musedock/themes');
        exit;
    }

    // Resolver paths reales
    $realThemePath = realpath($themePath);
    $fullPath = realpath($themePath . '/' . ltrim($fileParam, '/'));

    Logger::log("[BUILDER] Ruta base del tema: {$realThemePath}", 'DEBUG');
    Logger::log("[BUILDER] Ruta absoluta del archivo: {$fullPath}", 'DEBUG');

    // Validación estricta con DIRECTORY_SEPARATOR
    if (!$fullPath || !$realThemePath || !str_starts_with($fullPath, $realThemePath . DIRECTORY_SEPARATOR)) {
        Logger::log("[BUILDER] Acceso denegado (path traversal detectado): {$fullPath}", 'ERROR');
        flash('error', 'Acceso denegado.');
        header('Location: /musedock/themes');
        exit;
    }

    // Verificar que existe y es un archivo
    if (!file_exists($fullPath) || !is_file($fullPath)) {
        Logger::log("[BUILDER] El archivo no existe o no es válido: {$fullPath}", 'ERROR');
        flash('error', 'Archivo no encontrado.');
        header('Location: /musedock/themes');
        exit;
    }

    $html = file_get_contents($fullPath);
    $html = BladeProtector::proteger($html); // ← Protege antes de mostrar

    return View::renderSuperadmin('themes.builder', [
        'title'    => 'Editor Visual',
        'slug'     => $slug,
        'filepath' => $fileParam,
        'filename' => basename($fileParam),
        'html'     => $html,
    ]);
}

public function saveBuilder($slug)
{
    SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

    $filepath = $_POST['filepath'] ?? '';
    $html     = $_POST['html'] ?? '';

    $themePath = realpath(__DIR__ . "/../../../themes/{$slug}/views");
    $fullPath  = realpath($themePath . '/' . ltrim($filepath, '/'));

    Logger::log("[BUILDER-SAVE] Guardando archivo: {$fullPath}", 'INFO');

    if (!$fullPath || !str_starts_with($fullPath, $themePath)) {
        flash('error', 'Ruta inválida o acceso no permitido.');
        header("Location: /musedock/theme-editor/{$slug}/customize");
        exit;
    }

    // Restaurar Blade antes de limpieza
    $restaurado = BladeProtector::restaurar($html);

    // Eliminar <body> envolvente generado por GrapesJS
    $restaurado = preg_replace('/<body[^>]*>/i', '', $restaurado);
    $restaurado = str_replace('</body>', '', $restaurado);

    // Eliminar <style> añadido por GrapesJS
    $restaurado = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $restaurado);

    // Guardar limpio
    file_put_contents($fullPath, trim($restaurado));

    flash('success', 'Cambios guardados correctamente.');
    header("Location: /musedock/theme-editor/{$slug}/builder?file=" . urlencode($filepath));
    exit;
}
public function preview($slug)
{
    SessionSecurity::startSession();
        $this->checkPermission('appearance.themes');

    $fileParam = $_GET['file'] ?? 'home.blade.php';

    Logger::log("[PREVIEW] Tema recibido: {$slug}", 'DEBUG');
    Logger::log("[PREVIEW] Archivo solicitado: {$fileParam}", 'DEBUG');

    $themePath = realpath(__DIR__ . "/../../../themes/{$slug}/views");
    $fullPath  = realpath($themePath . '/' . ltrim($fileParam, '/'));

    Logger::log("[PREVIEW] Ruta base del tema: {$themePath}", 'DEBUG');
    Logger::log("[PREVIEW] Ruta absoluta del archivo: {$fullPath}", 'DEBUG');

    if (!$fullPath || !str_starts_with($fullPath, $themePath)) {
        Logger::log("[PREVIEW] Acceso denegado a: {$fullPath}", 'ERROR');
        echo "Acceso denegado.";
        exit;
    }

    if (!file_exists($fullPath)) {
        Logger::log("[PREVIEW] El archivo no existe: {$fullPath}", 'ERROR');
        echo "Archivo no encontrado.";
        exit;
    }

    // Convertir a notación Blade (home.blade.php → home)
    $viewName = str_replace(['/', '.blade.php'], ['.', ''], str_replace($themePath . '/', '', $fullPath));
    Logger::log("[PREVIEW] Nombre de la vista Blade: {$viewName}", 'DEBUG');

    // Cargar configuración del tema
    $themeRoot = realpath(__DIR__ . "/../../../themes/{$slug}");
    $configFile = $themeRoot . '/theme.json';
    $config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];

    // Asegurar que bootstrap sea true si está en la configuración
    if (isset($config['bootstrap']) && $config['bootstrap']) {
        $config['bootstrap'] = true;
    }

    Logger::log("[PREVIEW] Configuración del tema cargada: " . json_encode($config), 'DEBUG');
    Logger::log("[PREVIEW] Bootstrap habilitado: " . ($config['bootstrap'] ?? false ? 'true' : 'false'), 'DEBUG');

    // Pasar datos al renderTheme y asegurar themeConfig global
    try {
        Logger::log("[PREVIEW] Renderizando vista con renderTheme(): {$viewName}", 'DEBUG');
        echo \Screenart\Musedock\View::renderTheme($viewName, [
            'slug' => $slug,
            'themeConfigData' => $config,
        ]);
    } catch (\Exception $e) {
        Logger::log("[PREVIEW] Error al renderizar con View::renderTheme: " . $e->getMessage(), 'ERROR');
        echo "Error al renderizar la vista: " . $e->getMessage();
    }
}

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }

    private function verifyCurrentSuperadminPassword(?string $password): bool
    {
        if (empty($password)) {
            return false;
        }

        $user = SessionSecurity::getAuthenticatedUser();
        if (!$user || ($user['type'] ?? '') !== 'super_admin') {
            return false;
        }

        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT password FROM super_admins WHERE id = ?");
        $stmt->execute([$user['id']]);
        $hash = $stmt->fetchColumn();

        return $hash && password_verify($password, $hash);
    }
}

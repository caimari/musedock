<?php

namespace Elements\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Security\SessionSecurity;
use Elements\Models\Element;
use Elements\Models\ElementSetting;
use Screenart\Musedock\Traits\RequiresPermission;
use Screenart\Musedock\Env;
use MediaManager\Models\Media;

/**
 * ElementController - Superadmin
 *
 * Manages global elements available to all tenants
 */
class ElementController
{
    use RequiresPermission;

    /**
     * Verificar si el usuario actual tiene un permiso específico
     * Si no lo tiene, redirige con mensaje de error
     */
    private function checkPermission(string $permission): void
    {
        if (!userCan($permission)) {
            flash('error', __element('element.error') . ': Acceso denegado');
            header('Location: ' . admin_url('dashboard'));
            exit;
        }
    }

    /**
     * List global elements
     */
    public function index()
    {
        $this->checkPermission('elements.view');
        SessionSecurity::startSession();

        $elements = Element::getByTenant(null, false); // Only global elements

        return View::renderModule('elements', 'superadmin/elements/index', [
            'title' => __element('element.elements'),
            'elements' => $elements,
            'types' => Element::getAvailableTypes()
        ]);
    }

    /**
     * Create form
     */
    public function create()
    {
        $this->checkPermission('elements.create');
        SessionSecurity::startSession();

        return View::renderModule('elements', 'superadmin/elements/create', [
            'title' => __element('element.create'),
            'types' => Element::getAvailableTypes(),
            'heroLayouts' => Element::getHeroLayouts(),
            'highlightLayouts' => Element::getHighlightLayouts(),
            'dividerLayouts' => Element::getDividerLayouts(),
            'faqLayouts' => Element::getFaqLayouts(),
            'ctaLayouts' => Element::getCtaLayouts()
        ]);
    }

    /**
     * Store new global element
     */
    public function store()
    {
        $this->checkPermission('elements.create');
        SessionSecurity::startSession();

        // Validation
        $errors = $this->validateElement($_POST, null);

        if (!empty($errors)) {
            flash('error', implode('<br>', $errors));
            $_SESSION['_old_input'] = $_POST;
            header('Location: ' . route('elements.create'));
            exit;
        }

        // Generate unique slug
        $baseSlugInput = trim($_POST['slug'] ?? '');
        $slugSource = $baseSlugInput !== '' ? $baseSlugInput : ($_POST['name'] ?? '');
        $slug = Element::generateUniqueSlug($slugSource, null);

        // Parse data
        $data = [];
        if (!empty($_POST['data'])) {
            $data = is_array($_POST['data'])
                ? $_POST['data']
                : json_decode($_POST['data'], true) ?? [];
        }

        // Parse settings
        $settings = [];
        if (!empty($_POST['settings'])) {
            $settings = is_array($_POST['settings'])
                ? $_POST['settings']
                : json_decode($_POST['settings'], true) ?? [];
        }

        // Create global element (tenant_id = NULL)
        $element = Element::create([
            'tenant_id' => null,
            'name' => trim($_POST['name']),
            'slug' => $slug,
            'description' => trim($_POST['description'] ?? ''),
            'type' => trim($_POST['type']),
            'layout_type' => trim($_POST['layout_type'] ?? ''),
            'data' => $data,
            'settings' => $settings,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'featured' => isset($_POST['featured']) ? 1 : 0,
            'sort_order' => (int)($_POST['sort_order'] ?? 0)
        ]);

        if ($element) {
            flash('success', __element('element.created'));
            header('Location: ' . route('elements.edit', ['id' => $element->id]));
            exit;
        }

        flash('error', __element('element.error_creating'));
        header('Location: ' . route('elements.create'));
        exit;
    }

    /**
     * Edit form
     */
    public function edit(int $id)
    {
        $this->checkPermission('elements.edit');
        SessionSecurity::startSession();

        $element = Element::find($id);

        if (!$element) {
            flash('error', __element('element.not_found'));
            header('Location: ' . route('elements.index'));
            exit;
        }

        // Superadmin can only edit global elements
        if (!$element->isGlobal()) {
            flash('error', __element('element.not_found'));
            header('Location: ' . route('elements.index'));
            exit;
        }

        return View::renderModule('elements', 'superadmin/elements/edit', [
            'title' => __element('element.edit'),
            'element' => $element,
            'types' => Element::getAvailableTypes(),
            'heroLayouts' => Element::getHeroLayouts(),
            'highlightLayouts' => Element::getHighlightLayouts(),
            'dividerLayouts' => Element::getDividerLayouts(),
            'faqLayouts' => Element::getFaqLayouts(),
            'ctaLayouts' => Element::getCtaLayouts()
        ]);
    }

    /**
     * Update element
     */
    public function update(int $id)
    {
        $this->checkPermission('elements.edit');
        SessionSecurity::startSession();

        $element = Element::find($id);

        if (!$element) {
            flash('error', __element('element.not_found'));
            header('Location: ' . route('elements.index'));
            exit;
        }

        // Superadmin can only update global elements
        if (!$element->isGlobal()) {
            flash('error', __element('element.not_found'));
            header('Location: ' . route('elements.index'));
            exit;
        }

        // Validation
        $errors = $this->validateElement($_POST, null, $id);

        if (!empty($errors)) {
            flash('error', implode('<br>', $errors));
            $_SESSION['_old_input'] = $_POST;
            header('Location: ' . route('elements.edit', ['id' => $id]));
            exit;
        }

        // Generate slug if changed
        $baseSlugInput = trim($_POST['slug'] ?? '');
        $slugSource = $baseSlugInput !== '' ? $baseSlugInput : ($_POST['name'] ?? '');
        $slug = ($slugSource !== $element->slug)
            ? Element::generateUniqueSlug($slugSource, null, $id)
            : $element->slug;

        // Parse data
        $data = [];
        if (!empty($_POST['data'])) {
            $data = is_array($_POST['data'])
                ? $_POST['data']
                : json_decode($_POST['data'], true) ?? [];
        }

        // Parse settings
        $settings = [];
        if (!empty($_POST['settings'])) {
            $settings = is_array($_POST['settings'])
                ? $_POST['settings']
                : json_decode($_POST['settings'], true) ?? [];
        }

        // Update
        $updated = Element::query()->where('id', $id)->update([
            'name' => trim($_POST['name']),
            'slug' => $slug,
            'description' => trim($_POST['description'] ?? ''),
            'type' => trim($_POST['type']),
            'layout_type' => trim($_POST['layout_type'] ?? ''),
            'data' => json_encode($data),
            'settings' => json_encode($settings),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'featured' => isset($_POST['featured']) ? 1 : 0,
            'sort_order' => (int)($_POST['sort_order'] ?? 0)
        ]);

        if ($updated) {
            flash('success', __element('element.updated'));
            if (function_exists('clear_old_input')) {
                clear_old_input();
            }
        } else {
            flash('error', __element('element.error'));
        }

        header('Location: ' . route('elements.edit', ['id' => $id]));
        exit;
    }

    /**
     * Delete element
     */
    public function destroy(int $id)
    {
        $this->checkPermission('elements.delete');
        SessionSecurity::startSession();

        $element = Element::find($id);

        if (!$element) {
            flash('error', __element('element.not_found'));
            header('Location: ' . route('elements.index'));
            exit;
        }

        // Superadmin can only delete global elements
        if (!$element->isGlobal()) {
            flash('error', __element('element.not_found'));
            header('Location: ' . route('elements.index'));
            exit;
        }

        if (Element::query()->where('id', $id)->delete()) {
            flash('success', __element('element.deleted'));
        } else {
            flash('error', __element('element.error'));
        }

        header('Location: ' . route('elements.index'));
        exit;
    }

    /**
     * Validate element data
     */
    private function validateElement(array $data, ?int $tenantId = null, ?int $excludeId = null): array
    {
        $errors = [];

        // Name
        if (empty($data['name'])) {
            $errors[] = __element('validation.name_required');
        } elseif (mb_strlen($data['name']) > 255) {
            $errors[] = __element('validation.name_too_long');
        }

        // Type
        if (empty($data['type'])) {
            $errors[] = __element('validation.type_required');
        } elseif (!in_array($data['type'], array_keys(Element::getAvailableTypes()))) {
            $errors[] = __element('validation.type_invalid');
        }

        // Slug (if provided manually)
        if (!empty($data['slug'])) {
            if (!preg_match('/^[a-z0-9\-]+$/', $data['slug'])) {
                $errors[] = __element('validation.slug_invalid');
            }
        }

        return $errors;
    }

    /**
     * Check slug availability (AJAX)
     */
    public function checkSlug()
    {
        header('Content-Type: application/json');

        $slug = trim($_POST['slug'] ?? '');
        $excludeId = isset($_POST['exclude_id']) ? (int)$_POST['exclude_id'] : null;

        if (empty($slug)) {
            echo json_encode(['available' => false, 'message' => 'Slug is required']);
            exit;
        }

        // Validate slug format
        if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
            echo json_encode(['available' => false, 'message' => __element('validation.slug_invalid')]);
            exit;
        }

        // Check if slug exists for global elements
        $query = Element::query()->where('slug', $slug);
        $query->whereRaw('(tenant_id IS NULL OR tenant_id = 0)');

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        $exists = $query->exists();

        echo json_encode([
            'available' => !$exists,
            'message' => $exists ? __element('validation.slug_exists') : 'Slug is available'
        ]);
        exit;
    }

    /**
     * Upload image for element (AJAX)
     */
    public function uploadImage()
    {
        header('Content-Type: application/json');
        SessionSecurity::startSession();

        if (!userCan('elements.create') && !userCan('elements.edit')) {
            echo json_encode(['success' => false, 'message' => 'Permission denied']);
            exit;
        }

        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido',
                UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo del formulario',
                UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
                UPLOAD_ERR_NO_FILE => 'No se seleccionó ningún archivo',
                UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal',
                UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo',
                UPLOAD_ERR_EXTENSION => 'Extensión de archivo no permitida'
            ];
            $errorCode = $_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE;
            $message = $errorMessages[$errorCode] ?? 'Error al subir la imagen';
            echo json_encode(['success' => false, 'message' => $message]);
            exit;
        }

        $file = $_FILES['image'];

        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!in_array($mimeType, $allowedTypes)) {
            echo json_encode(['success' => false, 'message' => 'Tipo de archivo no permitido. Solo se permiten: JPG, PNG, GIF, WEBP']);
            exit;
        }

        // Validate file size (max 10MB)
        $maxSize = 10 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            echo json_encode(['success' => false, 'message' => 'El archivo es demasiado grande. Máximo: 10MB']);
            exit;
        }

        $defaultDisk = (string) Env::get('FILESYSTEM_DISK', 'media');
        if ($defaultDisk === 'local') {
            $defaultDisk = 'media';
        }
        $diskName = $this->getDiskNameForContext($_POST['disk'] ?? $defaultDisk);

        try {
            [$filesystem, $localRoot] = $this->createFilesystemForDisk($diskName);

            $safeBase = $this->slugifyFileBase(pathinfo($file['name'], PATHINFO_FILENAME));
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $safeFilename = $safeBase . '_' . uniqid() . '.' . $extension;

            $subPath = "global/elements/hero";
            $yearMonth = date('Y/m');
            $relativePath = "{$subPath}/{$yearMonth}/{$safeFilename}";
            $dirPath = dirname($relativePath);

            if (!$filesystem->directoryExists($dirPath)) {
                $filesystem->createDirectory($dirPath);
                if ($localRoot) {
                    @chmod($localRoot . '/' . $dirPath, 0755);
                }
            }

            $stream = fopen($file['tmp_name'], 'r+');
            if (!$stream) {
                echo json_encode(['success' => false, 'message' => 'No se pudo abrir el archivo temporal']);
                exit;
            }

            $filesystem->writeStream($relativePath, $stream, $this->buildWriteConfig($diskName, $mimeType));
            fclose($stream);

            if ($localRoot) {
                @chmod($localRoot . '/' . $relativePath, 0644);
            }

            $userId = $_SESSION['super_admin']['id'] ?? ($_SESSION['admin']['id'] ?? ($_SESSION['user']['id'] ?? null));
            $publicToken = Media::generatePublicToken();
            $slug = Media::generateSlug($file['name']);
            $seoFilename = $slug . '-' . $publicToken . '.' . $extension;

            $media = Media::create([
                'tenant_id' => null,
                'user_id' => $userId,
                'folder_id' => null,
                'disk' => $diskName,
                'path' => $relativePath,
                'public_token' => $publicToken,
                'slug' => $slug,
                'seo_filename' => $seoFilename,
                'filename' => $file['name'],
                'mime_type' => $mimeType,
                'size' => $file['size'],
                'metadata' => null,
            ]);

            echo json_encode([
                'success' => true,
                'url' => '/media/t/' . $publicToken,
                'token' => $publicToken,
                'disk' => $diskName,
                'media_id' => $media->id ?? null,
                'filename' => $file['name'],
            ]);
        } catch (\Throwable $e) {
            error_log("Elements superadmin uploadImage error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error al guardar la imagen']);
        }
        exit;
    }

    private function slugifyFileBase(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = @iconv('utf-8', 'us-ascii//TRANSLIT', $text) ?: $text;
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        return $text !== '' ? $text : 'file';
    }

    private function getDiskNameForContext(?string $requestedDisk): string
    {
        $validDisks = ['local', 'media', 'r2', 's3'];
        $diskName = in_array((string)$requestedDisk, $validDisks, true) ? (string)$requestedDisk : 'media';

        $filesystemsConfig = require APP_ROOT . '/config/filesystems.php';
        $diskConfig = $filesystemsConfig['disks'][$diskName] ?? null;
        if (!$diskConfig || !is_array($diskConfig)) {
            return 'media';
        }

        if (is_array($diskConfig) && ($diskConfig['driver'] ?? null) === 's3') {
            if (empty($diskConfig['key']) || empty($diskConfig['secret']) || empty($diskConfig['bucket'])) {
                return 'media';
            }
        }

        return $diskName;
    }

    private function createFilesystemForDisk(string $diskName): array
    {
        $filesystemsConfig = require APP_ROOT . '/config/filesystems.php';
        $diskConfig = $filesystemsConfig['disks'][$diskName] ?? null;
        if (!$diskConfig || !is_array($diskConfig)) {
            $diskName = 'media';
            $diskConfig = $filesystemsConfig['disks']['media'] ?? [];
        }

        if (($diskConfig['driver'] ?? 'local') === 's3') {
            $filesystem = $this->createS3Filesystem($diskConfig);
            if (!$filesystem) {
                throw new \RuntimeException("No se pudo conectar con el almacenamiento ({$diskName})");
            }
            return [$filesystem, null];
        }

        $localRoot = APP_ROOT . ($diskConfig['root'] ?? '/storage/app/media');
        $adapter = new \League\Flysystem\Local\LocalFilesystemAdapter(
            $localRoot,
            null,
            LOCK_EX,
            \League\Flysystem\Local\LocalFilesystemAdapter::DISALLOW_LINKS
        );
        $filesystem = new \League\Flysystem\Filesystem($adapter);
        return [$filesystem, $localRoot];
    }

    private function buildWriteConfig(string $diskName, string $mimeType): array
    {
        $filesystemsConfig = require APP_ROOT . '/config/filesystems.php';
        $diskConfig = $filesystemsConfig['disks'][$diskName] ?? [];

        $cfg = [
            'visibility' => 'public',
        ];

        if (($diskConfig['driver'] ?? null) === 's3') {
            $cfg['ContentType'] = $mimeType ?: 'application/octet-stream';
            $cfg['CacheControl'] = 'public, max-age=31536000, immutable';
        }

        return $cfg;
    }

    private function createS3Filesystem(array $config): ?\League\Flysystem\Filesystem
    {
        try {
            if (empty($config['key']) || empty($config['secret']) || empty($config['bucket'])) {
                return null;
            }

            $clientConfig = [
                'version' => 'latest',
                'region' => $config['region'] ?? 'auto',
                'credentials' => [
                    'key' => $config['key'],
                    'secret' => $config['secret'],
                ],
            ];

            if (!empty($config['endpoint'])) {
                $clientConfig['endpoint'] = $config['endpoint'];
            }
            if (isset($config['use_path_style_endpoint'])) {
                $clientConfig['use_path_style_endpoint'] = $config['use_path_style_endpoint'];
            }

            $client = new \Aws\S3\S3Client($clientConfig);
            $adapter = new \League\Flysystem\AwsS3V3\AwsS3V3Adapter(
                $client,
                $config['bucket'],
                '',
                null,
                null,
                ['ACL' => 'public-read']
            );

            return new \League\Flysystem\Filesystem($adapter);
        } catch (\Throwable $e) {
            error_log("Elements superadmin createS3Filesystem error: " . $e->getMessage());
            return null;
        }
    }
}

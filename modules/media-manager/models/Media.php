<?php
// Definir el namespace correcto basado en tu composer.json y la estructura
namespace MediaManager\Models; // <-- Namespace CORTO

use Screenart\Musedock\Database\Model; // <-- Necesitas 'use' para clases FUERA

// Importar otros modelos necesarios para relaciones
use Screenart\Musedock\Models\User; // Asumiendo modelo User genérico
use Screenart\Musedock\Models\Tenant; // Asumiendo modelo Tenant
use MediaManager\Models\Folder; // Modelo de carpetas

class Media extends Model
{
    protected static string $table = 'media';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'tenant_id', 'user_id', 'folder_id', 'disk', 'path', 'public_token',
        'slug', 'seo_filename', 'filename', 'mime_type', 'size', 'alt_text', 'caption', 'metadata'
    ];

    protected array $casts = [
        'tenant_id' => 'nullable|integer',
        'user_id'   => 'nullable|integer',
        'folder_id' => 'nullable|integer',
        'size'      => 'integer',
        'metadata'  => 'json', // Castear JSON automáticamente
    ];

    /**
     * Obtiene la URL pública completa del archivo.
     * Soporta múltiples discos de almacenamiento:
     * - 'local': Archivos en /public/assets/uploads/ (legacy)
     * - 'media': Archivos en /storage/app/media/ (nuevo, seguro)
     * - 'r2': Cloudflare R2 CDN
     * - 's3': Amazon S3
     *
     * @param bool $seoFriendly Si es true, devuelve URL SEO-friendly (default: true)
     * @return string
     */
    public function getPublicUrl(bool $seoFriendly = true): string
    {
        try {
            $path = ltrim($this->path, '/');
            $diskConfig = config("filesystems.disks.{$this->disk}");

            switch ($this->disk) {
                case 'r2':
                case 's3':
                    // Preferir URLs SEO/token para enlaces compartibles/estables (redirigen a CDN).
                    if ($seoFriendly && !empty($this->seo_filename)) {
                        return $this->getSeoUrl();
                    }
                    // CDN/Cloud storage: usar URL configurada
                    if (!empty($diskConfig['url'])) {
                        return rtrim($diskConfig['url'], '/') . '/' . $path;
                    }
                    // Fallback: si no hay URL configurada, usar endpoint
                    if (!empty($diskConfig['endpoint']) && !empty($diskConfig['bucket'])) {
                        return rtrim($diskConfig['endpoint'], '/') . '/' . $diskConfig['bucket'] . '/' . $path;
                    }
                    break;

                case 'media':
                    // Nuevo sistema con URLs SEO-friendly
                    if ($seoFriendly && !empty($this->seo_filename)) {
                        return $this->getSeoUrl();
                    }
                    // Fallback a URL con token (para compatibilidad)
                    return '/media/t/' . $this->public_token;

                case 'local':
                    // Sistema legacy: puede usar SEO-friendly URL que redirige
                    if ($seoFriendly && !empty($this->seo_filename)) {
                        return $this->getSeoUrl();
                    }
                    // Fallback: archivos directamente en /public/
                    return '/assets/uploads/' . $path;

                default:
                    // Otros discos: URL directa
                    return '/assets/uploads/' . $path;
            }

        } catch (\Exception $e) {
            error_log("Error generando URL para media {$this->id}: " . $e->getMessage());
        }

        return '#error-url'; // URL inválida si falla
    }

    /**
     * Obtiene la URL SEO-friendly del archivo.
     * Formato: /media/p/{slug}-{token}/{ext}
     * Ejemplo: /media/p/mi-imagen-aBcD1234EfGh5678/jpg
     *
     * Nota: No usamos extensión con punto (.jpg) porque nginx intercepta
     * esas URLs como archivos estáticos. Usamos /ext al final.
     *
     * Esta URL es:
     * - SEO-friendly: contiene palabras clave descriptivas
     * - Segura: el token impide enumerar archivos
     * - Compartible: bonita para redes sociales
     * - Compatible con nginx: no tiene extensión de archivo
     *
     * @return string
     */
    public function getSeoUrl(): string
    {
        // Generar slug si no existe
        if (empty($this->slug)) {
            $this->slug = static::generateSlug($this->filename);
        }

        // Obtener extensión
        $extension = strtolower(pathinfo($this->filename, PATHINFO_EXTENSION));

        // Formato: /media/p/{slug}-{token}/{extension}
        return '/media/p/' . $this->slug . '-' . $this->public_token . '/' . $extension;
    }

    /**
     * Obtiene la URL SEO-friendly alternativa con extensión real (para sitemap/og:image)
     * Formato: /media/p/{slug}-{token}.{ext}
     *
     * ADVERTENCIA: Esta URL puede no funcionar si nginx intercepta archivos estáticos.
     * Usar getSeoUrl() para URLs que funcionan con nginx.
     *
     * @return string
     */
    public function getSeoUrlWithExtension(): string
    {
        if (empty($this->seo_filename)) {
            $this->generateSeoFilename();
        }

        return '/media/p/' . $this->seo_filename;
    }

    /**
     * Genera un slug SEO-friendly a partir del nombre del archivo
     *
     * @param string|null $filename Nombre del archivo (usa $this->filename si no se proporciona)
     * @return string
     */
    public static function generateSlug(?string $filename = null): string
    {
        if (empty($filename)) {
            return '';
        }

        // Obtener nombre sin extensión
        $baseName = pathinfo($filename, PATHINFO_FILENAME);

        // Convertir a minúsculas
        $slug = mb_strtolower($baseName, 'UTF-8');

        // Transliterar caracteres especiales (ñ -> n, á -> a, etc.)
        $transliterations = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u',
            'ñ' => 'n', 'ç' => 'c', 'ß' => 'ss',
        ];
        $slug = strtr($slug, $transliterations);

        // Reemplazar cualquier caracter no alfanumérico por guión
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

        // Eliminar guiones múltiples
        $slug = preg_replace('/-+/', '-', $slug);

        // Eliminar guiones al inicio y final
        $slug = trim($slug, '-');

        // Limitar longitud
        if (strlen($slug) > 100) {
            $slug = substr($slug, 0, 100);
            $slug = rtrim($slug, '-');
        }

        return $slug ?: 'file';
    }

    /**
     * Genera el nombre de archivo SEO-friendly completo
     * Formato: {slug}-{token}.{extension}
     *
     * @return string
     */
    public function generateSeoFilename(): string
    {
        // Generar slug si no existe
        if (empty($this->slug)) {
            $this->slug = static::generateSlug($this->filename);
        }

        // Obtener extensión
        $extension = pathinfo($this->filename, PATHINFO_EXTENSION);
        $extension = strtolower($extension);

        // Construir seo_filename: slug-token.ext
        $this->seo_filename = $this->slug . '-' . $this->public_token . '.' . $extension;

        return $this->seo_filename;
    }

    /**
     * Busca un media por su seo_filename (extrayendo el token)
     *
     * @param string $seoFilename Nombre SEO del archivo (ej: mi-imagen-aBcD1234EfGh5678.jpg)
     * @return static|null
     */
    public static function findBySeoFilename(string $seoFilename): ?self
    {
        if (empty($seoFilename)) {
            return null;
        }

        // Extraer token del seo_filename
        // Formato: {slug}-{token}.{ext}
        // El token son los últimos 16 caracteres antes del punto
        if (!preg_match('/^(.+)-([a-zA-Z0-9]{16,32})\.(\w+)$/', $seoFilename, $matches)) {
            return null;
        }

        $token = $matches[2];

        return static::findByToken($token);
    }

    /**
     * Obtiene la URL del thumbnail/miniatura.
     * Soporta múltiples discos de almacenamiento.
     *
     * @return string
     */
    public function getThumbnailUrl(): string
    {
        try {
            $meta = is_array($this->metadata) ? $this->metadata : (is_string($this->metadata) ? json_decode($this->metadata, true) : []);
            $thumbPath = $meta['thumbnail']['path'] ?? null;

            $path = ltrim($this->path, '/');

            switch ($this->disk) {
                case 'r2':
                case 's3':
                    if ($thumbPath) {
                        $diskConfig = config("filesystems.disks.{$this->disk}");
                        $thumbPath = ltrim((string)$thumbPath, '/');
                        if (!empty($diskConfig['url'])) {
                            return rtrim($diskConfig['url'], '/') . '/' . $thumbPath;
                        }
                        if (!empty($diskConfig['endpoint']) && !empty($diskConfig['bucket'])) {
                            return rtrim($diskConfig['endpoint'], '/') . '/' . $diskConfig['bucket'] . '/' . $thumbPath;
                        }
                    }
                    // Fallback: misma URL
                    return $this->getPublicUrl();

                case 'media':
                    // Nuevo sistema: usar URL con token seguro
                    return '/media/t/' . $this->public_token;

                case 'local':
                default:
                    // Legacy: misma URL que el original
                    return $this->getPublicUrl();
            }

        } catch (\Exception $e) {
            return $this->getPublicUrl();
        }
    }

    /**
     * Obtiene la ruta física completa del archivo.
     *
     * @return string|null
     */
    public function getFullPath(): ?string
    {
        try {
            $diskConfig = config("filesystems.disks.{$this->disk}");
            if (!$diskConfig) {
                return null;
            }

            $root = APP_ROOT . $diskConfig['root'];
            return $root . '/' . ltrim($this->path, '/');

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Relación con el usuario que subió el archivo (opcional).
     */
    public function uploader()
    {
        // Asume que tienes un modelo User y relación belongsTo
        // return $this->belongsTo(User::class, 'user_id');
         return null;
    }

    /**
     * Relación con el tenant al que pertenece (opcional).
     */
    public function tenant()
    {
         // Asume que tienes un modelo Tenant y relación belongsTo
        // return $this->belongsTo(Tenant::class, 'tenant_id');
         return null;
    }

    /**
     * Relación con la carpeta que contiene este archivo
     */
    public function folder()
    {
        if (!$this->folder_id) {
            return null;
        }
        return Folder::find($this->folder_id);
    }

    /**
     * Mueve este archivo a otra carpeta
     * @param int|null $folderId
     * @return bool
     */
    public function moveToFolder(?int $folderId): bool
    {
        $this->folder_id = $folderId;
        return $this->save();
    }

    /**
     * Copia este archivo a otra carpeta
     * @param int|null $folderId
     * @return Media|null
     */
    public function copyToFolder(?int $folderId): ?Media
    {
        try {
            $copy = static::create([
                'tenant_id' => $this->tenant_id,
                'user_id' => $this->user_id,
                'folder_id' => $folderId,
                'disk' => $this->disk,
                'path' => $this->generateCopyPath(),
                'public_token' => static::generatePublicToken(),
                'filename' => $this->generateCopyFilename(),
                'mime_type' => $this->mime_type,
                'size' => $this->size,
                'alt_text' => $this->alt_text,
                'caption' => $this->caption,
                'metadata' => $this->metadata
            ]);

            // Copiar archivo físico usando el disco correcto
            $sourcePath = $this->getFullPath();
            $diskRoot = config("filesystems.disks.{$this->disk}.root", '/public/assets/uploads');
            $destPath = APP_ROOT . $diskRoot . '/' . $copy->path;

            if ($sourcePath && file_exists($sourcePath)) {
                $destDir = dirname($destPath);
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0755, true);
                }
                copy($sourcePath, $destPath);
            }

            return $copy;
        } catch (\Exception $e) {
            error_log("Error copying media {$this->id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Genera un nombre de archivo para la copia
     * @return string
     */
    private function generateCopyFilename(): string
    {
        $info = pathinfo($this->filename);
        $basename = $info['filename'];
        $extension = isset($info['extension']) ? '.' . $info['extension'] : '';

        return $basename . '-copy-' . time() . $extension;
    }

    /**
     * Genera una ruta para la copia del archivo
     * @return string
     */
    private function generateCopyPath(): string
    {
        $info = pathinfo($this->path);
        $dirname = $info['dirname'];
        $basename = $info['filename'];
        $extension = isset($info['extension']) ? '.' . $info['extension'] : '';

        return $dirname . '/' . $basename . '-copy-' . time() . $extension;
    }

    /**
     * Genera un token único para acceso público
     * 16 caracteres alfanuméricos = 62^16 combinaciones (~4.7 x 10^28)
     * Imposible de enumerar o adivinar
     *
     * @return string
     */
    public static function generatePublicToken(): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $maxAttempts = 10;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $token = '';
            for ($i = 0; $i < 16; $i++) {
                $token .= $chars[random_int(0, 61)];
            }

            // Verificar que no existe
            $existing = static::findByToken($token);
            if (!$existing) {
                return $token;
            }
        }

        // Fallback con más entropía
        return bin2hex(random_bytes(8));
    }

    /**
     * Busca un media por su token público
     *
     * @param string $token
     * @return static|null
     */
    public static function findByToken(string $token): ?self
    {
        if (empty($token) || strlen($token) < 8) {
            return null;
        }

        // Usar QueryBuilder para obtener el row, luego hidratar manualmente
        // QueryBuilder::where() toma 2 argumentos: (columna, valor) - el = es implícito
        $row = \Screenart\Musedock\Database::table(static::$table)
            ->where('public_token', $token)
            ->first();

        return $row ? new static($row) : null;
    }

}

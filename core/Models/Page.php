<?php
namespace Screenart\Musedock\Models;

use Screenart\Musedock\Database\Model;
use Screenart\Musedock\Models\PageTranslation;
use Screenart\Musedock\Models\SuperAdmin;
use Screenart\Musedock\Models\Admin;
use Screenart\Musedock\Models\User;
use Screenart\Musedock\Models\Tenant;
use Screenart\Musedock\Database; 
use Carbon\Carbon; // Importar Carbon


class Page extends Model
{
    protected static string $table = 'pages';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;
    
    /**
     * Atributos que se pueden asignar masivamente.
     * Nota: Eliminado 'prefix' porque no existe en la tabla pages
     */
    protected array $fillable = [
        'tenant_id',
        'user_id',
        'user_type',
        'title',
        'slug',
        'content',
        'status',
		'visibility',
        'published_at',
        'base_locale',
        'is_homepage', 
        // --- Campos SEO ---
        'seo_title',
        'seo_description',
        'seo_keywords',
        'seo_image',
        'canonical_url',
        'robots_directive',
        'twitter_title',
        'twitter_description',
        'twitter_image',
        // --- Campos de cabecera y slider ---
        'show_slider',
        'slider_image',
        'slider_title',
        'slider_content',
        'hide_title',
    ];
    
    /**
     * Conversión de tipos para los atributos.
     */
    protected array $casts = [
        'id'           => 'int',
        'user_id'      => 'int',
        'tenant_id'    => 'nullable', // Evita la conversión a entero
        'status'       => 'string',   // Asegura que sea string
		'visibility'   => 'string', 
        'published_at' => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
        'is_homepage'  => 'boolean',  // Convierte 0/1 a false/true
        'show_slider'  => 'boolean',  // Convierte 0/1 a false/true
        'hide_title'   => 'boolean',  // Convierte 0/1 a false/true
    ];
    
    /**
     * Relación con tenant (opcional).
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
    
    /**
     * Devuelve el autor en función del tipo de usuario.
     */
    public function getAuthor()
    {
        return match ($this->user_type) {
            'superadmin' => SuperAdmin::find($this->user_id),
            'admin'      => Admin::find($this->user_id),
            'user'       => User::find($this->user_id),
            default      => null,
        };
    }
    
    /**
     * Obtiene todas las traducciones disponibles de la página.
     */
    public function translations()
    {
        return PageTranslation::where('page_id', $this->id)->get();
    }
    
    /**
     * Devuelve la traducción para un idioma específico.
     */
    public function translation(string $locale): ?PageTranslation
    {
        return PageTranslation::where('page_id', $this->id)
            ->where('locale', $locale)
            ->first();
    }
    
    /**
     * Verifica si existe una traducción para un idioma dado.
     */
    public function hasTranslation(string $locale): bool
    {
        return PageTranslation::where('page_id', $this->id)
            ->where('locale', $locale)
            ->exists();
    }
    
    /**
     * Devuelve el contenido traducido si existe, o el original.
     */
    public function translatedOrDefault(string $locale): array
    {
        $t = $this->translation($locale);
        return [
            'title'   => $t?->title ?? $this->title,
            'content' => $t?->content ?? $this->content,
        ];
    }
    
    /**
     * Obtiene el slug asociado a esta página
     */
    public function getSlug()
    {
        try {
            $pdo = \Screenart\Musedock\Database::connect();
            $stmt = $pdo->prepare("SELECT * FROM slugs WHERE module = 'pages' AND reference_id = ?");
            $stmt->execute([$this->id]);
            return $stmt->fetch(\PDO::FETCH_OBJ);
        } catch (\Exception $e) {
            // Log del error
            error_log("Error al obtener slug para page ID {$this->id}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obtiene el prefix asociado al slug de esta página
     */
    public function getPrefix(): string
    {
        $slug = $this->getSlug();
        return $slug ? ($slug->prefix ?? 'p') : 'p';
    }
    
    /**
     * Obtiene la URL pública de la página
     */
    public function getPublicUrl(): string
    {
        $slug = $this->getSlug();
        if (!$slug) return '#';
        
        $host = $_SERVER['HTTP_HOST'] ?? env('APP_URL', 'localhost');
        $prefix = $slug->prefix ?? 'p';
        
        return "https://{$host}/{$prefix}/{$slug->slug}";
    }
    
    /**
     * Sobrescribe el método de guardado para manejar correctamente tenant_id
     * y también para asegurar las fechas de creación/actualización
     */
    public function save(): bool
    {
        error_log("PAGE SAVE: Iniciando save() del modelo Page");
        error_log("PAGE SAVE: Attributes: " . json_encode(array_keys($this->attributes)));

        // Si tenant_id es una cadena vacía o null, lo forzamos a NULL explícitamente
        if (isset($this->attributes['tenant_id']) && ($this->attributes['tenant_id'] === '' || $this->attributes['tenant_id'] === null)) {
            $this->attributes['tenant_id'] = null;
        }

        // Manejar fechas de creación y actualización manualmente
        if (!isset($this->attributes['id']) || empty($this->attributes['id'])) {
            if (!isset($this->attributes['created_at']) || empty($this->attributes['created_at'])) {
                $this->attributes['created_at'] = date('Y-m-d H:i:s');
            }
        }

        // Siempre actualizar la fecha de última modificación
        $this->attributes['updated_at'] = date('Y-m-d H:i:s');

        // Si el status es published pero no hay fecha de publicación, establecerla
        if (isset($this->attributes['status']) && $this->attributes['status'] === 'published') {
            if (empty($this->attributes['published_at'])) {
                $this->attributes['published_at'] = date('Y-m-d H:i:s');
            }
        }

        // Asegurar que visibility tenga un valor predeterminado si no existe
        if (!isset($this->attributes['visibility']) || !in_array($this->attributes['visibility'], ['public', 'private', 'members'])) {
            $this->attributes['visibility'] = 'public';
        }

        error_log("PAGE SAVE: Llamando a parent::save()");
        $result = parent::save();
        error_log("PAGE SAVE: parent::save() retornó: " . ($result ? 'true' : 'false'));

        return $result;
    }
    
    /**
     * Método para formatear fechas para mostrar en la vista
     */
     /**
     * Método para formatear fechas para mostrar en la vista usando Carbon.
     * Es redundante si el controlador ya lo hace, pero puede ser útil.
     */
     public function getFormattedDate(string $field, ?string $format = null): string
    {
        // Obtener el valor del atributo. Depende de cómo tu Model base maneje los atributos.
        // Intenta acceder directamente si tienes __get mágico o usa $this->attributes.
        // Reemplaza '$this->{$field}' con '$this->attributes[$field]' si es necesario.
        $value = $this->{$field} ?? ($this->attributes[$field] ?? null);

        // Si el valor está vacío o nulo, no hay nada que formatear.
        if (empty($value)) {
            return 'Desconocido'; // O puedes devolver '', null, etc.
        }

        // Determinar el formato a usar (desde settings o default)
        if ($format === null) {
             // Asegúrate que tu helper setting() está disponible globalmente
             try {
                 $dateFormat = setting('date_format', 'd/m/Y');
                 $timeFormat = setting('time_format', 'H:i');
                 $format = $dateFormat . ' ' . $timeFormat;
             } catch (\Throwable $e) {
                 // Fallback si setting() falla
                 $format = 'd/m/Y H:i';
                 error_log("Error obteniendo formato de fecha desde settings: " . $e->getMessage());
             }
        }

        // CASO 1: El valor YA es un objeto DateTime/Carbon (el casting funcionó)
        if ($value instanceof \DateTimeInterface) {
            try {
                // Usar Carbon::instance para asegurar que sea un objeto Carbon y formatear
                return Carbon::instance($value)->format($format);
            } catch (\Exception $e) {
                error_log("Error formateando objeto DateTime {$field} para page ID {$this->id}: " . $e->getMessage());
                return 'Error Formato Obj'; // Error específico
            }
        }

        // CASO 2: El valor es un STRING (el casting NO funcionó o no se aplicó)
        if (is_string($value)) {
            try {
                // Intentar parsear el string con Carbon y luego formatear
                return Carbon::parse($value)->format($format);
            } catch (\Exception $e) {
                // Si el string no es una fecha válida, Carbon::parse lanzará una excepción
                error_log("Error parseando string de fecha {$field} ('{$value}') para page ID {$this->id}: " . $e->getMessage());
                // Devolver 'Desconocido' o el string original si falla el parseo
                return 'Desconocido (Inválido)'; // O 'Desconocido'
            }
        }

        // Si no es ni objeto ni string parseable, devolver 'Desconocido'
        error_log("Valor de fecha {$field} para page ID {$this->id} no es objeto ni string parseable.");
        return 'Desconocido';
    }
    
    /**
     * Actualiza el slug asociado a la página
     */
    public function updateSlug(string $slug, ?string $prefix = null): bool
    {
        try {
            $pdo = \Screenart\Musedock\Database::connect();
            
            // Primero verificamos si ya existe un slug para esta página
            $checkStmt = $pdo->prepare("SELECT id FROM slugs WHERE module = 'pages' AND reference_id = ?");
            $checkStmt->execute([$this->id]);
            $existing = $checkStmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Actualizar el slug existente
                $updateStmt = $pdo->prepare("UPDATE slugs SET slug = ?, prefix = ? WHERE module = 'pages' AND reference_id = ?");
                return $updateStmt->execute([$slug, $prefix ?? 'p', $this->id]);
            } else {
                // Crear un nuevo registro de slug
                $insertStmt = $pdo->prepare("INSERT INTO slugs (module, reference_id, slug, tenant_id, prefix) VALUES (?, ?, ?, NULL, ?)");
                return $insertStmt->execute(['pages', $this->id, $slug, $prefix ?? 'p']);
            }
        } catch (\Exception $e) {
            error_log("Error al actualizar slug para page ID {$this->id}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Método estático para buscar por slug
     */
    public static function findBySlug(string $slug): ?self
    {
        try {
            $pdo = \Screenart\Musedock\Database::connect();
            $stmt = $pdo->prepare("
                SELECT p.* 
                FROM pages p
                JOIN slugs s ON s.module = 'pages' AND s.reference_id = p.id
                WHERE s.slug = ?
            ");
            $stmt->execute([$slug]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$result) return null;
            
            return new self($result);
        } catch (\Exception $e) {
            error_log("Error al buscar página por slug {$slug}: " . $e->getMessage());
            return null;
        }
    }
}
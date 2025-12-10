<?php

namespace CustomForms\Models;

use Screenart\Musedock\Database\Model;

/**
 * Form Model
 *
 * Representa un formulario personalizado con sus configuraciones
 */
class Form extends Model
{
    protected static string $table = 'custom_forms';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'submit_button_text',
        'success_message',
        'error_message',
        'redirect_url',
        'email_to',
        'email_subject',
        'email_from_name',
        'email_from_email',
        'email_reply_to',
        'send_confirmation_email',
        'confirmation_email_subject',
        'confirmation_email_message',
        'store_submissions',
        'enable_recaptcha',
        'form_class',
        'settings',
        'is_active',
        'submissions_count'
    ];

    protected array $casts = [
        'settings' => 'array',
        'is_active' => 'bool',
        'store_submissions' => 'bool',
        'send_confirmation_email' => 'bool',
        'enable_recaptcha' => 'bool',
        'submissions_count' => 'int',
        'tenant_id' => 'int'
    ];

    /**
     * Obtiene todos los campos del formulario
     */
    public function fields(): array
    {
        return FormField::query()
            ->where('form_id', $this->id)
            ->orderBy('sort_order', 'ASC')
            ->get();
    }

    /**
     * Obtiene solo los campos activos
     */
    public function activeFields(): array
    {
        return FormField::query()
            ->where('form_id', $this->id)
            ->where('is_active', 1)
            ->orderBy('sort_order', 'ASC')
            ->get();
    }

    /**
     * Obtiene los campos que son de entrada de datos (no decorativos)
     */
    public function inputFields(): array
    {
        $decorativeTypes = ['heading', 'paragraph', 'divider'];

        return array_filter($this->activeFields(), function ($field) use ($decorativeTypes) {
            return !in_array($field->field_type, $decorativeTypes);
        });
    }

    /**
     * Cuenta los campos del formulario
     */
    public function fieldCount(): int
    {
        return FormField::query()
            ->where('form_id', $this->id)
            ->count();
    }

    /**
     * Obtiene las submissions del formulario
     */
    public function submissions(int $limit = 100, int $offset = 0): array
    {
        return FormSubmission::query()
            ->where('form_id', $this->id)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();
    }

    /**
     * Cuenta las submissions
     */
    public function submissionCount(): int
    {
        return FormSubmission::query()
            ->where('form_id', $this->id)
            ->count();
    }

    /**
     * Cuenta las submissions no leídas
     */
    public function unreadCount(): int
    {
        return FormSubmission::query()
            ->where('form_id', $this->id)
            ->where('is_read', 0)
            ->where('is_spam', 0)
            ->count();
    }

    /**
     * Busca un formulario por slug
     */
    public static function findBySlug(string $slug, ?int $tenantId = null): ?self
    {
        $pdo = \Screenart\Musedock\Database::connect();

        if ($tenantId !== null) {
            // Buscar en tenant específico o globales
            $stmt = $pdo->prepare("
                SELECT * FROM " . static::$table . "
                WHERE slug = ? AND (tenant_id = ? OR tenant_id IS NULL OR tenant_id = 0)
                LIMIT 1
            ");
            $stmt->execute([$slug, $tenantId]);
        } else {
            // Buscar solo en globales
            $stmt = $pdo->prepare("
                SELECT * FROM " . static::$table . "
                WHERE slug = ? AND (tenant_id IS NULL OR tenant_id = 0)
                LIMIT 1
            ");
            $stmt->execute([$slug]);
        }

        $result = $stmt->fetch(\PDO::FETCH_OBJ);
        return $result ? new static($result) : null;
    }

    /**
     * Obtiene formularios por tenant
     */
    public static function getByTenant(?int $tenantId = null, bool $includeGlobal = true): array
    {
        $pdo = \Screenart\Musedock\Database::connect();

        if ($tenantId !== null) {
            if ($includeGlobal) {
                // Incluir formularios del tenant y globales
                $stmt = $pdo->prepare("
                    SELECT * FROM " . static::$table . "
                    WHERE tenant_id = ? OR tenant_id IS NULL OR tenant_id = 0
                    ORDER BY created_at DESC
                ");
                $stmt->execute([$tenantId]);
            } else {
                // Solo formularios del tenant específico
                $stmt = $pdo->prepare("
                    SELECT * FROM " . static::$table . "
                    WHERE tenant_id = ?
                    ORDER BY created_at DESC
                ");
                $stmt->execute([$tenantId]);
            }
        } else {
            // Formularios globales: tenant_id NULL o 0
            $stmt = $pdo->prepare("
                SELECT * FROM " . static::$table . "
                WHERE tenant_id IS NULL OR tenant_id = 0
                ORDER BY created_at DESC
            ");
            $stmt->execute();
        }

        $results = $stmt->fetchAll(\PDO::FETCH_OBJ);
        return array_map(fn($row) => new static($row), $results);
    }

    /**
     * Obtiene formularios activos
     */
    public static function getActive(?int $tenantId = null): array
    {
        $pdo = \Screenart\Musedock\Database::connect();

        if ($tenantId !== null) {
            // Incluir formularios del tenant y globales
            $stmt = $pdo->prepare("
                SELECT * FROM " . static::$table . "
                WHERE is_active = 1 AND (tenant_id = ? OR tenant_id IS NULL OR tenant_id = 0)
                ORDER BY name ASC
            ");
            $stmt->execute([$tenantId]);
        } else {
            // Solo formularios globales activos
            $stmt = $pdo->prepare("
                SELECT * FROM " . static::$table . "
                WHERE is_active = 1 AND (tenant_id IS NULL OR tenant_id = 0)
                ORDER BY name ASC
            ");
            $stmt->execute();
        }

        $results = $stmt->fetchAll(\PDO::FETCH_OBJ);
        return array_map(fn($row) => new static($row), $results);
    }

    /**
     * Genera un slug único
     */
    public static function generateUniqueSlug(string $name, ?int $tenantId = null, ?int $excludeId = null): string
    {
        $baseSlug = self::createSlug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (self::slugExists($slug, $tenantId, $excludeId)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Verifica si un slug ya existe
     */
    public static function slugExists(string $slug, ?int $tenantId = null, ?int $excludeId = null): bool
    {
        $pdo = \Screenart\Musedock\Database::connect();

        $sql = "SELECT COUNT(*) as count FROM " . static::$table . " WHERE slug = ?";
        $params = [$slug];

        if ($tenantId !== null) {
            $sql .= " AND tenant_id = ?";
            $params[] = $tenantId;
        } else {
            $sql .= " AND (tenant_id IS NULL OR tenant_id = 0)";
        }

        if ($excludeId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(\PDO::FETCH_OBJ);

        return $result->count > 0;
    }

    /**
     * Crea un slug a partir de un texto
     */
    private static function createSlug(string $text): string
    {
        $slug = mb_strtolower($text, 'UTF-8');

        $replacements = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ñ' => 'n', 'ü' => 'u', 'ç' => 'c',
        ];
        $slug = strtr($slug, $replacements);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug ?: 'form';
    }

    /**
     * Incrementa el contador de submissions
     */
    public function incrementSubmissions(): bool
    {
        $this->submissions_count = ($this->submissions_count ?? 0) + 1;
        return $this->save();
    }

    /**
     * Obtiene el email de campo para confirmación
     */
    public function getEmailField(): ?FormField
    {
        return FormField::query()
            ->where('form_id', $this->id)
            ->where('field_type', 'email')
            ->where('is_active', 1)
            ->first();
    }

    /**
     * Obtiene los tipos de campos disponibles
     */
    public static function getFieldTypes(): array
    {
        return [
            'text' => [
                'name' => 'Texto',
                'icon' => 'bi-fonts',
                'category' => 'basic'
            ],
            'email' => [
                'name' => 'Email',
                'icon' => 'bi-envelope',
                'category' => 'basic'
            ],
            'number' => [
                'name' => 'Número',
                'icon' => 'bi-123',
                'category' => 'basic'
            ],
            'phone' => [
                'name' => 'Teléfono',
                'icon' => 'bi-telephone',
                'category' => 'basic'
            ],
            'textarea' => [
                'name' => 'Área de texto',
                'icon' => 'bi-text-paragraph',
                'category' => 'basic'
            ],
            'select' => [
                'name' => 'Desplegable',
                'icon' => 'bi-menu-button-wide',
                'category' => 'choice'
            ],
            'radio' => [
                'name' => 'Opciones (radio)',
                'icon' => 'bi-ui-radios',
                'category' => 'choice'
            ],
            'checkbox' => [
                'name' => 'Casilla única',
                'icon' => 'bi-check-square',
                'category' => 'choice'
            ],
            'checkbox_group' => [
                'name' => 'Casillas múltiples',
                'icon' => 'bi-ui-checks',
                'category' => 'choice'
            ],
            'date' => [
                'name' => 'Fecha',
                'icon' => 'bi-calendar',
                'category' => 'datetime'
            ],
            'time' => [
                'name' => 'Hora',
                'icon' => 'bi-clock',
                'category' => 'datetime'
            ],
            'datetime' => [
                'name' => 'Fecha y hora',
                'icon' => 'bi-calendar-event',
                'category' => 'datetime'
            ],
            'file' => [
                'name' => 'Archivo',
                'icon' => 'bi-paperclip',
                'category' => 'advanced'
            ],
            'url' => [
                'name' => 'URL',
                'icon' => 'bi-link-45deg',
                'category' => 'advanced'
            ],
            'hidden' => [
                'name' => 'Campo oculto',
                'icon' => 'bi-eye-slash',
                'category' => 'advanced'
            ],
            'password' => [
                'name' => 'Contraseña',
                'icon' => 'bi-key',
                'category' => 'advanced'
            ],
            'heading' => [
                'name' => 'Encabezado',
                'icon' => 'bi-type-h1',
                'category' => 'layout'
            ],
            'paragraph' => [
                'name' => 'Párrafo',
                'icon' => 'bi-text-left',
                'category' => 'layout'
            ],
            'divider' => [
                'name' => 'Separador',
                'icon' => 'bi-hr',
                'category' => 'layout'
            ],
        ];
    }

    /**
     * Serializa el formulario para API/frontend
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'submit_button_text' => $this->submit_button_text,
            'field_count' => $this->fieldCount(),
            'submission_count' => $this->submissions_count,
            'unread_count' => $this->unreadCount(),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

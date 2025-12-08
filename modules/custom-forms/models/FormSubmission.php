<?php

namespace CustomForms\Models;

use Screenart\Musedock\Database\Model;

/**
 * FormSubmission Model
 *
 * Representa un envío de formulario con todos sus datos
 */
class FormSubmission extends Model
{
    protected static string $table = 'custom_form_submissions';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'form_id',
        'data',
        'ip_address',
        'user_agent',
        'referrer_url',
        'page_url',
        'user_id',
        'email_sent',
        'email_sent_at',
        'confirmation_sent',
        'is_read',
        'is_starred',
        'is_spam',
        'notes'
    ];

    protected array $casts = [
        'data' => 'array',
        'email_sent' => 'bool',
        'confirmation_sent' => 'bool',
        'is_read' => 'bool',
        'is_starred' => 'bool',
        'is_spam' => 'bool',
        'form_id' => 'int',
        'user_id' => 'int'
    ];

    /**
     * Obtiene el formulario asociado
     */
    public function form(): ?Form
    {
        return Form::find($this->form_id);
    }

    /**
     * Obtiene submissions por formulario
     */
    public static function getByForm(int $formId, array $filters = []): array
    {
        $query = self::query()->where('form_id', $formId);

        // Filtro por estado de lectura
        if (isset($filters['is_read'])) {
            $query->where('is_read', $filters['is_read'] ? 1 : 0);
        }

        // Filtro por destacados
        if (isset($filters['is_starred'])) {
            $query->where('is_starred', $filters['is_starred'] ? 1 : 0);
        }

        // Filtro por spam
        if (isset($filters['is_spam'])) {
            $query->where('is_spam', $filters['is_spam'] ? 1 : 0);
        } else {
            // Por defecto excluir spam
            $query->where('is_spam', 0);
        }

        // Filtro por fecha
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        // Búsqueda en datos
        if (!empty($filters['search'])) {
            $query->where('data', 'LIKE', '%' . $filters['search'] . '%');
        }

        return $query->orderBy('created_at', 'DESC')->get();
    }

    /**
     * Obtiene submissions paginadas
     */
    public static function paginate(int $formId, int $perPage = 25, int $page = 1, array $filters = []): array
    {
        $query = self::query()->where('form_id', $formId);

        // Aplicar filtros
        if (!isset($filters['is_spam'])) {
            $query->where('is_spam', 0);
        } elseif ($filters['is_spam']) {
            $query->where('is_spam', 1);
        }

        if (isset($filters['is_read'])) {
            $query->where('is_read', $filters['is_read'] ? 1 : 0);
        }

        if (isset($filters['is_starred']) && $filters['is_starred']) {
            $query->where('is_starred', 1);
        }

        if (!empty($filters['search'])) {
            $query->where('data', 'LIKE', '%' . $filters['search'] . '%');
        }

        $total = $query->count();
        $offset = ($page - 1) * $perPage;

        $items = $query
            ->orderBy('created_at', 'DESC')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        return [
            'items' => $items,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage),
            'from' => $offset + 1,
            'to' => min($offset + $perPage, $total)
        ];
    }

    /**
     * Cuenta submissions por estado
     */
    public static function countByStatus(int $formId): array
    {
        $pdo = \Screenart\Musedock\Database::connect();

        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN is_read = 0 AND is_spam = 0 THEN 1 ELSE 0 END) as unread,
                SUM(CASE WHEN is_starred = 1 AND is_spam = 0 THEN 1 ELSE 0 END) as starred,
                SUM(CASE WHEN is_spam = 1 THEN 1 ELSE 0 END) as spam
            FROM `custom_form_submissions`
            WHERE `form_id` = ?
        ");

        $stmt->execute([$formId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return [
            'total' => (int) ($result['total'] ?? 0),
            'unread' => (int) ($result['unread'] ?? 0),
            'starred' => (int) ($result['starred'] ?? 0),
            'spam' => (int) ($result['spam'] ?? 0),
        ];
    }

    /**
     * Marca como leído
     */
    public function markAsRead(): bool
    {
        $this->is_read = true;
        return $this->save();
    }

    /**
     * Marca como no leído
     */
    public function markAsUnread(): bool
    {
        $this->is_read = false;
        return $this->save();
    }

    /**
     * Alterna estado de destacado
     */
    public function toggleStar(): bool
    {
        $this->is_starred = !$this->is_starred;
        return $this->save();
    }

    /**
     * Marca como spam
     */
    public function markAsSpam(): bool
    {
        $this->is_spam = true;
        return $this->save();
    }

    /**
     * Marca como no spam
     */
    public function markAsNotSpam(): bool
    {
        $this->is_spam = false;
        return $this->save();
    }

    /**
     * Obtiene un valor específico de los datos
     */
    public function getValue(string $fieldName, $default = null)
    {
        $data = $this->data ?? [];
        return $data[$fieldName] ?? $default;
    }

    /**
     * Obtiene los datos formateados con etiquetas
     */
    public function getFormattedData(): array
    {
        $form = $this->form();
        if (!$form) {
            return $this->data ?? [];
        }

        $fields = $form->fields();
        $fieldMap = [];
        foreach ($fields as $field) {
            $fieldMap[$field->field_name] = $field;
        }

        $formatted = [];
        $data = $this->data ?? [];

        foreach ($data as $name => $value) {
            $field = $fieldMap[$name] ?? null;

            $formatted[] = [
                'name' => $name,
                'label' => $field ? $field->field_label : ucfirst(str_replace('_', ' ', $name)),
                'value' => $this->formatValue($value, $field),
                'raw_value' => $value,
                'type' => $field ? $field->field_type : 'text'
            ];
        }

        return $formatted;
    }

    /**
     * Formatea un valor según el tipo de campo
     */
    private function formatValue($value, ?FormField $field): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (is_array($value)) {
            return implode(', ', $value);
        }

        if ($field) {
            switch ($field->field_type) {
                case 'checkbox':
                    return $value ? 'Sí' : 'No';

                case 'date':
                    $date = \DateTime::createFromFormat('Y-m-d', $value);
                    return $date ? $date->format('d/m/Y') : $value;

                case 'datetime':
                    $date = \DateTime::createFromFormat('Y-m-d\TH:i', $value);
                    return $date ? $date->format('d/m/Y H:i') : $value;

                case 'file':
                    return '<a href="' . htmlspecialchars($value) . '" target="_blank">Ver archivo</a>';
            }
        }

        return htmlspecialchars((string) $value);
    }

    /**
     * Obtiene el email del remitente si existe
     */
    public function getSubmitterEmail(): ?string
    {
        $data = $this->data ?? [];

        // Buscar campo de tipo email
        $form = $this->form();
        if ($form) {
            $emailField = $form->getEmailField();
            if ($emailField && isset($data[$emailField->field_name])) {
                return $data[$emailField->field_name];
            }
        }

        // Buscar por nombre común
        $emailFields = ['email', 'correo', 'e-mail', 'mail'];
        foreach ($emailFields as $fieldName) {
            if (isset($data[$fieldName]) && filter_var($data[$fieldName], FILTER_VALIDATE_EMAIL)) {
                return $data[$fieldName];
            }
        }

        return null;
    }

    /**
     * Exporta a array para CSV/Excel
     */
    public function toExportArray(): array
    {
        $data = $this->data ?? [];

        return array_merge([
            'ID' => $this->id,
            'Fecha' => $this->created_at,
            'IP' => $this->ip_address,
            'Leído' => $this->is_read ? 'Sí' : 'No',
            'Destacado' => $this->is_starred ? 'Sí' : 'No',
        ], $data);
    }

    /**
     * Serializa para API
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'form_id' => $this->form_id,
            'data' => $this->data,
            'formatted_data' => $this->getFormattedData(),
            'ip_address' => $this->ip_address,
            'page_url' => $this->page_url,
            'email_sent' => $this->email_sent,
            'is_read' => $this->is_read,
            'is_starred' => $this->is_starred,
            'is_spam' => $this->is_spam,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'submitter_email' => $this->getSubmitterEmail(),
        ];
    }
}

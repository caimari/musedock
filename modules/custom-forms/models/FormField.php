<?php

namespace CustomForms\Models;

use Screenart\Musedock\Database\Model;

/**
 * FormField Model
 *
 * Representa un campo individual dentro de un formulario
 */
class FormField extends Model
{
    protected static string $table = 'custom_form_fields';
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = true;

    protected array $fillable = [
        'form_id',
        'field_type',
        'field_name',
        'field_label',
        'placeholder',
        'default_value',
        'help_text',
        'options',
        'validation_rules',
        'is_required',
        'min_length',
        'max_length',
        'min_value',
        'max_value',
        'pattern',
        'error_message',
        'field_class',
        'wrapper_class',
        'width',
        'conditional_logic',
        'sort_order',
        'is_active'
    ];

    protected array $casts = [
        'options' => 'array',
        'validation_rules' => 'array',
        'conditional_logic' => 'array',
        'is_required' => 'bool',
        'is_active' => 'bool',
        'form_id' => 'int',
        'sort_order' => 'int',
        'min_length' => 'int',
        'max_length' => 'int'
    ];

    /**
     * Obtiene el formulario al que pertenece
     */
    public function form(): ?Form
    {
        return Form::find($this->form_id);
    }

    /**
     * Obtiene los campos por formulario
     */
    public static function getByForm(int $formId, bool $onlyActive = true): array
    {
        $query = self::query()->where('form_id', $formId);

        if ($onlyActive) {
            $query->where('is_active', 1);
        }

        return $query->orderBy('sort_order', 'ASC')->get();
    }

    /**
     * Obtiene el siguiente orden disponible
     */
    public static function getNextSortOrder(int $formId): int
    {
        $maxOrder = self::query()
            ->where('form_id', $formId)
            ->max('sort_order');

        return ($maxOrder ?? 0) + 1;
    }

    /**
     * Reordena los campos de un formulario
     */
    public static function reorder(int $formId, array $fieldIds): bool
    {
        $pdo = \Screenart\Musedock\Database::connect();

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE `custom_form_fields`
                SET `sort_order` = ?
                WHERE `id` = ? AND `form_id` = ?
            ");

            foreach ($fieldIds as $order => $fieldId) {
                $stmt->execute([$order, $fieldId, $formId]);
            }

            $pdo->commit();
            return true;

        } catch (\Exception $e) {
            $pdo->rollBack();
            error_log("Error reordering form fields: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Genera un nombre de campo único
     */
    public static function generateFieldName(string $label, int $formId): string
    {
        $baseName = self::sanitizeFieldName($label);
        $name = $baseName;
        $counter = 1;

        while (self::fieldNameExists($name, $formId)) {
            $name = $baseName . '_' . $counter;
            $counter++;
        }

        return $name;
    }

    /**
     * Verifica si un nombre de campo existe
     */
    public static function fieldNameExists(string $name, int $formId, ?int $excludeId = null): bool
    {
        $query = self::query()
            ->where('form_id', $formId)
            ->where('field_name', $name);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->first() !== null;
    }

    /**
     * Sanitiza un nombre de campo
     */
    private static function sanitizeFieldName(string $label): string
    {
        $name = mb_strtolower($label, 'UTF-8');

        $replacements = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ñ' => 'n', 'ü' => 'u',
        ];
        $name = strtr($name, $replacements);
        $name = preg_replace('/[^a-z0-9\s]/', '', $name);
        $name = preg_replace('/\s+/', '_', $name);
        $name = trim($name, '_');

        return $name ?: 'field';
    }

    /**
     * Verifica si el campo es de tipo input
     */
    public function isInputField(): bool
    {
        $layoutTypes = ['heading', 'paragraph', 'divider'];
        return !in_array($this->field_type, $layoutTypes);
    }

    /**
     * Verifica si el campo tiene opciones
     */
    public function hasOptions(): bool
    {
        return in_array($this->field_type, ['select', 'radio', 'checkbox_group']);
    }

    /**
     * Obtiene las opciones formateadas
     */
    public function getOptions(): array
    {
        if (!$this->hasOptions()) {
            return [];
        }

        $options = $this->options ?? [];

        // Si es un array simple, convertir a formato label/value
        if (!empty($options) && !isset($options[0]['label'])) {
            return array_map(function ($opt) {
                if (is_string($opt)) {
                    return ['label' => $opt, 'value' => $opt];
                }
                return $opt;
            }, $options);
        }

        return $options;
    }

    /**
     * Obtiene las reglas de validación
     */
    public function getValidationRules(): array
    {
        $rules = $this->validation_rules ?? [];

        // Añadir reglas basadas en propiedades
        if ($this->is_required) {
            $rules['required'] = true;
        }

        if ($this->min_length) {
            $rules['minlength'] = $this->min_length;
        }

        if ($this->max_length) {
            $rules['maxlength'] = $this->max_length;
        }

        if ($this->min_value !== null) {
            $rules['min'] = $this->min_value;
        }

        if ($this->max_value !== null) {
            $rules['max'] = $this->max_value;
        }

        if ($this->pattern) {
            $rules['pattern'] = $this->pattern;
        }

        // Reglas por tipo
        switch ($this->field_type) {
            case 'email':
                $rules['email'] = true;
                break;
            case 'url':
                $rules['url'] = true;
                break;
            case 'number':
                $rules['number'] = true;
                break;
        }

        return $rules;
    }

    /**
     * Valida un valor según las reglas del campo
     */
    public function validate($value): array
    {
        $errors = [];
        $rules = $this->getValidationRules();

        // Required
        if (!empty($rules['required']) && $this->isEmpty($value)) {
            $errors[] = $this->error_message ?: __forms('validation.required', ['field' => $this->field_label]);
            return $errors; // Si es requerido y vacío, no seguir validando
        }

        // Si está vacío y no es requerido, no validar más
        if ($this->isEmpty($value)) {
            return $errors;
        }

        // Email
        if (!empty($rules['email']) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $errors[] = __forms('validation.email');
        }

        // URL
        if (!empty($rules['url']) && !filter_var($value, FILTER_VALIDATE_URL)) {
            $errors[] = __forms('validation.url');
        }

        // Number
        if (!empty($rules['number']) && !is_numeric($value)) {
            $errors[] = __forms('validation.number');
        }

        // Min length
        if (isset($rules['minlength']) && strlen($value) < $rules['minlength']) {
            $errors[] = __forms('validation.minlength', ['min' => $rules['minlength']]);
        }

        // Max length
        if (isset($rules['maxlength']) && strlen($value) > $rules['maxlength']) {
            $errors[] = __forms('validation.maxlength', ['max' => $rules['maxlength']]);
        }

        // Min value
        if (isset($rules['min']) && is_numeric($value) && $value < $rules['min']) {
            $errors[] = __forms('validation.min', ['min' => $rules['min']]);
        }

        // Max value
        if (isset($rules['max']) && is_numeric($value) && $value > $rules['max']) {
            $errors[] = __forms('validation.max', ['max' => $rules['max']]);
        }

        // Pattern
        if (!empty($rules['pattern']) && !preg_match('/' . $rules['pattern'] . '/', $value)) {
            $errors[] = $this->error_message ?: __forms('validation.pattern');
        }

        return $errors;
    }

    /**
     * Verifica si un valor está vacío
     */
    private function isEmpty($value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_array($value) && count($value) === 0) {
            return true;
        }

        return false;
    }

    /**
     * Obtiene la clase CSS del ancho
     */
    public function getWidthClass(): string
    {
        switch ($this->width) {
            case 'half':
                return 'col-md-6';
            case 'third':
                return 'col-md-4';
            case 'quarter':
                return 'col-md-3';
            case 'full':
            default:
                return 'col-12';
        }
    }

    /**
     * Genera el HTML del input type
     */
    public function getInputType(): string
    {
        $typeMap = [
            'text' => 'text',
            'email' => 'email',
            'number' => 'number',
            'phone' => 'tel',
            'url' => 'url',
            'password' => 'password',
            'date' => 'date',
            'time' => 'time',
            'datetime' => 'datetime-local',
            'file' => 'file',
            'hidden' => 'hidden',
        ];

        return $typeMap[$this->field_type] ?? 'text';
    }

    /**
     * Serializa el campo para API/frontend
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'form_id' => $this->form_id,
            'field_type' => $this->field_type,
            'field_name' => $this->field_name,
            'field_label' => $this->field_label,
            'placeholder' => $this->placeholder,
            'default_value' => $this->default_value,
            'help_text' => $this->help_text,
            'options' => $this->getOptions(),
            'is_required' => $this->is_required,
            'min_length' => $this->min_length,
            'max_length' => $this->max_length,
            'min_value' => $this->min_value,
            'max_value' => $this->max_value,
            'pattern' => $this->pattern,
            'error_message' => $this->error_message,
            'field_class' => $this->field_class,
            'wrapper_class' => $this->wrapper_class,
            'width' => $this->width,
            'conditional_logic' => $this->conditional_logic,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'validation_rules' => $this->getValidationRules(),
            'width_class' => $this->getWidthClass(),
            'input_type' => $this->getInputType(),
        ];
    }
}

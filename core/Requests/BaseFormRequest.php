<?php

namespace Screenart\Musedock\Requests;

abstract class BaseFormRequest
{
    protected array $data;
    protected array $errors = [];
    protected bool $updating = false;
    protected ?int $resourceId = null;

    public function __construct(array $data, bool $updating = false, ?int $resourceId = null)
    {
        $this->data = $data;
        $this->updating = $updating;
        $this->resourceId = $resourceId;

        $this->validate();
    }

    abstract protected function validate(): void;

    protected function required(string $field, string $label = null): void
    {
        if (empty($this->data[$field])) {
            $label ??= ucfirst(str_replace('_', ' ', $field));
            $this->errors[] = "El campo <strong>{$label}</strong> es obligatorio.";
        }
    }

    protected function unique(string $modelClass, string $field, ?int $tenantId = null): void
    {
        $query = $modelClass::where($field, $this->data[$field]);

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        if ($this->updating && $this->resourceId) {
            $query->where('id', '!=', $this->resourceId);
        }

        if ($query->first()) {
            $this->errors[] = "Ya existe un registro con este <strong>{$field}</strong>.";
        }
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }
}

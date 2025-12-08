<?php

namespace Screenart\Musedock\Database;


use Screenart\Musedock\Database\QueryBuilder;
use Screenart\Musedock\Database;
use DateTime;

abstract class Model
{
    protected static string $table;
    protected static string $primaryKey = 'id';
    protected static bool $timestamps = false;
    protected array $fillable = [];
    protected array $casts = [];

    protected array $attributes = [];
    protected bool $exists = false;

	public function __construct($data = [])
	{
		if (is_object($data)) {
			$data = (array) $data; // Convertir stdClass en array
		}

		$this->fill($data);
		$this->exists = isset($this->attributes[static::$primaryKey]);
	}

    public static function all(): array
    {
        $rows = Database::table(static::$table)->get();
        return array_map(fn($row) => new static($row), $rows);
    }

    public static function find($id): ?self
    {
        $row = Database::table(static::$table)
            ->where(static::$primaryKey, $id)
            ->first();

        return $row ? new static($row) : null;
    }

    public static function create(array $data): self
    {
        $model = new static();
        $model->fill($data);
        $model->save();
        return $model;
    }

	/**
	 * Llena el modelo con un array de atributos
	 *
	 * SEGURIDAD: Por razones de seguridad, el primary key NUNCA puede ser modificado
	 * mediante fill(), incluso si está en $fillable. Esto previene escalación de privilegios.
	 *
	 * @param array $data
	 * @return void
	 */
	public function fill(array $data): void
	{
		foreach ($data as $field => $value) {
			// Permitir el primary key SOLO si el modelo no existe aún (durante carga inicial)
			if ($field === static::$primaryKey) {
				if (!$this->exists && !isset($this->attributes[static::$primaryKey])) {
					// Permitir establecer el primary key durante la carga inicial
					$this->attributes[$field] = $value;
				}
				continue; // Saltar en otros casos
			}

			// Solo llenar campos que están en $fillable
			if (in_array($field, $this->fillable)) {
				$this->attributes[$field] = $value;
			}
		}
	}


    public function save(): bool
    {
        $data = $this->getPreparedAttributes();

        error_log("MODEL SAVE: Table: " . static::$table . ", Exists: " . ($this->exists ? 'yes' : 'no'));
        error_log("MODEL SAVE: Data keys: " . implode(', ', array_keys($data)));

        if (static::$timestamps) {
            $now = date('Y-m-d H:i:s');
            $data['updated_at'] = $now;
            if (!$this->exists) {
                $data['created_at'] = $now;
            }
        }

        if ($this->exists) {
            error_log("MODEL SAVE: Ejecutando UPDATE para ID: " . $this->getKey());
            Database::table(static::$table)
                ->where(static::$primaryKey, $this->getKey())
                ->update($data);
            error_log("MODEL SAVE: UPDATE completado");
        } else {
            error_log("MODEL SAVE: Ejecutando INSERT");
            $id = Database::table(static::$table)->insert($data);
            $this->attributes[static::$primaryKey] = $id;
            $this->exists = true;
            error_log("MODEL SAVE: INSERT completado con ID: " . $id);
        }

        return true;
    }

    public function update(array $data): bool
    {
        $this->fill($data);
        return $this->save();
    }

    public function delete(): bool
    {
        if (!$this->exists) return false;

        return Database::table(static::$table)
            ->where(static::$primaryKey, $this->getKey())
            ->delete();
    }

    protected function getPreparedAttributes(): array
    {
        $data = [];
        foreach ($this->attributes as $key => $value) {
            $data[$key] = $this->castForSave($key, $value);
        }
        return $data;
    }

    protected function castForSave($key, $value)
    {
        $castType = $this->casts[$key] ?? null;

        if ($castType === 'datetime' && $value instanceof DateTime) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($castType === 'int') {
            return (int) $value;
        }

        if ($castType === 'bool') {
            return $value ? 1 : 0;
        }

        if ($castType === 'array') {
            if (is_string($value)) {
                // Evitar doble codificación si ya viene como JSON
                json_decode($value);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $value;
                }
            }

            return json_encode($value ?? [], JSON_UNESCAPED_UNICODE);
        }

        return $value;
    }

	public function __get($key)
	{
		$value = $this->attributes[$key] ?? null;

        if (!isset($this->casts[$key]) || $value === null) {
            return $value;
        }

        $castType = $this->casts[$key];

        if ($castType === 'datetime' && $value && !($value instanceof DateTime)) {
            return new DateTime($value);
        }

        if ($castType === 'int') {
            return (int) $value;
        }

        if ($castType === 'bool') {
            return (bool) $value;
        }

        if ($castType === 'array') {
            if (is_array($value)) {
                return $value;
            }

            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
            }

            return [];
        }

        return $value;
	}


    public function __set($key, $value)
    {
        $this->attributes[$key] = $value;
    }

    public function getKey()
    {
        return $this->attributes[static::$primaryKey] ?? null;
    }

    // ----------------------
    // Relaciones
    // ----------------------

    public function belongsTo(string $related, string $foreignKey, string $ownerKey = 'id'): ?Model
    {
        $foreignId = $this->$foreignKey;
        return $related::find($foreignId);
    }

    public function hasOne(string $related, string $foreignKey, string $localKey = 'id'): ?Model
    {
        $value = $this->$localKey;
        $row = Database::table($related::$table)
            ->where($foreignKey, $value)
            ->first();

        return $row ? new $related($row) : null;
    }

    public function hasMany(string $related, string $foreignKey, string $localKey = 'id'): array
    {
        $value = $this->$localKey;
        $rows = Database::table($related::$table)
            ->where($foreignKey, $value)
            ->get();

        return array_map(fn($row) => new $related($row), $rows);
    }

    public function belongsToMany(string $related, string $pivotTable, string $foreignPivotKey, string $relatedPivotKey): array
    {
        $localId = $this->getKey();

        $pivotRows = Database::table($pivotTable)
            ->where($foreignPivotKey, $localId)
            ->get();

        $relatedIds = array_column($pivotRows, $relatedPivotKey);

        if (empty($relatedIds)) return [];

        $placeholders = implode(',', array_fill(0, count($relatedIds), '?'));

        $rows = Database::fetchAll(
            "SELECT * FROM {$related::$table} WHERE {$related::$primaryKey} IN ($placeholders)",
            $relatedIds
        );

        return array_map(fn($row) => new $related($row), $rows);
    }
	
	public static function where($column, $operator = null, $value = null)
{
    // Si se pasan 3 argumentos (columna, operador, valor), usar whereOp
    if ($value !== null) {
        return Database::table(static::$table)->whereOp($column, $operator, $value);
    }
    // Si se pasan 2 argumentos (columna, valor), usar where normal
    return Database::table(static::$table)->where($column, $operator);
}

public static function first()
{
    return Database::table(static::$table)->first();
}

public static function exists(): bool
{
    return Database::table(static::$table)->exists();
}

public static function whereRaw(string $raw)
{
    return Database::table(static::$table)->whereRaw($raw);
}

public static function whereNull(string $column)
{
    return Database::table(static::$table)->whereNull($column);
}

public static function whereNotNull(string $column)
{
    return Database::table(static::$table)->whereNotNull($column);
}

public static function query()
{
    return new \Screenart\Musedock\Database\QueryBuilder(static::$table);
}

    /**
     * Crea una instancia del modelo a partir de un resultado de la BD
     */
    protected static function newFromBuilder($attributes): static
    {
        if ($attributes instanceof static) {
            return $attributes;
        }

        return new static($attributes ?? []);
    }

    /**
     * Convierte un conjunto de resultados en instancias del modelo
     */
    protected static function hydrateCollection(array $items): array
    {
        return array_map(fn($item) => static::newFromBuilder($item), $items);
    }
}

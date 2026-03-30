<?php

namespace Screenart\Musedock\Models;

use Screenart\Musedock\Database\QueryBuilder;
use Screenart\Musedock\Database\Model;

class User extends Model
{
    // Definir la tabla asociada a este modelo
    protected static string $table = 'users';

    // Definir la clave primaria
    protected static string $primaryKey = 'id';

    // Atributos que se pueden asignar masivamente
    protected array $fillable = [
        'name',
        'email',
        'password',
        'tenant_id',
        'registered_ip',
        'created_at',
        'updated_at',
    ];

    // Método para realizar una consulta
    public static function query(): QueryBuilder
    {
        return new QueryBuilder(static::class, (new static)->getTable());
    }

    // Método para encontrar un usuario por su email y tenant_id
    public static function findByEmail(string $email, int $tenantId): ?self
    {
        return static::query()
            ->where('email', $email)
            ->where('tenant_id', $tenantId)
            ->first();
    }

    // Método para crear un nuevo usuario (insert directo)
    public static function createUser(array $attributes): bool
    {
        return static::query()->insert($attributes);
    }

    // Método para actualizar un usuario por su ID
    public static function updateById(int $id, array $data): int
    {
        return static::query()->where('id', $id)->update($data);
    }

    // Método para eliminar un usuario por su ID
    public static function deleteById(int $id): int
    {
        return static::query()->where('id', $id)->delete();
    }
}

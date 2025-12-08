# Migraciones de Base de Datos - Seguridad

Este directorio contiene las migraciones para implementar las mejoras de seguridad en Musedock.

## 📋 Migraciones Disponibles

### 1. `2025_01_13_000000_add_soft_delete_columns.php`
Agrega columnas para soft delete (eliminación lógica):
- `deleted_at` - Fecha y hora de eliminación
- `deleted_by` - ID del usuario que eliminó el registro
- Índices en `deleted_at` para mejorar rendimiento

**Tablas afectadas:**
- `blog_posts`
- `blog_categories`
- `blog_tags`

### 2. `2025_01_13_000001_add_security_indexes.php`
Crea índices para mejorar rendimiento y seguridad:
- Índices en `tenant_id` para queries multi-tenant
- Índices en `status`, `published_at`, `slug`
- Índices compuestos para queries frecuentes

**Tablas afectadas:**
- `blog_posts`, `blog_categories`, `blog_tags`
- `blog_post_categories`, `blog_post_tags`
- `admins`, `users`, `slugs`, `tenants`

### 3. `2025_01_13_000002_add_security_foreign_keys.php`
Agrega foreign keys para integridad referencial:
- Relaciones tenant → blog posts/categories/tags
- Relaciones post → categories → tags
- Cascade en DELETE y UPDATE

⚠️ **IMPORTANTE:** Esta migración puede fallar si hay datos huérfanos. Hacer backup primero.

## 🚀 Cómo Ejecutar

### Usando el sistema de migraciones PHP

```bash
cd /home/user/musedock
php migrate up
```

Esto ejecutará todas las migraciones pendientes en orden.

### Ejecutar migración específica

```bash
php migrate up 2025_01_13_000000_add_soft_delete_columns
```

### Revertir migración

```bash
php migrate down 2025_01_13_000000_add_soft_delete_columns
```

### Revertir todas las migraciones

```bash
php migrate down
```

## ⚠️ Precauciones

### Antes de ejecutar las migraciones:

1. **HACER BACKUP COMPLETO DE LA BASE DE DATOS**
   ```bash
   mysqldump -u usuario -p nombre_bd > backup_$(date +%Y%m%d_%H%M%S).sql
   ```

2. **Verificar datos huérfanos** (especialmente antes de foreign keys):
   ```sql
   -- Verificar blog_posts huérfanos
   SELECT COUNT(*) FROM blog_posts
   WHERE tenant_id IS NOT NULL
   AND tenant_id NOT IN (SELECT id FROM tenants);

   -- Verificar admins huérfanos
   SELECT COUNT(*) FROM admins
   WHERE tenant_id IS NOT NULL
   AND tenant_id NOT IN (SELECT id FROM tenants);
   ```

3. **Ejecutar en ambiente de prueba primero**

4. **Verificar que tienes permisos suficientes:**
   - ALTER TABLE
   - CREATE INDEX
   - DROP INDEX
   - REFERENCES (para foreign keys)

## 📊 Verificación Post-Migración

### Verificar que las migraciones se ejecutaron correctamente:

```sql
-- Verificar columnas soft delete
SHOW COLUMNS FROM blog_posts LIKE 'deleted_at';
SHOW COLUMNS FROM blog_posts LIKE 'deleted_by';

-- Verificar índices creados
SHOW INDEX FROM blog_posts;
SHOW INDEX FROM blog_categories;

-- Verificar foreign keys creados
SELECT
    TABLE_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
AND REFERENCED_TABLE_NAME IS NOT NULL;
```

## 🔄 Orden de Ejecución

**IMPORTANTE:** Las migraciones deben ejecutarse en este orden:

1. **Soft Delete** (000000) - Primero, sin dependencias
2. **Índices** (000001) - Segundo, mejora rendimiento
3. **Foreign Keys** (000002) - Último, requiere datos limpios

El sistema de migraciones respeta automáticamente este orden por el timestamp en el nombre del archivo.

## 🐛 Troubleshooting

### Error: "Column already exists"
- La columna ya fue agregada. La migración detectará esto y lo omitirá.
- Ver logs de migración para confirmar.

### Error: "Cannot add foreign key constraint"
- **Causa:** Hay datos huérfanos en la BD
- **Solución:** Ejecutar queries de verificación y limpiar datos huérfanos
- Ver sección "Verificar datos huérfanos" arriba

### Error: "Duplicate key name"
- El índice ya existe. La migración lo detectará y omitirá.

### Error de permisos
- Verificar que el usuario MySQL tiene permisos suficientes:
  ```sql
  SHOW GRANTS FOR 'usuario'@'localhost';
  ```

## 📝 Actualización del Código

### Después de ejecutar soft delete:

Todas las queries deben incluir `WHERE deleted_at IS NULL`:

```php
// ❌ ANTES
$posts = BlogPost::where('tenant_id', $tenantId)->get();

// ✅ DESPUÉS
$posts = BlogPost::where('tenant_id', $tenantId)
    ->whereNull('deleted_at')
    ->get();
```

### Implementar método delete() en modelos:

```php
public function delete()
{
    $this->deleted_at = date('Y-m-d H:i:s');
    $this->deleted_by = $_SESSION['admin']['id'] ?? null;
    return $this->save();
}

public function forceDelete()
{
    // Eliminar permanentemente
    $pdo = Database::connect();
    $stmt = $pdo->prepare("DELETE FROM {$this->table} WHERE id = ?");
    return $stmt->execute([$this->id]);
}
```

## 🔙 Reversión

Si necesitas revertir los cambios:

```bash
# Revertir en orden inverso
php migrate down 2025_01_13_000002_add_security_foreign_keys
php migrate down 2025_01_13_000001_add_security_indexes
php migrate down 2025_01_13_000000_add_soft_delete_columns
```

O manualmente:

```sql
-- Eliminar foreign keys
ALTER TABLE blog_posts DROP FOREIGN KEY fk_blog_posts_tenant;
-- ... etc

-- Eliminar índices
DROP INDEX idx_blog_posts_tenant ON blog_posts;
-- ... etc

-- Eliminar columnas soft delete
ALTER TABLE blog_posts DROP COLUMN deleted_at, DROP COLUMN deleted_by;
-- ... etc
```

## 📊 Estado de las Migraciones

Para ver qué migraciones se han ejecutado:

```bash
php migrate status
```

O consultar la tabla de migraciones:

```sql
SELECT * FROM migrations ORDER BY batch DESC, migration DESC;
```

## 💡 Tips

1. **Siempre hacer backup antes de migrar en producción**
2. **Probar en ambiente de desarrollo primero**
3. **Monitorear logs durante la migración**
4. **Verificar rendimiento después de crear índices**
5. **Tener plan de rollback preparado**

## 📞 Soporte

Si encuentras problemas:
1. Revisar logs de error de PHP: `/var/log/php_errors.log`
2. Revisar logs de MySQL: `/var/log/mysql/error.log`
3. Verificar permisos de archivos y BD
4. Consultar documentación de Laravel migrations
5. Revisar `SECURITY_FIXES_SUMMARY.md` en la raíz del proyecto

## 🔗 Referencias

- [Laravel Migrations Docs](https://laravel.com/docs/migrations)
- [MySQL Foreign Keys](https://dev.mysql.com/doc/refman/8.0/en/create-table-foreign-keys.html)
- [MySQL Indexes](https://dev.mysql.com/doc/refman/8.0/en/optimization-indexes.html)
- Documentación completa: `SECURITY_FIXES_SUMMARY.md`

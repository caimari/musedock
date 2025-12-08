<?php

namespace Screenart\Musedock\Database;

/**
 * Clase abstracta para drivers de base de datos
 * Permite abstraer diferencias entre MySQL y PostgreSQL
 */
abstract class DatabaseDriver
{
    /**
     * Obtiene el DSN de conexión
     *
     * @param string $host Host de la base de datos
     * @param string $db Nombre de la base de datos
     * @param int $port Puerto (opcional)
     * @return string DSN para PDO
     */
    abstract public function getDSN(string $host, string $db, int $port = null): string;

    /**
     * Escapa el nombre de una columna o tabla según el motor
     *
     * @param string $identifier Nombre de la columna o tabla
     * @return string Identificador escapado
     */
    abstract public function escapeIdentifier(string $identifier): string;

    /**
     * Genera una consulta UPSERT (INSERT con actualización en caso de duplicado)
     *
     * @param string $table Nombre de la tabla
     * @param array $columns Columnas a insertar
     * @param array $updateColumns Columnas a actualizar en caso de duplicado
     * @param string $conflictKey Clave única que genera el conflicto
     * @return string Consulta SQL
     */
    abstract public function upsertQuery(string $table, array $columns, array $updateColumns, string $conflictKey): string;

    /**
     * Suma un intervalo de tiempo a una fecha
     *
     * @param string $date Expresión de fecha (ej: 'NOW()', 'created_at')
     * @param int $value Valor del intervalo
     * @param string $unit Unidad (MINUTE, HOUR, DAY, MONTH, YEAR)
     * @return string Expresión SQL
     */
    abstract public function dateAdd(string $date, int $value, string $unit): string;

    /**
     * Resta un intervalo de tiempo a una fecha
     *
     * @param string $date Expresión de fecha
     * @param int $value Valor del intervalo
     * @param string $unit Unidad (MINUTE, HOUR, DAY, MONTH, YEAR)
     * @return string Expresión SQL
     */
    abstract public function dateSub(string $date, int $value, string $unit): string;

    /**
     * Calcula la diferencia entre dos fechas
     *
     * @param string $date1 Primera fecha
     * @param string $date2 Segunda fecha
     * @param string $unit Unidad de resultado (SECOND, MINUTE, HOUR, DAY)
     * @return string Expresión SQL que retorna la diferencia
     */
    abstract public function dateDiff(string $date1, string $date2, string $unit = 'SECOND'): string;

    /**
     * Obtiene el nombre del driver (mysql, pgsql)
     *
     * @return string
     */
    abstract public function getDriverName(): string;

    /**
     * Obtiene el nombre de la extensión PDO requerida
     *
     * @return string
     */
    abstract public function getPDOExtension(): string;

    /**
     * Genera SQL para crear una columna AUTO_INCREMENT / SERIAL
     *
     * @param string $columnName Nombre de la columna
     * @return string Definición SQL
     */
    abstract public function autoIncrementColumn(string $columnName = 'id'): string;

    /**
     * Obtiene la función NOW() o equivalente
     *
     * @return string
     */
    public function now(): string
    {
        return 'NOW()';
    }

    /**
     * Genera INSERT IGNORE o equivalente
     *
     * @param string $table Nombre de la tabla
     * @param array $columns Columnas
     * @return string Consulta SQL
     */
    abstract public function insertIgnore(string $table, array $columns): string;
}

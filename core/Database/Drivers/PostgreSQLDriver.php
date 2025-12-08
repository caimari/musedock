<?php

namespace Screenart\Musedock\Database\Drivers;

use Screenart\Musedock\Database\DatabaseDriver;

/**
 * Driver para PostgreSQL
 */
class PostgreSQLDriver extends DatabaseDriver
{
    public function getDSN(string $host, string $db, int $port = null): string
    {
        $port = $port ?? 5432;
        return "pgsql:host={$host};port={$port};dbname={$db};options='--client_encoding=UTF8'";
    }

    public function escapeIdentifier(string $identifier): string
    {
        // PostgreSQL usa comillas dobles
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    public function upsertQuery(string $table, array $columns, array $updateColumns, string $conflictKey): string
    {
        $table = $this->escapeIdentifier($table);
        $columnNames = array_map([$this, 'escapeIdentifier'], $columns);
        $placeholders = array_fill(0, count($columns), '?');

        $updates = [];
        foreach ($updateColumns as $col) {
            $escapedCol = $this->escapeIdentifier($col);
            // En PostgreSQL necesitamos referenciar la tabla para updates en ON CONFLICT
            $updates[] = "{$escapedCol} = EXCLUDED.{$escapedCol}";
        }

        $conflictKeyEscaped = $this->escapeIdentifier($conflictKey);

        return sprintf(
            "INSERT INTO %s (%s) VALUES (%s) ON CONFLICT (%s) DO UPDATE SET %s",
            $table,
            implode(', ', $columnNames),
            implode(', ', $placeholders),
            $conflictKeyEscaped,
            implode(', ', $updates)
        );
    }

    public function dateAdd(string $date, int $value, string $unit): string
    {
        // PostgreSQL usa sintaxis: date + INTERVAL 'X unit'
        return "({$date} + INTERVAL '{$value} {$unit}')";
    }

    public function dateSub(string $date, int $value, string $unit): string
    {
        // PostgreSQL usa sintaxis: date - INTERVAL 'X unit'
        return "({$date} - INTERVAL '{$value} {$unit}')";
    }

    public function dateDiff(string $date1, string $date2, string $unit = 'SECOND'): string
    {
        // PostgreSQL: EXTRACT(EPOCH FROM (date2 - date1))
        $diff = "EXTRACT(EPOCH FROM ({$date2} - {$date1}))";

        switch (strtoupper($unit)) {
            case 'SECOND':
                return $diff;
            case 'MINUTE':
                return "({$diff} / 60)";
            case 'HOUR':
                return "({$diff} / 3600)";
            case 'DAY':
                return "({$diff} / 86400)";
            default:
                return $diff;
        }
    }

    public function getDriverName(): string
    {
        return 'pgsql';
    }

    public function getPDOExtension(): string
    {
        return 'pdo_pgsql';
    }

    public function autoIncrementColumn(string $columnName = 'id'): string
    {
        $col = $this->escapeIdentifier($columnName);
        // PostgreSQL moderno prefiere GENERATED ALWAYS AS IDENTITY
        return "{$col} INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY";
    }

    public function insertIgnore(string $table, array $columns): string
    {
        $table = $this->escapeIdentifier($table);
        $columnNames = array_map([$this, 'escapeIdentifier'], $columns);
        $placeholders = array_fill(0, count($columns), '?');

        // PostgreSQL no tiene INSERT IGNORE, usamos ON CONFLICT DO NOTHING
        // Nota: esto requiere que haya una constraint única en la tabla
        return sprintf(
            "INSERT INTO %s (%s) VALUES (%s) ON CONFLICT DO NOTHING",
            $table,
            implode(', ', $columnNames),
            implode(', ', $placeholders)
        );
    }

    public function now(): string
    {
        // PostgreSQL soporta NOW() y CURRENT_TIMESTAMP
        return 'CURRENT_TIMESTAMP';
    }
}

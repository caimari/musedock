<?php

namespace Screenart\Musedock\Database\Drivers;

use Screenart\Musedock\Database\DatabaseDriver;

/**
 * Driver para MySQL/MariaDB
 */
class MySQLDriver extends DatabaseDriver
{
    public function getDSN(string $host, string $db, int $port = null): string
    {
        $port = $port ?? 3306;
        return "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
    }

    public function escapeIdentifier(string $identifier): string
    {
        // MySQL usa backticks
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    public function upsertQuery(string $table, array $columns, array $updateColumns, string $conflictKey): string
    {
        $table = $this->escapeIdentifier($table);
        $columnNames = array_map([$this, 'escapeIdentifier'], $columns);
        $placeholders = array_fill(0, count($columns), '?');

        $updates = [];
        foreach ($updateColumns as $col) {
            $escapedCol = $this->escapeIdentifier($col);
            $updates[] = "{$escapedCol} = VALUES({$escapedCol})";
        }

        return sprintf(
            "INSERT INTO %s (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s",
            $table,
            implode(', ', $columnNames),
            implode(', ', $placeholders),
            implode(', ', $updates)
        );
    }

    public function dateAdd(string $date, int $value, string $unit): string
    {
        return "DATE_ADD({$date}, INTERVAL {$value} {$unit})";
    }

    public function dateSub(string $date, int $value, string $unit): string
    {
        return "DATE_SUB({$date}, INTERVAL {$value} {$unit})";
    }

    public function dateDiff(string $date1, string $date2, string $unit = 'SECOND'): string
    {
        switch (strtoupper($unit)) {
            case 'SECOND':
                return "TIMESTAMPDIFF(SECOND, {$date1}, {$date2})";
            case 'MINUTE':
                return "TIMESTAMPDIFF(MINUTE, {$date1}, {$date2})";
            case 'HOUR':
                return "TIMESTAMPDIFF(HOUR, {$date1}, {$date2})";
            case 'DAY':
                return "TIMESTAMPDIFF(DAY, {$date1}, {$date2})";
            default:
                return "TIMESTAMPDIFF(SECOND, {$date1}, {$date2})";
        }
    }

    public function getDriverName(): string
    {
        return 'mysql';
    }

    public function getPDOExtension(): string
    {
        return 'pdo_mysql';
    }

    public function autoIncrementColumn(string $columnName = 'id'): string
    {
        $col = $this->escapeIdentifier($columnName);
        return "{$col} INT UNSIGNED AUTO_INCREMENT PRIMARY KEY";
    }

    public function insertIgnore(string $table, array $columns): string
    {
        $table = $this->escapeIdentifier($table);
        $columnNames = array_map([$this, 'escapeIdentifier'], $columns);
        $placeholders = array_fill(0, count($columns), '?');

        return sprintf(
            "INSERT IGNORE INTO %s (%s) VALUES (%s)",
            $table,
            implode(', ', $columnNames),
            implode(', ', $placeholders)
        );
    }
}

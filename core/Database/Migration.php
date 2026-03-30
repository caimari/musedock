<?php

namespace Screenart\Musedock\Database;

use PDO;

/**
 * Clase base para migraciones PHP
 * Las migraciones deben extender esta clase e implementar up() y down()
 */
abstract class Migration
{
    protected ?PDO $db = null;
    protected string $driver = 'mysql';

    /**
     * Establece la conexión a la base de datos
     */
    public function setConnection(PDO $db, string $driver = 'mysql'): void
    {
        $this->db = $db;
        $this->driver = strtolower($driver);
    }

    /**
     * Obtiene el nombre del driver de base de datos
     */
    protected function getDriverName(): string
    {
        return $this->driver;
    }

    /**
     * Ejecuta una consulta SQL
     */
    protected function execute(string $sql): bool
    {
        if ($this->db === null) {
            throw new \Exception('Database connection not set');
        }

        try {
            $this->db->exec($sql);
            return true;
        } catch (\PDOException $e) {
            throw new \Exception("SQL Error: " . $e->getMessage());
        }
    }

    /**
     * Ejecuta una consulta y retorna el resultado
     */
    protected function query(string $sql): \PDOStatement
    {
        if ($this->db === null) {
            throw new \Exception('Database connection not set');
        }

        return $this->db->query($sql);
    }

    /**
     * Verifica si una tabla existe
     */
    protected function tableExists(string $table): bool
    {
        if ($this->db === null) {
            return false;
        }

        try {
            if ($this->driver === 'pgsql') {
                $sql = "SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = ?)";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$table]);
                $result = (bool) $stmt->fetchColumn();
            } else {
                // MySQL/MariaDB - usar information_schema para evitar problemas con unbuffered queries
                $sql = "SELECT COUNT(*) FROM information_schema.tables
                        WHERE table_schema = DATABASE() AND table_name = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$table]);
                $result = (int) $stmt->fetchColumn() > 0;
            }
            $stmt->closeCursor();
            return $result;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Verifica si una columna existe en una tabla
     */
    protected function columnExists(string $table, string $column): bool
    {
        if ($this->db === null) {
            return false;
        }

        try {
            if ($this->driver === 'pgsql') {
                $sql = "SELECT COUNT(*) FROM information_schema.columns
                        WHERE table_name = ? AND column_name = ?";
            } else {
                // MySQL/MariaDB - usar information_schema para mayor compatibilidad
                $sql = "SELECT COUNT(*) FROM information_schema.columns
                        WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?";
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$table, $column]);
            $result = (int) $stmt->fetchColumn() > 0;
            $stmt->closeCursor();
            return $result;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Método que ejecuta la migración
     */
    abstract public function up(): void;

    /**
     * Método que revierte la migración
     */
    abstract public function down(): void;
}

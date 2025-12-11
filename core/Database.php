<?php

namespace Screenart\Musedock;

use PDO;
use PDOException;
use Screenart\Musedock\Database\QueryBuilder;
use Screenart\Musedock\Database\DatabaseDriver;
use Screenart\Musedock\Database\Drivers\MySQLDriver;
use Screenart\Musedock\Database\Drivers\PostgreSQLDriver;

class Database
{
    private static ?PDO $connection = null;
    private static ?DatabaseDriver $driver = null;

    /**
     * Obtiene el driver de base de datos configurado
     *
     * @return DatabaseDriver
     */
    public static function getDriver(): DatabaseDriver
    {
        if (self::$driver === null) {
            $config = require __DIR__ . '/../config/config.php';
            $driverName = $config['db']['driver'] ?? 'mysql';

            self::$driver = match(strtolower($driverName)) {
                'pgsql', 'postgresql', 'postgres' => new PostgreSQLDriver(),
                'mysql', 'mariadb' => new MySQLDriver(),
                default => new MySQLDriver(), // Default a MySQL por compatibilidad
            };
        }

        return self::$driver;
    }

    public static function connect(): PDO
    {
        if (self::$connection === null) {
            $config = require __DIR__ . '/../config/config.php';

            if (!isset($config['db'])) {
                throw new \Exception("Falta la configuración de base de datos. ¿Has ejecutado el instalador?");
            }

            $host = $config['db']['host'] ?? 'localhost';
            $db = $config['db']['name'] ?? '';
            $user = $config['db']['user'] ?? '';
            $pass = $config['db']['pass'] ?? '';
            $port = $config['db']['port'] ?? null;

            // Obtener el driver y generar el DSN dinámicamente
            $driver = self::getDriver();
            $dsn = $driver->getDSN($host, $db, $port);

            try {
                self::$connection = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } catch (PDOException $e) {
                Logger::log("Error al conectar a la base de datos: " . $e->getMessage(), 'ERROR');
                throw new \Exception("Error de conexión a la base de datos.");
            }
        }

        return self::$connection;
    }

    public static function query(string $sql, array $params = [])
    {
        $stmt = self::connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = [])
    {
        return self::query($sql, $params)->fetch();
    }

    public static function fetchAll(string $sql, array $params = [])
    {
        return self::query($sql, $params)->fetchAll();
    }

	public static function insert(string $sql, array $params = [])
	{
		self::query($sql, $params);
		return self::connect()->lastInsertId();
	}

	public static function table(string $tableName)
	{
		return new QueryBuilder($tableName);
	}

    /**
     * Escapa un nombre de columna/identificador según el driver de BD
     * MySQL usa backticks: `key`
     * PostgreSQL usa comillas dobles: "key"
     *
     * @param string $identifier Nombre de columna o tabla
     * @return string Identificador escapado
     */
    public static function quoteIdentifier(string $identifier): string
    {
        $pdo = self::connect();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            return '`' . str_replace('`', '``', $identifier) . '`';
        } else {
            // PostgreSQL y otros
            return '"' . str_replace('"', '""', $identifier) . '"';
        }
    }

    /**
     * Alias corto para quoteIdentifier
     */
    public static function qi(string $identifier): string
    {
        return self::quoteIdentifier($identifier);
    }

    /**
     * Verifica si el driver actual es MySQL
     */
    public static function isMySQL(): bool
    {
        $pdo = self::connect();
        return $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql';
    }

    /**
     * Verifica si el driver actual es PostgreSQL
     */
    public static function isPostgreSQL(): bool
    {
        $pdo = self::connect();
        return $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql';
    }
}
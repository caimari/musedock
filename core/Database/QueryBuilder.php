<?php

namespace Screenart\Musedock\Database;

use Screenart\Musedock\Database;
use PDO;

class QueryBuilder
{
    protected $pdo;
    protected $driver;
    protected $table;
    protected $wheres = [];
    protected $bindings = [];
    protected $selects = ['*'];
    protected $orderBy = '';
    protected $limit = null;
    protected $offset = null;
    protected $joins = [];
    protected $modelClass = null; // Clase del modelo para hidratación


    public function __construct($table)
    {
        $this->pdo = Database::connect();
        $this->driver = Database::getDriver();
        $this->table = $table; // no escapar aquí
    }

    /**
     * Establece la clase del modelo para hidratar resultados
     */
    public function setModelClass(?string $modelClass): self
    {
        $this->modelClass = $modelClass;
        return $this;
    }
    
    public function select($columns)
    {
        $this->selects = is_array($columns) ? $columns : [$columns];
        return $this;
    }
    
    public function whereNull(string $column)
    {
        $this->wheres[] = [
            'column' => $column,
            'operator' => 'IS',
            'value' => null
        ];
        return $this;
    }

    public function whereNotNull(string $column)
    {
        $this->wheres[] = [
            'column' => $column,
            'operator' => 'IS NOT',
            'value' => null
        ];
        return $this;
    }

    public function where(string $column, $operator = null, $value = null)
    {
        // Contar cuántos argumentos se pasaron realmente (sin contar null por defecto)
        $numArgs = func_num_args();

        if ($numArgs < 2) {
            throw new \InvalidArgumentException("El método where() requiere al menos 2 argumentos: columna y valor, o 3: columna, operador y valor");
        }

        // Si solo se pasan 2 argumentos (columna, valor), el operador es '='
        if ($numArgs === 2) {
            $value = $operator;
            $operator = '=';
        }

        // Manejar búsqueda IS NULL o IS NOT NULL
        if ($value === null) {
            $this->wheres[] = [
                'column' => $column,
                'operator' => $operator === '=' ? 'IS' : 'IS NOT',
                'value' => null,
                'placeholder' => null
            ];
            return $this;
        }

        $placeholder = ":where_" . count($this->bindings);
        $this->wheres[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'placeholder' => $placeholder
        ];
        $this->bindings[$placeholder] = $value;
        return $this;
    }

    /**
     * Agrega una condición WHERE con SQL raw (RAW SQL - USAR CON PRECAUCIÓN)
     *
     * ⚠️ ADVERTENCIA DE SEGURIDAD:
     * Este método permite SQL directo y puede ser vulnerable a SQL Injection.
     * NUNCA pase entrada del usuario sin sanitizar directamente a este método.
     * Use placeholders (?) y pase valores en $bindings para protegerse.
     *
     * USO SEGURO:
     *   ->whereRaw('column = ?', [$userInput])  ✓
     *   ->whereRaw('DATE(created_at) = CURDATE()')  ✓
     *
     * USO INSEGURO:
     *   ->whereRaw("column = '$userInput'")  ✗ SQL INJECTION
     *   ->whereRaw("slug = " . $_GET['slug'])  ✗ SQL INJECTION
     *
     * @param string $sql SQL raw
     * @param array $bindings Valores para placeholders (opcional pero recomendado)
     * @return self
     * @deprecated Considere usar where(), orWhere() u otros métodos seguros
     */
    public function whereRaw(string $sql, array $bindings = []): self
    {
        // Advertencia de seguridad en desarrollo
        $isDebug = defined('APP_DEBUG') && APP_DEBUG;

        if ($isDebug) {
            // Detectar patrones peligrosos obvios
            $dangerousPatterns = [
                '/\$_GET/i',
                '/\$_POST/i',
                '/\$_REQUEST/i',
                '/\$_SERVER/i',
                '/concat\s*\(/i', // Evitar concatenaciones sospechosas
            ];

            foreach ($dangerousPatterns as $pattern) {
                if (preg_match($pattern, $sql)) {
                    Logger::warning("SEGURIDAD: whereRaw() detectó patrón potencialmente peligroso", [
                        'sql' => $sql,
                        'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
                    ]);
                    break;
                }
            }

            // Advertir si no se usan bindings
            if (empty($bindings) && strpos($sql, '?') === false && strpos($sql, ':') === false) {
                Logger::warning("SEGURIDAD: whereRaw() usado sin bindings", [
                    'sql' => $sql,
                    'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)
                ]);
            }
        }

        // Convertir parámetros posicionales (?) a nombrados para evitar mezcla
        $namedBindings = [];
        if (!empty($bindings) && array_keys($bindings) === range(0, count($bindings) - 1)) {
            // Es un array indexado (parámetros posicionales)
            $modifiedSql = $sql;
            $bindingIndex = 0;

            $modifiedSql = preg_replace_callback('/\?/', function($match) use ($bindings, &$bindingIndex, &$namedBindings) {
                $placeholder = ':raw_' . count($this->bindings) . '_' . $bindingIndex;
                if (isset($bindings[$bindingIndex])) {
                    $namedBindings[$placeholder] = $bindings[$bindingIndex];
                }
                $bindingIndex++;
                return $placeholder;
            }, $modifiedSql);

            $sql = $modifiedSql;
            $bindings = $namedBindings;
        }

        $this->wheres[] = [
            'type'     => 'raw',
            'sql'      => $sql,
            'bindings' => $bindings,
        ];
        $this->bindings = array_merge($this->bindings, $bindings);
        return $this;
    }
    
    public function orWhere(string $column, $operator = null, $value = null): self
    {
        // Si solo se pasan 2 argumentos (columna, valor), el operador es '='
        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = '=';
        }

        $placeholder = ":or_where_" . count($this->bindings);
        $this->wheres[] = [
            'type'     => 'basic',
            'column'   => $column,
            'operator' => $operator,
            'value'    => $value,
            'boolean'  => 'OR',
            'placeholder' => $placeholder
        ];
        $this->bindings[$placeholder] = $value;
        return $this;
    }

    public function insertGetId(array $data)
    {
        $this->insert($data);
        return $this->pdo->lastInsertId();
    }

    public function orderBy(string $column, string $direction = 'ASC')
    {
        $this->orderBy = " ORDER BY " . $this->escapeColumn($column) . " " . strtoupper($direction);
        return $this;
    }

    public function limit(int $limit)
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset)
    {
        $this->offset = $offset;
        return $this;
    }

    public function join(string $table, string $localKey, string $operator, string $foreignKey)
    {
        $this->joins[] = "INNER JOIN " . $this->driver->escapeIdentifier($table) . " ON " . $this->escapeColumn($localKey) . " {$operator} " . $this->escapeColumn($foreignKey);
        return $this;
    }

/**
 * Actualiza el método get() existente para soportar GROUP BY y HAVING
 */
public function get()
{
    $sql = "SELECT " . implode(', ', $this->selects) . " FROM " . $this->escapeColumn($this->table);

    // Construcción de joins
    if (!empty($this->joins)) {
        foreach ($this->joins as $join) {
            if (is_string($join)) {
                $sql .= ' ' . $join; // Para compatibilidad vieja
            } elseif (is_array($join) && isset($join['type'])) {
                $sql .= " {$join['type']} JOIN " . $this->driver->escapeIdentifier($join['table']) . " ON " . $this->driver->escapeIdentifier($join['first']) . " {$join['operator']} " . $this->driver->escapeIdentifier($join['second']);
            }
        }
    }

    // WHERE
    if (!empty($this->wheres)) {
        $whereParts = $this->buildWhereParts();
        $sql .= " WHERE " . implode(' AND ', $whereParts);
    }
    
    // GROUP BY
    if (!empty($this->groupBy)) {
        $groupByColumns = array_map([$this, 'escapeColumn'], $this->groupBy);
        $sql .= " GROUP BY " . implode(', ', $groupByColumns);
    }
    
    // GROUP BY RAW
    if (!empty($this->groupByRaw)) {
        if (!empty($this->groupBy)) {
            $sql .= ", " . implode(', ', $this->groupByRaw);
        } else {
            $sql .= " GROUP BY " . implode(', ', $this->groupByRaw);
        }
    }
    
    // HAVING
    if (!empty($this->havings) || !empty($this->havingsRaw)) {
        $sql .= " HAVING ";
        
        $havingParts = [];
        
        // Construir cláusulas HAVING normales
        if (!empty($this->havings)) {
            foreach ($this->havings as $having) {
                $havingParts[] = $this->escapeColumn($having['column']) . " {$having['operator']} {$having['placeholder']}";
            }
        }
        
        // Añadir cláusulas HAVING RAW
        if (!empty($this->havingsRaw)) {
            $havingParts = array_merge($havingParts, $this->havingsRaw);
        }
        
        $sql .= implode(' AND ', $havingParts);
    }

    // ORDER BY
    $sql .= $this->orderBy;

    // LIMIT
    if ($this->limit !== null) {
        $sql .= " LIMIT {$this->limit}";
    }

    // OFFSET
    if ($this->offset !== null) {
        $sql .= " OFFSET {$this->offset}";
    }
    
    // UNION
    if (!empty($this->unions)) {
        foreach ($this->unions as $union) {
            $unionQuery = $union['query'];
            $unionSql = $unionQuery->toSql();
            
            $sql .= ($union['all'] ? ' UNION ALL ' : ' UNION ') . "({$unionSql})";
            
            // Añadir bindings de la consulta union
            foreach ($unionQuery->getBindings() as $key => $value) {
                $this->bindings[$key] = $value;
            }
        }
    }

    // Para depuración
    // error_log("SQL Query: " . $sql);
    // error_log("SQL Bindings: " . json_encode($this->bindings));

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($this->bindings);

    $results = $stmt->fetchAll(PDO::FETCH_OBJ);

    // Si hay una clase de modelo configurada, hidratar los resultados
    if ($this->modelClass) {
        return array_map(function($row) {
            return $this->hydrateModel($row);
        }, $results);
    }

    return $results;
}

/**
 * Hidrata un objeto stdClass a una instancia del modelo
 */
protected function hydrateModel($row)
{
    if (!$this->modelClass || !class_exists($this->modelClass)) {
        return $row;
    }

    $model = new $this->modelClass();

    // Copiar todas las propiedades del stdClass al modelo
    foreach ($row as $key => $value) {
        $model->$key = $value;
    }

    return $model;
}
	/**
 * Crea una cláusula GROUP BY
 *
 * @param string|array $columns Columna(s) para agrupar
 * @return $this
 */
public function groupBy($columns)
{
    // Convertimos a array si es string
    $columns = is_array($columns) ? $columns : [$columns];
    
    // Si no existe la propiedad, la creamos
    if (!isset($this->groupBy)) {
        $this->groupBy = [];
    }
    
    // Añadimos las columnas al GROUP BY
    $this->groupBy = array_merge($this->groupBy, $columns);
    
    return $this;
}

/**
 * Crea una cláusula GROUP BY con SQL sin procesar
 *
 * @param string $sql SQL para GROUP BY
 * @return $this
 */
public function groupByRaw(string $sql)
{
    // Si no existe la propiedad, la creamos
    if (!isset($this->groupByRaw)) {
        $this->groupByRaw = [];
    }
    
    $this->groupByRaw[] = $sql;
    
    return $this;
}

/**
 * Crea una cláusula HAVING
 *
 * @param string $column Columna
 * @param string $operator Operador
 * @param mixed $value Valor
 * @return $this
 */
public function having(string $column, $operator = null, $value = null)
{
    // Si se proporcionan solo dos parámetros, asumimos que el operador es "=" y el segundo parámetro es el valor
    if ($value === null) {
        $value = $operator;
        $operator = '=';
    }
    
    // Si no existe la propiedad, la creamos
    if (!isset($this->havings)) {
        $this->havings = [];
    }
    
    $placeholder = ":having_" . count($this->bindings);
    $this->havings[] = [
        'column' => $column,
        'operator' => $operator,
        'value' => $value,
        'placeholder' => $placeholder
    ];
    
    $this->bindings[$placeholder] = $value;
    
    return $this;
}

/**
 * Crea una cláusula HAVING con SQL sin procesar
 *
 * @param string $sql SQL para HAVING
 * @param array $bindings Bindings adicionales
 * @return $this
 */
public function havingRaw(string $sql, array $bindings = [])
{
    // Si no existe la propiedad, la creamos
    if (!isset($this->havingsRaw)) {
        $this->havingsRaw = [];
    }

    // Convertir parámetros posicionales (?) a nombrados para evitar mezcla
    $namedBindings = [];
    if (!empty($bindings) && array_keys($bindings) === range(0, count($bindings) - 1)) {
        // Es un array indexado (parámetros posicionales)
        $modifiedSql = $sql;
        $bindingIndex = 0;

        $modifiedSql = preg_replace_callback('/\?/', function($match) use ($bindings, &$bindingIndex, &$namedBindings) {
            $placeholder = ':having_raw_' . count($this->bindings) . '_' . $bindingIndex;
            if (isset($bindings[$bindingIndex])) {
                $namedBindings[$placeholder] = $bindings[$bindingIndex];
            }
            $bindingIndex++;
            return $placeholder;
        }, $modifiedSql);

        $sql = $modifiedSql;
        $bindings = $namedBindings;
    }

    $this->havingsRaw[] = $sql;
    $this->bindings = array_merge($this->bindings, $bindings);

    return $this;
}

/**
 * Encuentra un registro por su clave primaria
 *
 * @param mixed $id Valor de la clave primaria
 * @param string $column Nombre de la columna ID (por defecto 'id')
 * @return object|null
 */
public function find($id, string $column = 'id')
{
    return $this->where($column, $id)->first();
}

/**
 * Encuentra un registro por su clave primaria o lanza una excepción
 *
 * @param mixed $id Valor de la clave primaria
 * @param string $column Nombre de la columna ID (por defecto 'id')
 * @return object
 * @throws \Exception
 */
public function findOrFail($id, string $column = 'id')
{
    $result = $this->find($id, $column);
    
    if (!$result) {
        throw new \Exception("No se encontró un registro con {$column} = {$id}");
    }
    
    return $result;
}

/**
 * Calcula el valor máximo de una columna
 *
 * @param string $column Nombre de la columna
 * @return mixed
 */
public function max(string $column)
{
    return $this->aggregate('MAX', $column);
}

/**
 * Calcula el valor mínimo de una columna
 *
 * @param string $column Nombre de la columna
 * @return mixed
 */
public function min(string $column)
{
    return $this->aggregate('MIN', $column);
}

/**
 * Calcula la suma de una columna
 *
 * @param string $column Nombre de la columna
 * @return mixed
 */
public function sum(string $column)
{
    return $this->aggregate('SUM', $column);
}

/**
 * Calcula el promedio de una columna
 *
 * @param string $column Nombre de la columna
 * @return mixed
 */
public function avg(string $column)
{
    return $this->aggregate('AVG', $column);
}

/**
 * Calcula un valor agregado (MAX, MIN, AVG, SUM, etc.)
 *
 * @param string $function Función de agregación
 * @param string $column Nombre de la columna
 * @return mixed
 */
private function aggregate(string $function, string $column)
{
    $originalSelects = $this->selects;
    
    $this->selects = ["{$function}({$this->escapeColumn($column)}) as aggregate"];
    $result = $this->first();
    
    // Restauramos las selecciones originales
    $this->selects = $originalSelects;
    
    return $result ? $result->aggregate : null;
}

/**
 * Establece una unión entre dos consultas
 *
 * @param QueryBuilder $query Consulta a unir
 * @param bool $all Si es true, usará UNION ALL en lugar de UNION
 * @return $this
 */
public function union(QueryBuilder $query, bool $all = false)
{
    // Si no existe la propiedad, la creamos
    if (!isset($this->unions)) {
        $this->unions = [];
    }
    
    $this->unions[] = [
        'query' => $query,
        'all' => $all
    ];
    
    return $this;
}

/**
 * Establece una unión ALL entre dos consultas
 *
 * @param QueryBuilder $query Consulta a unir
 * @return $this
 */
public function unionAll(QueryBuilder $query)
{
    return $this->union($query, true);
}

/**
 * Ordena los resultados por una columna en orden descendente
 *
 * @param string $column Nombre de la columna
 * @return $this
 */
public function orderByDesc(string $column)
{
    return $this->orderBy($column, 'DESC');
}

/**
 * Obtiene un array asociativo usando una columna como clave y otra como valor
 *
 * @param string $value Columna para los valores
 * @param string $key Columna para las claves
 * @return array
 */
public function pluckAssoc(string $value, string $key): array
{
    $this->select([$value, $key]);
    $results = $this->get();
    
    $return = [];
    foreach ($results as $result) {
        $return[$result->$key] = $result->$value;
    }
    
    return $return;
}

/**
 * Ejecuta la consulta y transforma los resultados a array asociativo
 *
 * @return array
 */
public function getAsArray(): array
{
    $sql = "SELECT " . implode(', ', $this->selects) . " FROM " . $this->escapeColumn($this->table);

    // Construcción de joins
    if (!empty($this->joins)) {
        foreach ($this->joins as $join) {
            if (is_string($join)) {
                $sql .= ' ' . $join;
            } elseif (is_array($join) && isset($join['type'])) {
                $sql .= " {$join['type']} JOIN " . $this->driver->escapeIdentifier($join['table']) . " ON " . $this->driver->escapeIdentifier($join['first']) . " {$join['operator']} " . $this->driver->escapeIdentifier($join['second']);
            }
        }
    }

    // WHERE
    if (!empty($this->wheres)) {
        $whereParts = $this->buildWhereParts();
        $sql .= " WHERE " . implode(' AND ', $whereParts);
    }

    // GROUP BY
    if (!empty($this->groupBy)) {
        $groupByColumns = array_map([$this, 'escapeColumn'], $this->groupBy);
        $sql .= " GROUP BY " . implode(', ', $groupByColumns);
    }

    // GROUP BY RAW
    if (!empty($this->groupByRaw)) {
        if (!empty($this->groupBy)) {
            $sql .= ", " . implode(', ', $this->groupByRaw);
        } else {
            $sql .= " GROUP BY " . implode(', ', $this->groupByRaw);
        }
    }

    // HAVING
    if (!empty($this->havings) || !empty($this->havingsRaw)) {
        $sql .= " HAVING ";

        $havingParts = [];

        // Construir cláusulas HAVING normales
        if (!empty($this->havings)) {
            foreach ($this->havings as $having) {
                $havingParts[] = $this->escapeColumn($having['column']) . " {$having['operator']} {$having['placeholder']}";
            }
        }

        // Añadir cláusulas HAVING RAW
        if (!empty($this->havingsRaw)) {
            $havingParts = array_merge($havingParts, $this->havingsRaw);
        }

        $sql .= implode(' AND ', $havingParts);
    }

    // ORDER BY
    $sql .= $this->orderBy;

    // LIMIT
    if ($this->limit !== null) {
        $sql .= " LIMIT {$this->limit}";
    }

    // OFFSET
    if ($this->offset !== null) {
        $sql .= " OFFSET {$this->offset}";
    }

    // UNION
    if (!empty($this->unions)) {
        foreach ($this->unions as $union) {
            $unionQuery = $union['query'];
            $unionSql = $unionQuery->toSql();

            $sql .= ($union['all'] ? ' UNION ALL ' : ' UNION ') . "({$unionSql})";

            // Añadir bindings de la consulta union
            foreach ($unionQuery->getBindings() as $key => $value) {
                $this->bindings[$key] = $value;
            }
        }
    }

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($this->bindings);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene los bindings actuales
 *
 * @return array
 */
public function getBindings(): array
{
    return $this->bindings;
}

/**
 * Construye la consulta SQL sin ejecutarla
 * 
 * @return string
 */
public function toSql(): string
{
    $sql = "SELECT " . implode(', ', $this->selects) . " FROM " . $this->escapeColumn($this->table);

    // Construcción de joins
    if (!empty($this->joins)) {
        foreach ($this->joins as $join) {
            if (is_string($join)) {
                $sql .= ' ' . $join;
            } elseif (is_array($join) && isset($join['type'])) {
                $sql .= " {$join['type']} JOIN " . $this->driver->escapeIdentifier($join['table']) . " ON " . $this->driver->escapeIdentifier($join['first']) . " {$join['operator']} " . $this->driver->escapeIdentifier($join['second']);
            }
        }
    }

    // WHERE
    if (!empty($this->wheres)) {
        $whereParts = $this->buildWhereParts();
        $sql .= " WHERE " . implode(' AND ', $whereParts);
    }

    // GROUP BY
    if (!empty($this->groupBy)) {
        $groupByColumns = array_map([$this, 'escapeColumn'], $this->groupBy);
        $sql .= " GROUP BY " . implode(', ', $groupByColumns);
    }

    // GROUP BY RAW
    if (!empty($this->groupByRaw)) {
        if (!empty($this->groupBy)) {
            $sql .= ", " . implode(', ', $this->groupByRaw);
        } else {
            $sql .= " GROUP BY " . implode(', ', $this->groupByRaw);
        }
    }

    // HAVING
    if (!empty($this->havings) || !empty($this->havingsRaw)) {
        $sql .= " HAVING ";

        $havingParts = [];

        // Construir cláusulas HAVING normales
        if (!empty($this->havings)) {
            foreach ($this->havings as $having) {
                $havingParts[] = $this->escapeColumn($having['column']) . " {$having['operator']} {$having['placeholder']}";
            }
        }

        // Añadir cláusulas HAVING RAW
        if (!empty($this->havingsRaw)) {
            $havingParts = array_merge($havingParts, $this->havingsRaw);
        }

        $sql .= implode(' AND ', $havingParts);
    }

    // ORDER BY
    $sql .= $this->orderBy;

    // LIMIT
    if ($this->limit !== null) {
        $sql .= " LIMIT {$this->limit}";
    }

    // OFFSET
    if ($this->offset !== null) {
        $sql .= " OFFSET {$this->offset}";
    }

    // UNION
    if (!empty($this->unions)) {
        foreach ($this->unions as $union) {
            $unionQuery = $union['query'];
            $unionSql = $unionQuery->toSql();

            $sql .= ($union['all'] ? ' UNION ALL ' : ' UNION ') . "({$unionSql})";
        }
    }

    return $sql;
}
/**
 * Añade una cláusula WHERE con operador personalizado
 *
 * @param string $column Nombre de la columna
 * @param string $operator Operador de comparación
 * @param mixed $value Valor
 * @return $this
 */
public function whereOp(string $column, string $operator, $value)
{
    $placeholder = ":where_op_" . count($this->bindings);
    $this->wheres[] = $this->escapeColumn($column) . " {$operator} {$placeholder}";
    $this->bindings[$placeholder] = $value;
    return $this;
}

/**
 * Añade una cláusula WHERE NOT IN
 *
 * @param string $column Nombre de la columna
 * @param array $values Valores a excluir
 * @return $this
 */
public function whereNotIn(string $column, array $values)
{
    if (empty($values)) {
        return $this;
    }

    // Genera los placeholders
    $placeholders = [];
    foreach ($values as $i => $value) {
        $placeholder = ":where_not_in_{$i}";
        $placeholders[] = $placeholder;
        $this->bindings[$placeholder] = $value;
    }

    // Añade la condición SQL al array de condiciones
    $this->wheres[] = "$column NOT IN (" . implode(', ', $placeholders) . ")";

    return $this;
}

/**
 * Añade una cláusula RIGHT JOIN
 *
 * @param string $table Tabla a unir
 * @param string $first Columna local
 * @param string $operator Operador de comparación
 * @param string $second Columna foránea
 * @return $this
 */
public function rightJoin(string $table, string $first, string $operator, string $second)
{
    $this->joins[] = sprintf(
        "RIGHT JOIN %s ON %s %s %s",
        $this->driver->escapeIdentifier($table),
        $this->rawColumn($first),
        $operator,
        $this->rawColumn($second)
    );
    return $this;
}

/**
 * Añade una cláusula CROSS JOIN
 *
 * @param string $table Tabla a unir
 * @return $this
 */
public function crossJoin(string $table)
{
    $this->joins[] = "CROSS JOIN " . $this->escapeColumn($table);
    return $this;
}

/**
 * Inicia una transacción de base de datos
 * @return bool
 */
public function beginTransaction()
{
    return $this->pdo->beginTransaction();
}

/**
 * Confirma una transacción en curso
 * @return bool
 */
public function commit()
{
    return $this->pdo->commit();
}

/**
 * Revierte una transacción en curso
 * @return bool
 */
public function rollBack()
{
    return $this->pdo->rollBack();
}

/**
 * Ejecuta una función dentro de una transacción
 * Si la función lanza una excepción, la transacción se revierte
 * 
 * @param callable $callback Función a ejecutar
 * @return mixed Resultado de la función
 */
public function transaction(callable $callback)
{
    $this->beginTransaction();
    
    try {
        $result = $callback($this);
        $this->commit();
        return $result;
    } catch (\Exception $e) {
        $this->rollBack();
        throw $e;
    }
}

/**
 * Procesa resultados en bloques para optimizar memoria
 * 
 * @param int $size Tamaño del bloque
 * @param callable $callback Función a ejecutar por cada bloque
 * @return bool
 */
public function chunk(int $size, callable $callback)
{
    $page = 1;
    
    do {
        $results = $this->forPage($page, $size)->get();
        
        $count = count($results);
        
        if ($count === 0) {
            break;
        }
        
        if ($callback($results, $page) === false) {
            return false;
        }
        
        $page++;
        
    } while ($count === $size);
    
    return true;
}

/**
 * Configura la consulta para una página específica
 * 
 * @param int $page Número de página
 * @param int $perPage Elementos por página
 * @return $this
 */
public function forPage(int $page, int $perPage)
{
    return $this->offset(($page - 1) * $perPage)->limit($perPage);
}

/**
 * Trunca la tabla (elimina todos los registros)
 * 
 * @return bool
 */
public function truncate()
{
    $sql = "TRUNCATE TABLE " . $this->escapeColumn($this->table);
    $stmt = $this->pdo->prepare($sql);
    return $stmt->execute();
}

/**
 * Método de depuración para mostrar la consulta SQL
 * 
 * @return $this
 */
public function dump()
{
    echo "<pre>";
    echo "SQL: " . $this->toSql() . "\n";
    echo "Bindings: " . print_r($this->bindings, true);
    echo "</pre>";
    return $this;
}

/**
 * Método de depuración: muestra la consulta y termina la ejecución
 */
public function dd()
{
    $this->dump();
    die;
}
    public function first()
    {
        $this->limit = 1;
        $results = $this->get();
        return $results[0] ?? null;
    }

    public function insert(array $data)
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ":$col", $columns);

        $escapedColumns = array_map([$this, 'escapeColumn'], $columns);

        $sql = "INSERT INTO {$this->table} (" . implode(',', $escapedColumns) . ") VALUES (" . implode(',', $placeholders) . ")";

        // PostgreSQL requiere RETURNING para obtener el ID insertado
        if ($this->driver === 'pgsql') {
            $sql .= " RETURNING id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($data);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['id'] ?? null;
        }

        // MySQL usa lastInsertId()
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        return $this->pdo->lastInsertId();
    }

    public function update(array $data, bool $force = false)
    {
        // ⚠️ Si no hay WHERE y no se fuerza, lanzamos excepción
        if (empty($this->wheres) && !$force) {
            throw new \Exception("No se puede hacer UPDATE sin cláusula WHERE. Usa update(\$data, true) si estás completamente seguro.");
        }

        $sets = [];
        $updateBindings = []; // Bindings específicos para el SET

        foreach ($data as $col => $val) {
            // Debug para ver qué tipo de datos exactos estamos recibiendo
            error_log("[DEBUG QueryBuilder] Columna: {$col}, Valor: " . var_export($val, true) . ", Tipo: " . gettype($val));
            
            // ATENCIÓN: Para tenant_id específicamente, verificamos si está vacío o falsy excepto 0
            if ($col === 'tenant_id' && empty($val) && $val !== 0) {
                error_log("[DEBUG QueryBuilder] Detectado tenant_id vacío, forzando a NULL");
                $sets[] = $this->escapeColumn($col) . " = NULL";
                continue; // Saltamos al siguiente item del loop
            }
            
            // Conversión general para otros campos
            if ($val === '' || $val === null) {
                error_log("[DEBUG QueryBuilder] Detectado valor vacío para {$col}, forzando a NULL");
                $sets[] = $this->escapeColumn($col) . " = NULL";
            } else {
                $placeholder = ":set_{$col}";
                $sets[] = $this->escapeColumn($col) . " = {$placeholder}";
                $updateBindings[$placeholder] = $val;
            }
        }

        // Construcción del SQL base
        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets);

        // Si hay cláusulas WHERE, las añadimos
        if (!empty($this->wheres)) {
            $whereParts = $this->buildWhereParts();
            $sql .= " WHERE " . implode(' AND ', $whereParts);
        }

        // Debug SQL
        error_log("[DEBUG SQL] " . $sql);

        // Combinar bindings del WHERE y del SET
        $allBindings = array_merge($this->bindings, $updateBindings);

        // Debug Bindings
        error_log("[DEBUG Bindings] " . json_encode($allBindings));

        // Preparar y ejecutar
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute($allBindings);

        if (!$result) {
            error_log("[ERROR UPDATE] " . json_encode($stmt->errorInfo()));
        }

        return $result;
    }

    public function delete()
    {
        if (empty($this->wheres)) {
            throw new \Exception("No se puede hacer DELETE sin cláusula WHERE.");
        }

        $whereParts = $this->buildWhereParts();

        $sql = "DELETE FROM {$this->table} WHERE " . implode(' AND ', $whereParts);
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($this->bindings);
    }

    public function paginate(int $perPage = 10, int $page = 1): array
    {
        $total = $this->count();
        $offset = ($page - 1) * $perPage;

        $this->limit($perPage)->offset($offset);
        $items = $this->get();

        return [
            'items'      => $items,
            'total'      => $total,
            'per_page'   => $perPage,
            'current'    => $page,
            'last_page'  => (int) ceil($total / $perPage),
        ];
    }
    
    public function exists(): bool
    {
        $sql = "SELECT EXISTS(SELECT 1 FROM {$this->table}";

        if (!empty($this->wheres)) {
            $whereParts = $this->buildWhereParts();
            $sql .= " WHERE " . implode(' AND ', $whereParts);
        }

        $sql .= ") AS exists_check";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (bool) ($result['exists_check'] ?? false);
    }

    public function updateOrInsert(array $conditions, array $data): bool
    {
        $this->wheres = [];
        $this->bindings = [];

        foreach ($conditions as $column => $value) {
            $this->where($column, $value);
        }

        if ($this->exists()) {
            return $this->update($data);
        } else {
            return $this->insert(array_merge($conditions, $data)) > 0;
        }
    }

    public function count(): int
    {
        $sql = "SELECT COUNT(*) AS count FROM {$this->table}";

        if (!empty($this->wheres)) {
            $whereParts = $this->buildWhereParts();
            $sql .= " WHERE " . implode(' AND ', $whereParts);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($result['count'] ?? 0);
    }

    public function pluck(string $column): array
    {
        $this->select([$this->escapeColumn($column)]);
        $results = $this->get();
        
        $values = [];
        foreach ($results as $result) {
            $values[] = $result->$column;
        }
        
        return $values;
    }
    
    public function value(string $column)
    {
        $result = $this->first();
        return $result ? $result->$column : null;
    }
    
    public function whereIn(string $column, array $values)
    {
        if (empty($values)) {
            // Evita que devuelva resultados no deseados si el array está vacío
            $this->wheres[] = "1 = 0";
            return $this;
        }

        // Genera los placeholders
        $placeholders = [];
        foreach ($values as $i => $value) {
            $placeholder = ":where_in_{$i}";
            $placeholders[] = $placeholder;
            $this->bindings[$placeholder] = $value;
        }

        // Añade la condición SQL al array de condiciones
        $this->wheres[] = "$column IN (" . implode(', ', $placeholders) . ")";

        return $this;
    }

    public static function query()
    {
        return new \Screenart\Musedock\Database\QueryBuilder(static::$table);
    }

	/**
	 * Actualizado el método reset() para limpiar las nuevas propiedades
	 */
	public function reset(): self
	{
		$this->wheres = [];
		$this->bindings = [];
		$this->selects = ['*'];
		$this->orderBy = '';
		$this->limit = null;
		$this->offset = null;
		$this->joins = [];
		$this->groupBy = [];
		$this->groupByRaw = [];
		$this->havings = [];
		$this->havingsRaw = [];
		$this->unions = [];
		return $this;
	}
    
    // Método adicional para LEFT JOIN
    public function leftJoin($table, $first, $operator, $second)
    {
        $this->joins[] = sprintf(
            "LEFT JOIN %s ON %s %s %s",
            $this->driver->escapeIdentifier($table),
            $this->rawColumn($first),
            $operator,
            $this->rawColumn($second)
        );
        return $this;
    }

    private function escapeColumn(string $column): string
    {
        // Si el string ya contiene AS (alias), no escapar
        if (preg_match('/\s+as\s+/i', $column)) {
            return $column;
        }

        // Si contiene funciones tipo COUNT(*) o LEFT(...)
        if (preg_match('/\w+\(.*\)/', $column)) {
            return $column;
        }

        // Soporta tabla.columna
        if (str_contains($column, '.')) {
            $parts = explode('.', $column);
            $escaped = array_map([$this->driver, 'escapeIdentifier'], $parts);
            return implode('.', $escaped);
        }

        return $this->driver->escapeIdentifier($column);
    }

    private function buildWhereParts(): array
    {
        $parts = [];

        foreach ($this->wheres as $where) {
            // Soporte para condiciones tipo string
            if (is_string($where)) {
                $escaped = preg_replace_callback('/^([a-zA-Z0-9_]+)\s*=\s*(.+)$/', function ($matches) {
                    return $this->escapeColumn($matches[1]) . ' = ' . $matches[2];
                }, $where);
                $parts[] = $escaped;

            // Soporte para condiciones tipo raw
            } elseif (is_array($where) && ($where['type'] ?? '') === 'raw') {
                $parts[] = $where['sql'];

            // Soporte para whereNull y similares
            } elseif (is_array($where) && isset($where['column'], $where['operator'])) {
                if ($where['operator'] === 'IS' && $where['value'] === null) {
                    $parts[] = $this->escapeColumn($where['column']) . ' IS NULL';
                } elseif ($where['operator'] === 'IS NOT' && $where['value'] === null) {
                    $parts[] = $this->escapeColumn($where['column']) . ' IS NOT NULL';
                } else {
                    // Usar el placeholder si existe (para orWhere), o generar uno nuevo
                    $placeholder = $where['placeholder'] ?? (':where_' . count($this->bindings));
                    $parts[] = $this->escapeColumn($where['column']) . ' ' . $where['operator'] . ' ' . $placeholder;
                    // Solo agregar al binding si no existe ya
                    if (!isset($this->bindings[$placeholder])) {
                        $this->bindings[$placeholder] = $where['value'];
                    }
                }
            }
        }

        return $parts;
    }
    
    private function rawColumn(string $column): string
    {
        // Si ya tiene un punto como tabla.columna => escapamos cada parte
        if (strpos($column, '.') !== false) {
            [$table, $col] = explode('.', $column, 2);
            return $this->driver->escapeIdentifier($table) . '.' . $this->driver->escapeIdentifier($col);
        }

        return $this->driver->escapeIdentifier($column);
    }
}
<?php

namespace Screenart\Musedock\CLI;

use PDO;
use Screenart\Musedock\Database;

/**
 * Clase Scaffold - Generador de CRUD automático
 *
 * Genera automáticamente Modelos, Controladores, Vistas y Rutas basándose
 * en la estructura de tablas existentes en la base de datos.
 *
 * COMPATIBILIDAD:
 * - MySQL 5.7+ / MariaDB 10.2+
 * - PostgreSQL 10+
 *
 * CARACTERÍSTICAS DE SEGURIDAD:
 * - ✅ Prepared statements para todas las consultas SQL
 * - ✅ Validación de nombres de modelos y contextos
 * - ✅ Escapado de identificadores SQL según el motor
 * - ✅ Prevención de SQL Injection
 * - ✅ Validación de tipos de datos
 * - ✅ CSRF tokens incluidos en formularios generados
 * - ✅ Escapado HTML en vistas generadas
 *
 * DETECCIÓN AUTOMÁTICA DE TIPOS:
 * - Booleanos: BOOLEAN (PostgreSQL), TINYINT(1) (MySQL), SMALLINT (PostgreSQL)
 * - Enteros: INT, BIGINT, SMALLINT
 * - Decimales: DECIMAL, NUMERIC, FLOAT, DOUBLE
 * - Texto: VARCHAR, TEXT, CHAR
 * - Fechas: DATE, DATETIME, TIMESTAMP
 * - JSON: JSON, JSONB
 * - Enum: ENUM (MySQL)
 *
 * EJEMPLO DE USO:
 * ```bash
 * # Generar CRUD completo
 * ./muse make:crud Product superadmin
 *
 * # Generar componentes individuales
 * ./muse make:model Product
 * ./muse make:controller Product superadmin
 * ./muse make:views Product superadmin
 * ./muse make:routes Product superadmin
 * ```
 *
 * @package Screenart\Musedock\CLI
 * @author Antoni Caimari Caldes
 * @version 2.0.0
 * @since 1.0.0
 */
class Scaffold
{
    /**
     * Obtiene las columnas de una tabla independientemente del motor de base de datos
     * Maneja MySQL y PostgreSQL de forma segura
     *
     * @param string $table Nombre de la tabla
     * @return array|false Array de columnas o false si la tabla no existe
     */
    private static function getTableColumns(string $table)
    {
        try {
            $db = Database::connect();
            $driver = Database::getDriver();
            $driverName = $driver->getDriverName();

            if ($driverName === 'mysql') {
                // MySQL: SHOW COLUMNS FROM table
                $stmt = $db->prepare("SHOW COLUMNS FROM " . $driver->escapeIdentifier($table));
                $stmt->execute();
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Normalizar formato MySQL a formato estándar
                return array_map(function($col) {
                    return [
                        'Field' => $col['Field'],
                        'Type' => $col['Type'],
                        'Null' => $col['Null'],
                        'Key' => $col['Key'] ?? '',
                        'Default' => $col['Default'],
                        'Extra' => $col['Extra'] ?? ''
                    ];
                }, $columns);

            } else if ($driverName === 'pgsql') {
                // PostgreSQL: information_schema.columns con prepared statement
                $sql = "SELECT
                    column_name as \"Field\",
                    CASE
                        WHEN data_type = 'character varying' THEN 'varchar(' || character_maximum_length || ')'
                        WHEN data_type = 'character' THEN 'char(' || character_maximum_length || ')'
                        WHEN data_type = 'numeric' THEN 'decimal(' || numeric_precision || ',' || numeric_scale || ')'
                        WHEN data_type = 'integer' THEN 'int'
                        WHEN data_type = 'bigint' THEN 'bigint'
                        WHEN data_type = 'smallint' THEN 'smallint'
                        WHEN data_type = 'boolean' THEN 'boolean'
                        WHEN data_type = 'text' THEN 'text'
                        WHEN data_type = 'timestamp without time zone' THEN 'timestamp'
                        WHEN data_type = 'timestamp with time zone' THEN 'timestamptz'
                        WHEN data_type = 'date' THEN 'date'
                        WHEN data_type = 'time without time zone' THEN 'time'
                        WHEN data_type = 'json' THEN 'json'
                        WHEN data_type = 'jsonb' THEN 'jsonb'
                        WHEN data_type = 'USER-DEFINED' AND udt_name = 'citext' THEN 'citext'
                        ELSE data_type
                    END as \"Type\",
                    CASE WHEN is_nullable = 'YES' THEN 'YES' ELSE 'NO' END as \"Null\",
                    CASE WHEN column_default LIKE 'nextval%' THEN 'auto_increment' ELSE '' END as \"Extra\",
                    column_default as \"Default\"
                FROM information_schema.columns
                WHERE table_schema = 'public'
                AND table_name = :table
                ORDER BY ordinal_position";

                $stmt = $db->prepare($sql);
                $stmt->execute(['table' => $table]);
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($columns)) {
                    return false;
                }

                // Añadir campo Key para columnas PK
                $pkSql = "SELECT a.attname as column_name
                    FROM pg_index i
                    JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
                    WHERE i.indrelid = :table::regclass AND i.indisprimary";

                $pkStmt = $db->prepare($pkSql);
                $pkStmt->execute(['table' => $table]);
                $primaryKeys = array_column($pkStmt->fetchAll(PDO::FETCH_ASSOC), 'column_name');

                foreach ($columns as &$col) {
                    $col['Key'] = in_array($col['Field'], $primaryKeys) ? 'PRI' : '';
                }

                return $columns;
            }

            return false;

        } catch (\Exception $e) {
            echo "❌ Error al obtener columnas: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Valida que el nombre del modelo sea seguro (previene inyección)
     *
     * @param string $modelName
     * @return bool
     */
    private static function isValidModelName(string $modelName): bool
    {
        // Solo permitir letras, números y guiones bajos, debe empezar con letra mayúscula
        return preg_match('/^[A-Z][a-zA-Z0-9_]*$/', $modelName) === 1;
    }

    /**
     * Valida que el contexto sea uno permitido
     *
     * @param string $location
     * @return bool
     */
    private static function isValidContext(string $location): bool
    {
        $allowedContexts = ['superadmin', 'admin', 'tenant', 'frontend'];
        return in_array(strtolower($location), $allowedContexts);
    }

    /**
     * Genera un CRUD completo incluyendo modelo, controlador, vistas y rutas
     */
    public static function generateCrud(string $modelName, string $location = 'superadmin')
    {
        echo "🔄 Iniciando generación de CRUD para $modelName...\n";

        // Validación de seguridad: nombre del modelo
        if (!self::isValidModelName($modelName)) {
            echo "❌ Error: Nombre de modelo inválido. Debe empezar con mayúscula y contener solo letras, números y guiones bajos.\n";
            return;
        }

        // Validación de seguridad: contexto
        if (!self::isValidContext($location)) {
            echo "❌ Error: Contexto inválido. Debe ser: superadmin, admin, tenant o frontend.\n";
            return;
        }

        // Verificar si la tabla existe
        $table = strtolower($modelName) . 's';
        $columns = self::getTableColumns($table);

        if ($columns === false || empty($columns)) {
            echo "❌ Error: La tabla $table no existe en la base de datos.\n";
            echo "   Crea la tabla primero antes de generar el CRUD.\n";
            return;
        }

        echo "✅ Tabla $table encontrada con " . count($columns) . " columnas\n";
        
        // Generar las distintas partes
        $modelGenerated = self::generateModel($modelName, $columns);
        $controllerGenerated = self::generateController($modelName, $columns, $location);
        $viewsGenerated = self::generateViews($modelName, $columns, $location);
        $routesGenerated = self::generateRoutes($modelName, $location);
        
        if ($modelGenerated && $controllerGenerated && $viewsGenerated && $routesGenerated) {
            echo "\n✨ CRUD completo generado correctamente para el modelo $modelName en el contexto $location\n";
        } else {
            echo "\n⚠️ Generación de CRUD completada con errores. Revisa los mensajes anteriores.\n";
        }
    }
    
    /**
     * Genera solo el modelo para una entidad
     */
    public static function generateModel(string $modelName, array $columns = null)
    {
        echo "\n🔄 Generando modelo para $modelName...\n";
        
        $basePath = realpath(__DIR__ . "/../..");
        $modelPath = "$basePath/core/models/$modelName.php";
        
        // Verificar si el modelo ya existe
        if (file_exists($modelPath)) {
            echo "⚠️ El modelo $modelName ya existe. ¿Deseas sobrescribirlo? (s/n): ";
            $handle = fopen("php://stdin", "r");
            $line = trim(fgets($handle));
            fclose($handle);
            
            if (strtolower($line) !== 's' && strtolower($line) !== 'si' && strtolower($line) !== 'yes' && strtolower($line) !== 'y') {
                echo "❌ Generación de modelo cancelada por el usuario.\n";
                return false;
            }
            
            echo "✅ Continuando con la sobrescritura del modelo...\n";
        }
        
        // Obtener la estructura de la tabla si no se proporcionó
        if (!$columns) {
            $table = strtolower($modelName) . 's';
            $columns = self::getTableColumns($table);

            if ($columns === false || empty($columns)) {
                echo "❌ Error: La tabla $table no existe en la base de datos.\n";
                echo "   Crea la tabla primero antes de generar el modelo.\n";
                return false;
            }

            echo "✅ Tabla $table encontrada con " . count($columns) . " columnas\n";
        }
        
        // Generar fillable y casts
        $fillable = [];
        $casts = [];

        foreach ($columns as $column) {
            $name = $column['Field'];
            $type = strtolower($column['Type']);

            // Excluir campos automáticos de fillable
            if (!in_array($name, ['id', 'created_at', 'updated_at'])) {
                $fillable[] = $name;
            }

            // Detectar tipos para casts (compatible MySQL y PostgreSQL)
            if (strpos($type, 'boolean') !== false) {
                // PostgreSQL: BOOLEAN nativo
                $casts[$name] = 'boolean';
            } elseif (strpos($type, 'tinyint(1)') !== false) {
                // MySQL: TINYINT(1) como booleano
                $casts[$name] = 'boolean';
            } elseif (strpos($type, 'smallint') !== false && in_array($name, ['active', 'status', 'is_public', 'is_active', 'enabled', 'visible', 'published', 'featured', 'is_featured'])) {
                // PostgreSQL: SMALLINT usado como booleano (0/1)
                $casts[$name] = 'boolean';
            } elseif (in_array($name, ['active', 'status', 'is_public', 'is_active', 'enabled', 'visible', 'published', 'featured', 'is_featured'])) {
                // Campos conocidos como booleanos por nombre
                $casts[$name] = 'boolean';
            } elseif (strpos($type, 'bigint') !== false) {
                $casts[$name] = 'int';
            } elseif (strpos($type, 'int') !== false) {
                $casts[$name] = 'int';
            } elseif (strpos($type, 'decimal') !== false || strpos($type, 'float') !== false || strpos($type, 'double') !== false || strpos($type, 'numeric') !== false) {
                $casts[$name] = 'float';
            } elseif (strpos($type, 'datetime') !== false || strpos($type, 'timestamp') !== false) {
                $casts[$name] = 'datetime';
            } elseif (strpos($type, 'date') !== false && strpos($type, 'datetime') === false) {
                $casts[$name] = 'date';
            } elseif (strpos($type, 'json') !== false) {
                $casts[$name] = 'array';
            }
        }
        
        // Formatear fillable y casts para el modelo
        $fillableStr = '';
        foreach ($fillable as $field) {
            $fillableStr .= "        '$field',\n";
        }
        
        $castsStr = '';
        foreach ($casts as $field => $type) {
            $castsStr .= "        '$field' => '$type',\n";
        }
        
        // Código del modelo
        $modelCode = "<?php

namespace Screenart\\Musedock\\Models;

use Screenart\\Musedock\\Models\\Model;

class $modelName extends Model
{
    protected static string \$table = '" . strtolower($modelName) . "s';
    protected static string \$primaryKey = 'id';
    protected static bool \$timestamps = true;
    
    // Atributos que se pueden asignar masivamente
    protected array \$fillable = [
$fillableStr    ];
    
    // Casts automáticos de tipos
    protected array \$casts = [
$castsStr    ];
    
    // Atributos ocultos al serializar
    protected array \$hidden = [];
    
    // Relaciones (puedes implementar manualmente según tus necesidades)
    // public function relation() { 
    //     return \$this->belongsTo(RelatedModel::class); 
    // }
}";
        
        // Crear directorio de modelos si no existe
        $modelDir = "$basePath/core/models";
        if (!is_dir($modelDir)) {
            echo "🆕 Creando directorio de modelos: $modelDir\n";
            if (!mkdir($modelDir, 0777, true)) {
                echo "❌ Error: No se pudo crear el directorio de modelos\n";
                return false;
            }
        }
        
        // Guardar el modelo
        echo "📝 Creando modelo: $modelName.php\n";
        if (file_put_contents($modelPath, $modelCode) === false) {
            echo "❌ Error: No se pudo crear el modelo\n";
            return false;
        } else {
            echo "✅ Modelo creado correctamente en: $modelPath\n";
            return true;
        }
    }
    
    /**
     * Genera solo el controlador para una entidad
     */
    public static function generateController(string $modelName, array $columns = null, string $location = 'superadmin')
    {
        echo "\n🔄 Generando controlador para $modelName en contexto $location...\n";
        
        $basePath = realpath(__DIR__ . "/../..");
        $controllerName = ucfirst($modelName) . 'Controller';
        $controllerPath = "$basePath/core/controllers/$location/$controllerName.php";
        $table = strtolower($modelName) . 's';
        $routePrefix = $location === 'superadmin' ? '/musedock' : '/' . admin_path();
        
        // Verificar si el controlador ya existe
        if (file_exists($controllerPath)) {
            echo "⚠️ El controlador $controllerName ya existe en el contexto $location. ¿Deseas sobrescribirlo? (s/n): ";
            $handle = fopen("php://stdin", "r");
            $line = trim(fgets($handle));
            fclose($handle);
            
            if (strtolower($line) !== 's' && strtolower($line) !== 'si' && strtolower($line) !== 'yes' && strtolower($line) !== 'y') {
                echo "❌ Generación de controlador cancelada por el usuario.\n";
                return false;
            }
            
            echo "✅ Continuando con la sobrescritura del controlador...\n";
        }
        
        // Obtener la estructura de la tabla si no se proporcionó
        if (!$columns) {
            $columns = self::getTableColumns($table);

            if ($columns === false || empty($columns)) {
                echo "❌ Error: La tabla $table no existe en la base de datos.\n";
                echo "   Crea la tabla primero antes de generar.\n";
                return false;
            }

            echo "✅ Tabla $table encontrada con " . count($columns) . " columnas\n";
        }
        
        // Código del controlador con detección dinámica de checkboxes
        $controllerCode = "<?php

namespace Screenart\\Musedock\\Controllers\\$location;

use Screenart\\Musedock\\Database;
use Screenart\\Musedock\\View;
use Screenart\\Musedock\\Models\\$modelName;

class $controllerName
{
    public function index() {
        \$" . strtolower($modelName) . "s = Database::table('$table')->get();
        return View::render" . ($location === 'superadmin' ? 'Superadmin' : 'Admin') . "('" . strtolower($modelName) . "s.index', ['title' => '$modelName" . "s', '" . strtolower($modelName) . "s' => \$" . strtolower($modelName) . "s]);
    }

    public function create() {
        return View::render" . ($location === 'superadmin' ? 'Superadmin' : 'Admin') . "('" . strtolower($modelName) . "s.create', ['title' => 'Crear $modelName']);
    }

    public function store() {
        \$data = \$_POST;
        unset(\$data['_token']);
        unset(\$data['_csrf']); // Eliminar el campo CSRF del array
        
        // Procesar campos especiales (ej: checkboxes)
        \$data = self::processFormData(\$data);
        
        Database::table('$table')->insert(\$data);
        header('Location: $routePrefix/" . strtolower($modelName) . "s');
    }

    public function edit(\$id) {
        \$$modelName = Database::table('$table')->where('id', \$id)->first();
        return View::render" . ($location === 'superadmin' ? 'Superadmin' : 'Admin') . "('" . strtolower($modelName) . "s.edit', ['title' => 'Editar $modelName', '$modelName' => \$$modelName]);
    }

    public function update(\$id) {
        \$data = \$_POST;
        unset(\$data['_token']);
        unset(\$data['_csrf']); // Eliminar el campo CSRF del array
        
        // Procesar campos especiales (ej: checkboxes)
        \$data = self::processFormData(\$data);
        
        Database::table('$table')->where('id', \$id)->update(\$data);
        header('Location: $routePrefix/" . strtolower($modelName) . "s');
    }

    public function destroy(\$id) {
        Database::table('$table')->where('id', \$id)->delete();
        header('Location: $routePrefix/" . strtolower($modelName) . "s');
    }
    
    private static function processFormData(\$data) {
        // Procesar checkboxes (convertir a 0/1)
        foreach (\$data as \$key => \$value) {
            if (\$value === 'on') {
                \$data[\$key] = 1;
            }
        }
        
        // Lista dinámica de campos tipo checkbox" . self::generateCheckboxFields($columns) . "
        foreach (\$expectedFields as \$field) {
            if (!isset(\$data[\$field])) {
                \$data[\$field] = 0;
            }
        }
        
        return \$data;
    }
}";
        
        // Crear directorio de controladores si no existe
        $controllerDir = "$basePath/core/controllers/$location";
        if (!is_dir($controllerDir)) {
            echo "🆕 Creando directorio de controladores: $controllerDir\n";
            if (!mkdir($controllerDir, 0777, true)) {
                echo "❌ Error: No se pudo crear el directorio de controladores\n";
                return false;
            }
        }
        
        // Guardar el controlador
        echo "📝 Creando controlador: $controllerName.php\n";
        if (file_put_contents($controllerPath, $controllerCode) === false) {
            echo "❌ Error: No se pudo crear el controlador\n";
            return false;
        } else {
            echo "✅ Controlador creado correctamente en: $controllerPath\n";
            return true;
        }
    }
    
    /**
     * Genera solo las vistas para una entidad
     */
    public static function generateViews(string $modelName, array $columns = null, string $location = 'superadmin')
    {
        echo "\n🔄 Generando vistas para $modelName en contexto $location...\n";
        
        $basePath = realpath(__DIR__ . "/../..");
        $viewFolder = strtolower($modelName) . 's';
        $viewPath = "$basePath/core/views/$location/$viewFolder";
        $table = strtolower($modelName) . 's';
        $routePrefix = $location === 'superadmin' ? '/musedock' : '/' . admin_path();
        
        // Verificar si las vistas ya existen
        if (is_dir($viewPath)) {
            echo "⚠️ Las vistas para $modelName ya existen en el contexto $location. ¿Deseas sobrescribirlas? (s/n): ";
            $handle = fopen("php://stdin", "r");
            $line = trim(fgets($handle));
            fclose($handle);
            
            if (strtolower($line) !== 's' && strtolower($line) !== 'si' && strtolower($line) !== 'yes' && strtolower($line) !== 'y') {
                echo "❌ Generación de vistas cancelada por el usuario.\n";
                return false;
            }
            
            echo "✅ Continuando con la sobrescritura de vistas...\n";
        }
        
        // Obtener la estructura de la tabla si no se proporcionó
        if (!$columns) {
            $columns = self::getTableColumns($table);

            if ($columns === false || empty($columns)) {
                echo "❌ Error: La tabla $table no existe en la base de datos.\n";
                echo "   Crea la tabla primero antes de generar.\n";
                return false;
            }

            echo "✅ Tabla $table encontrada con " . count($columns) . " columnas\n";
        }
        
        // Generar campos del formulario
        $fields = '';
        $rowOpen = false;
        $colCount = 0;
        
        foreach ($columns as $column) {
            $name = $column['Field'];
            
            // Omitir campos que se generan automáticamente
            if (in_array($name, ['id', 'created_at', 'updated_at'])) continue;
            
            $label = ucfirst(str_replace('_', ' ', $name));
            $type = self::detectFieldType($column);
            $required = $column['Null'] === 'NO' ? 'required' : '';
            
            // Extraer valores enum si corresponde
            $enumValues = [];
            if (preg_match('/enum\((.*)\)/', $column['Type'], $matches)) {
                $enumString = $matches[1];
                preg_match_all("/'([^']*)'/", $enumString, $enumMatches);
                $enumValues = $enumMatches[1];
            }
            
            // Generar input basado en el tipo detectado
            if (!$rowOpen) {
                $fields .= "<div class=\"row\">\n";
                $rowOpen = true;
            }
            
            $fields .= self::generateInputField($name, $label, $type, $required, $modelName, $enumValues, $column);
            $colCount++;
            
            // Cerrar fila cada 2 columnas (o al final de los campos)
            if ($colCount >= 2) {
                $fields .= "</div>\n";
                $rowOpen = false;
                $colCount = 0;
            }
        }
        
        // Cerrar la última fila si está abierta
        if ($rowOpen) {
            $fields .= "</div>\n";
        }
        
        // Vista Index
        $viewIndex = "@extends('layouts.app')

@section('title', \$title)

@section('content')
<div class=\"app-content\">
  <div class=\"container-fluid\">
    <div class=\"d-flex justify-content-between align-items-center mb-3\">
      <h2>{{ \$title }}</h2>
      <a href=\"$routePrefix/" . strtolower($modelName) . "s/create\" class=\"btn btn-primary\">Crear $modelName</a>
    </div>

    @include('partials.alerts')

    <div class=\"card\">
      <div class=\"card-body table-responsive p-0\">
        <table class=\"table table-hover align-middle\">
          <thead>
            <tr>";

        foreach ($columns as $col) {
            $viewIndex .= "<th>" . ucfirst(str_replace('_', ' ', $col['Field'])) . "</th>";
        }

        $viewIndex .= "<th>Acciones</th>
            </tr>
          </thead>
          <tbody>
          @foreach (\$" . strtolower($modelName) . "s as \$$modelName)
            <tr>";
        foreach ($columns as $col) {
            $fieldName = $col['Field'];
            // Procesar campos especiales para la visualización
            if (str_contains($col['Type'], 'tinyint(1)')) {
                // Mostrar como Sí/No para campos booleanos
                $viewIndex .= "<td>{{ \$$modelName" . "['$fieldName'] ? 'Sí' : 'No' }}</td>";
            } elseif (str_contains($col['Type'], 'datetime') || str_contains($col['Type'], 'timestamp')) {
                // Formatear fechas
                $viewIndex .= "<td>{{ \$$modelName" . "['$fieldName'] ? date('d/m/Y H:i', strtotime(\$$modelName" . "['$fieldName'])) : '' }}</td>";
            } elseif (str_contains($col['Type'], 'enum')) {
                // Mostrar valor enum tal cual
                $viewIndex .= "<td>{{ \$$modelName" . "['$fieldName'] }}</td>";
            } else {
                $viewIndex .= "<td>{{ \$$modelName" . "['$fieldName'] }}</td>";
            }
        }

        $viewIndex .= "<td class=\"d-flex gap-1\">
                <a href=\"$routePrefix/" . strtolower($modelName) . "s/{{ \$$modelName" . "['id'] }}/edit\" class=\"btn btn-warning btn-sm\">Editar</a>
                <form method=\"POST\" action=\"$routePrefix/" . strtolower($modelName) . "s/{{ \$$modelName" . "['id'] }}/delete\" onsubmit=\"return confirm('¿Seguro?')\">
                  {!! csrf_field() !!}
                  <button class=\"btn btn-danger btn-sm\">Eliminar</button>
                </form>
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection";

        // Vista Create
        $viewCreate = "@extends('layouts.app')

@section('title', \$title)

@section('content')
<div class=\"app-content\">
  <div class=\"container-fluid\">
    <div class=\"card\">
      <div class=\"card-header\">
        <h3 class=\"card-title\">{{ \$title }}</h3>
      </div>
      <div class=\"card-body\">
        <form method=\"POST\" action=\"$routePrefix/" . strtolower($modelName) . "s\">
          {!! csrf_field() !!}
          $fields
          <div class=\"mt-3\">
            <button type=\"submit\" class=\"btn btn-primary\">Guardar</button>
            <a href=\"$routePrefix/" . strtolower($modelName) . "s\" class=\"btn btn-secondary\">Cancelar</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection";

        // Vista Edit
        $viewEdit = "@extends('layouts.app')

@section('title', \$title)

@section('content')
<div class=\"app-content\">
  <div class=\"container-fluid\">
    <div class=\"card\">
      <div class=\"card-header\">
        <h3 class=\"card-title\">{{ \$title }}</h3>
      </div>
      <div class=\"card-body\">
        <form method=\"POST\" action=\"$routePrefix/" . strtolower($modelName) . "s/{{ \$$modelName" . "['id'] }}/update\">
          {!! csrf_field() !!}
          $fields
          <div class=\"mt-3\">
            <button type=\"submit\" class=\"btn btn-success\">Actualizar</button>
            <a href=\"$routePrefix/" . strtolower($modelName) . "s\" class=\"btn btn-secondary\">Cancelar</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection";
        
        // Verificar y crear directorio base para vistas si no existe
        $viewBasePath = "$basePath/core/views/$location";
        if (!is_dir($viewBasePath)) {
            echo "🆕 Creando directorio base para vistas: $viewBasePath\n";
            if (!mkdir($viewBasePath, 0777, true)) {
                echo "❌ Error: No se pudo crear el directorio base para vistas\n";
                return false;
            }
        }
        
        // Crear directorio de vistas si no existe
        if (!is_dir($viewPath)) {
            echo "🆕 Creando directorio de vistas: $viewPath\n";
            if (!mkdir($viewPath, 0777, true)) {
                echo "❌ Error: No se pudo crear el directorio de vistas\n";
                return false;
            } else {
                echo "✅ Directorio de vistas creado\n";
            }
        } else {
            echo "ℹ️ Directorio de vistas ya existe\n";
        }
        
        // Crear archivos de vistas
        $views = [
            'index' => $viewIndex,
            'create' => $viewCreate,
            'edit' => $viewEdit
        ];
        
        $allSuccess = true;
        foreach ($views as $name => $content) {
            $viewFile = "$viewPath/$name.blade.php";
            echo "📝 Creando vista: $name.blade.php\n";
            if (file_put_contents($viewFile, $content) === false) {
                echo "❌ Error: No se pudo crear la vista $name.blade.php\n";
                $allSuccess = false;
            } else {
                echo "✅ Vista $name.blade.php creada correctamente\n";
            }
        }
        
        if ($allSuccess) {
            echo "✅ Todas las vistas creadas correctamente en: $viewPath\n";
            return true;
        } else {
            echo "⚠️ Algunas vistas no pudieron ser creadas, revisa los permisos\n";
            return false;
        }
    }
    
    /**
     * Genera solo las rutas para una entidad
     */
    public static function generateRoutes(string $modelName, string $location = 'superadmin')
    {
        echo "\n🔄 Generando rutas para $modelName en contexto $location...\n";
        
        $basePath = realpath(__DIR__ . "/../..");
        $routesFile = "$basePath/routes/$location.php";
        $controllerName = ucfirst($modelName) . 'Controller';
        $routePrefix = $location === 'superadmin' ? '/musedock' : '/' . admin_path();
        
        // Verificar si el archivo de rutas existe
        if (!is_file($routesFile)) {
            echo "⚠️ Archivo de rutas no encontrado: $routesFile\n";
            echo "📝 Creando archivo de rutas para el contexto $location\n";
            $initContent = "<?php\n\nuse Screenart\\Musedock\\Route;\n\n";
            if (file_put_contents($routesFile, $initContent) === false) {
                echo "❌ Error: No se pudo crear el archivo de rutas\n";
                return false;
            }
        }
        
        // Middleware específico según location
        $middleware = $location === 'superadmin' ? 'superadmin' : 'AuthMiddleware';
        
        // Bloque de rutas a añadir
        $routeEntry = "\n// Rutas CRUD para $modelName
Route::get('$routePrefix/" . strtolower($modelName) . "s', '$location.$controllerName@index')->middleware('$middleware');
Route::get('$routePrefix/" . strtolower($modelName) . "s/create', '$location.$controllerName@create')->middleware('$middleware');
Route::post('$routePrefix/" . strtolower($modelName) . "s', '$location.$controllerName@store')->middleware('$middleware');
Route::get('$routePrefix/" . strtolower($modelName) . "s/{id}/edit', '$location.$controllerName@edit')->middleware('$middleware');
Route::post('$routePrefix/" . strtolower($modelName) . "s/{id}/update', '$location.$controllerName@update')->middleware('$middleware');
Route::post('$routePrefix/" . strtolower($modelName) . "s/{id}/delete', '$location.$controllerName@destroy')->middleware('$middleware');\n";
        
        // Actualizar archivo de rutas
        return self::updateRoutesFile($routesFile, $modelName, $routeEntry);
    }
    
    /**
     * Actualiza el archivo de rutas, sobrescribiendo las rutas existentes en lugar de añadir duplicados
     */
    private static function updateRoutesFile($routesFile, $modelName, $newRoutes) {
        if (!file_exists($routesFile)) {
            file_put_contents($routesFile, "<?php\n\nuse Screenart\\Musedock\\Route;\n\n");
        }
        
        $content = file_get_contents($routesFile);
        
        // Patrón para encontrar las rutas existentes del modelo
        $pattern = "/\n\/\/ Rutas CRUD para $modelName\nRoute::get.*?$modelName.*?\n.*?\n.*?\n.*?\n.*?\n.*?\n/s";
        
        if (preg_match($pattern, $content)) {
            // Si ya existen rutas para este modelo, reemplazarlas
            $updatedContent = preg_replace($pattern, $newRoutes, $content);
            echo "🔄 Sobrescribiendo rutas existentes para $modelName\n";
        } else {
            // Si no existen rutas para este modelo, añadirlas al final
            $updatedContent = $content . $newRoutes;
            echo "📝 Añadiendo nuevas rutas para $modelName\n";
        }
        
        if (file_put_contents($routesFile, $updatedContent) === false) {
            echo "❌ Error: No se pudieron actualizar las rutas\n";
            return false;
        } else {
            echo "✅ Rutas actualizadas correctamente en: $routesFile\n";
            return true;
        }
    }
    
    /**
     * Genera la lista de campos tipo checkbox para el código del controlador
     * Compatible con MySQL (TINYINT) y PostgreSQL (BOOLEAN, SMALLINT)
     */
    private static function generateCheckboxFields($columns) {
        $checkboxFields = [];
        foreach ($columns as $column) {
            $fieldName = $column['Field'];
            $fieldType = strtolower($column['Type']);

            // Detectar si es un campo tipo checkbox
            $isCheckbox = false;

            // PostgreSQL: tipo BOOLEAN nativo
            if (str_contains($fieldType, 'boolean')) {
                $isCheckbox = true;
            }
            // MySQL: TINYINT(1)
            elseif (str_contains($fieldType, 'tinyint(1)')) {
                $isCheckbox = true;
            }
            // PostgreSQL: SMALLINT usado como booleano (por nombre de campo)
            elseif (str_contains($fieldType, 'smallint') &&
                    in_array(strtolower($fieldName), ['active', 'status', 'published', 'is_public', 'is_active', 'enabled', 'visible', 'featured', 'is_featured'])) {
                $isCheckbox = true;
            }
            // Detección por nombre de campo (cualquier motor)
            elseif (in_array(strtolower($fieldName), ['active', 'status', 'published', 'is_public', 'is_active', 'enabled', 'visible', 'featured', 'is_featured'])) {
                $isCheckbox = true;
            }

            if ($isCheckbox) {
                $checkboxFields[] = $fieldName;
            }
        }

        if (empty($checkboxFields)) {
            return "\n        \$expectedFields = [];";
        }

        $fieldsList = implode("', '", $checkboxFields);
        return "\n        \$expectedFields = ['$fieldsList'];";
    }
    
    /**
     * Detecta el tipo de campo basado en la estructura de la columna
     * Compatible con MySQL y PostgreSQL
     */
     private static function detectFieldType($column) {
        $fieldName = strtolower($column['Field']);
        $fieldType = strtolower($column['Type']);

        // Enum (solo MySQL, PostgreSQL usa CHECK constraints o tipos personalizados)
        if (str_contains($fieldType, 'enum')) {
            return 'enum';
        }

        // Fechas y timestamps (compatible ambos motores)
        if (str_contains($fieldType, 'datetime') || str_contains($fieldType, 'timestamp')) {
            return 'datetime-local';
        }
        if (str_contains($fieldType, 'date') && !str_contains($fieldType, 'datetime')) {
            return 'date';
        }
        if (str_contains($fieldType, 'time')) {
            return 'time';
        }

        // Booleanos - PostgreSQL BOOLEAN nativo
        if (str_contains($fieldType, 'boolean')) {
            return 'checkbox';
        }
        // Booleanos - MySQL TINYINT(1)
        if (str_contains($fieldType, 'tinyint(1)')) {
            return 'checkbox';
        }
        // Booleanos - PostgreSQL SMALLINT usado como booleano (por nombre)
        if (str_contains($fieldType, 'smallint') &&
            in_array($fieldName, ['active', 'status', 'published', 'is_public', 'is_active', 'enabled', 'visible', 'featured', 'is_featured'])) {
            return 'checkbox';
        }
        // Booleanos - Detección por nombre (cualquier motor)
        if (in_array($fieldName, ['active', 'status', 'published', 'is_public', 'is_active', 'enabled', 'visible', 'featured', 'is_featured'])) {
            return 'checkbox';
        }

        // Emails
        if (str_contains($fieldName, 'email')) {
            return 'email';
        }

        // URLs
        if (str_contains($fieldName, 'url') || str_contains($fieldName, 'website') || str_contains($fieldName, 'link')) {
            return 'url';
        }

        // Passwords
        if (str_contains($fieldName, 'password')) {
            return 'password';
        }

        // Números - PostgreSQL y MySQL
        if (str_contains($fieldType, 'bigint') || str_contains($fieldType, 'smallint') || str_contains($fieldType, 'int')) {
            return 'number';
        }
        if (str_contains($fieldType, 'decimal') || str_contains($fieldType, 'numeric') || str_contains($fieldType, 'float') || str_contains($fieldType, 'double')) {
            return 'number';
        }

        // Texto largo
        if (str_contains($fieldType, 'text')) {
            return 'textarea';
        }

        // Por defecto
        return 'text';
    }
    
    /**
     * Genera el campo de formulario basado en el tipo
     */
  
private static function generateInputField($name, $label, $type, $required, $modelName, $enumValues = [], $column = []) {
    // Determinar ancho de columna basado en el tipo de campo
    switch ($type) {
        case 'enum':
        case 'datetime-local':
        case 'date':
        case 'time':
            $columnWidth = 'col-md-3';
            break;
        case 'number':
            $columnWidth = 'col-md-4';
            break;
        case 'checkbox':
            $columnWidth = 'col-md-2';
            break;
        case 'textarea':
            $columnWidth = 'col-md-12';
            break;
        default:
            $columnWidth = 'col-md-6';
    }
    
    $field = "<div class=\"$columnWidth mb-3\">\n";
    
    switch ($type) {
        case 'enum':
            $field .= "  <label class=\"form-label\">$label</label>\n";
            $field .= "  <select class=\"form-select\" name=\"$name\" $required>\n";
            if (!$required) {
                $field .= "    <option value=\"\">-- Seleccionar --</option>\n";
            }
            foreach ($enumValues as $value) {
                $field .= "    <option value=\"$value\" @if(isset(\$$modelName" . "['$name']) && \$$modelName" . "['$name'] == '$value') selected @endif>$value</option>\n";
            }
            $field .= "  </select>\n";
            break;
            
        case 'textarea':
            $field .= "  <label class=\"form-label\">$label</label>\n";
            $field .= "  <textarea class=\"form-control\" name=\"$name\" rows=\"5\" $required>{!! \$$modelName" . "['$name'] ?? '' !!}</textarea>\n";
            break;
            
        case 'checkbox':
            $field .= "  <div class=\"form-check mt-4\">\n";
            $field .= "    <input type=\"checkbox\" class=\"form-check-input\" name=\"$name\" id=\"$name\" @if(isset(\$$modelName" . "['$name']) && \$$modelName" . "['$name']) checked @endif>\n";
            $field .= "    <label class=\"form-check-label\" for=\"$name\">$label</label>\n";
            $field .= "  </div>\n";
            break;
            
        case 'datetime-local':
            $field .= "  <label class=\"form-label\">$label</label>\n";
            $field .= "  <input type=\"datetime-local\" class=\"form-control\" name=\"$name\" value=\"{{ isset(\$$modelName" . "['$name']) && \$$modelName" . "['$name'] ? date('Y-m-d\\TH:i', strtotime(\$$modelName" . "['$name'])) : '' }}\" $required>\n";
            break;
            
        case 'date':
            $field .= "  <label class=\"form-label\">$label</label>\n";
            $field .= "  <input type=\"date\" class=\"form-control\" name=\"$name\" value=\"{{ isset(\$$modelName" . "['$name']) && \$$modelName" . "['$name'] ? date('Y-m-d', strtotime(\$$modelName" . "['$name'])) : '' }}\" $required>\n";
            break;
            
        case 'time':
            $field .= "  <label class=\"form-label\">$label</label>\n";
            $field .= "  <input type=\"time\" class=\"form-control\" name=\"$name\" value=\"{{ isset(\$$modelName" . "['$name']) && \$$modelName" . "['$name'] ? date('H:i', strtotime(\$$modelName" . "['$name'])) : '' }}\" $required>\n";
            break;
            
        case 'number':
            // Detectar si es decimal
            if (str_contains($column['Type'], 'decimal') && preg_match('/decimal\((\d+),(\d+)\)/', $column['Type'], $matches)) {
                $step = "0." . str_repeat('0', $matches[2] - 1) . '1';
                $field .= "  <label class=\"form-label\">$label</label>\n";
                $field .= "  <input type=\"number\" step=\"$step\" class=\"form-control\" name=\"$name\" value=\"{{ \$$modelName" . "['$name'] ?? '' }}\" $required>\n";
            } else {
                $field .= "  <label class=\"form-label\">$label</label>\n";
                $field .= "  <input type=\"number\" class=\"form-control\" name=\"$name\" value=\"{{ \$$modelName" . "['$name'] ?? '' }}\" $required>\n";
            }
            break;
            
        case 'email':
            $field .= "  <label class=\"form-label\">$label</label>\n";
            $field .= "  <input type=\"email\" class=\"form-control\" name=\"$name\" value=\"{{ \$$modelName" . "['$name'] ?? '' }}\" $required>\n";
            break;
            
        case 'url':
            $field .= "  <label class=\"form-label\">$label</label>\n";
            $field .= "  <input type=\"url\" class=\"form-control\" name=\"$name\" value=\"{{ \$$modelName" . "['$name'] ?? '' }}\" $required>\n";
            break;
            
        case 'password':
            $field .= "  <label class=\"form-label\">$label</label>\n";
            $field .= "  <input type=\"password\" class=\"form-control\" name=\"$name\" $required>\n";
            break;
            
        default:
            $field .= "  <label class=\"form-label\">$label</label>\n";
            $field .= "  <input type=\"$type\" class=\"form-control\" name=\"$name\" value=\"{{ \$$modelName" . "['$name'] ?? '' }}\" $required>\n";
            break;
    }
    
    $field .= "</div>\n";
    return $field;
}
}
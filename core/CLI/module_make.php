<?php
/**
 * MuseDock Module Generator
 * 
 * Uso: php core/CLI/module_make.php ModuleName [--admin] [--front] [--tenant]
 * 
 * Opciones:
 *   --admin  : Incluir componentes para el panel de superadmin
 *   --front  : Incluir componentes para el frontend
 *   --tenant : Incluir componentes para panel de tenant
 * 
 * Ejemplo: 
 *   php commands/module_make.php Blog --admin --front --tenant
 */

// Verificar argumentos
if ($argc < 2) {
    echo "Error: Debes especificar un nombre para el módulo.\n";
    echo "Uso: php commands/module_make.php ModuleName [--admin] [--front] [--tenant]\n";
    exit(1);
}

// Obtener el nombre del módulo y las opciones
$moduleName = $argv[1];
$moduleLower = strtolower($moduleName);
$moduleUpper = ucfirst($moduleName);
$moduleNamespace = $moduleUpper;

// Configurar opciones
$includeAdmin = in_array('--admin', $argv);
$includeFront = in_array('--front', $argv);
$includeTenant = in_array('--tenant', $argv);

// Si no se proporciona ninguna opción, incluir todas
if (!$includeAdmin && !$includeFront && !$includeTenant) {
    $includeAdmin = $includeFront = $includeTenant = true;
}

// Definir rutas base
// $baseDir = __DIR__ . '/../';
$baseDir = dirname(__DIR__, 2) . '/';
$moduleDir = $baseDir . 'modules/' . $moduleLower;

// Crear estructura de directorios del módulo
echo "Creando estructura de directorios para el módulo '$moduleUpper'...\n";

// Directorios principales
$dirs = [
    $moduleDir,
    $moduleDir . '/controllers',
    $moduleDir . '/models',
    $moduleDir . '/views',
];

// Agregar directorios según las opciones
if ($includeFront) {
    $dirs[] = $moduleDir . '/views/front';
}

if ($includeAdmin || $includeTenant) {
    $dirs[] = $moduleDir . '/views/admin';
    $dirs[] = $moduleDir . '/views/admin/index';
    $dirs[] = $moduleDir . '/views/admin/create';
    $dirs[] = $moduleDir . '/views/admin/edit';
}

// Crear directorios
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "  Creado: $dir\n";
    }
}

// Función para crear archivos con plantillas
function createFile($path, $content) {
    if (!file_exists($path)) {
        file_put_contents($path, $content);
        echo "  Creado: $path\n";
    } else {
        echo "  Ya existe: $path (no modificado)\n";
    }
}

// 1. Crear module.json
$moduleJsonContent = <<<JSON
{
  "name": "$moduleUpper",
  "description": "Módulo de $moduleUpper para MuseDock",
  "version": "1.0.0",
  "author": "MuseDock",
  "active": true,
  "public": true,
  "autoload": {
    "psr4": {
      "$moduleNamespace\\\\": "controllers/"
    }
  }
}
JSON;

createFile($moduleDir . '/module.json', $moduleJsonContent);

// 2. Crear bootstrap.php
$bootstrapContent = <<<PHP
<?php
// Evitar cargas múltiples
if (defined('BOOTSTRAP_$moduleLower')) return;
define('BOOTSTRAP_$moduleLower', true);

use Screenart\Musedock\Services\SlugService;
use Screenart\Musedock\Services\TenantManager;
use Screenart\Musedock\Database;

/**
 * Registrar slugs para el front
 */
\$tenantId = tenant_id();

// Registrar principales slugs del módulo
if (\$tenantId !== null) {
    // Para tenant activo
    SlugService::registerOnce(
        module: '$moduleLower',
        referenceId: 1,
        slug: '$moduleLower',
        tenantId: \$tenantId,
        prefix: null
    );
} else {
    // Para CMS principal
    SlugService::registerOnce(
        module: '$moduleLower',
        referenceId: 1,
        slug: '$moduleLower',
        tenantId: null,
        prefix: null
    );
}

// Registrar items en SlugService
try {
    // Condición para tenant activo o CMS principal
    \$tenantCondition = \$tenantId !== null ? 
        "AND (tenant_id = {\$tenantId} OR tenant_id IS NULL)" : 
        "AND tenant_id IS NULL";
    
    // Registrar items desde la BD si es necesario
    // ...
} catch (\Exception \$e) {
    // Probablemente las tablas aún no existen
}

/**
 * Registrar menú de admin
 */

// Añadir al menú del superadmin
if (isset(\$_SESSION['super_admin'])) {
    // Para panel de superadmin
    \$GLOBALS['ADMIN_MENU'] = \$GLOBALS['ADMIN_MENU'] ?? [];
    
    \$GLOBALS['ADMIN_MENU'][] = [
        'title' => '$moduleUpper',
        'icon' => 'box',
        'children' => [
            [
                'title' => 'Gestionar $moduleUpper',
                'url' => '/musedock/$moduleLower'
            ]
        ]
    ];
}

// Añadir al menú de admin de tenant
if (isset(\$_SESSION['admin'])) {
    // Para panel de admin de tenant
    \$GLOBALS['ADMIN_MENU'] = \$GLOBALS['ADMIN_MENU'] ?? [];
    \$adminPath = admin_url();
    
    \$GLOBALS['ADMIN_MENU'][] = [
        'title' => '$moduleUpper',
        'icon' => 'box',
        'children' => [
            [
                'title' => 'Gestionar $moduleUpper',
                'url' => "\$adminPath/$moduleLower"
            ]
        ]
    ];
}
PHP;

createFile($moduleDir . '/bootstrap.php', $bootstrapContent);

// 3. Crear routes.php
$routesContent = <<<PHP
<?php
use Screenart\Musedock\Route;

// Cargar controladores
require_once __DIR__ . '/controllers/MainController.php';

// Front routes
Route::get('/$moduleLower', '$moduleNamespace\\\\MainController@index');
Route::get('/$moduleLower/{slug}', '$moduleNamespace\\\\MainController@show');

// ---------- Rutas del panel del superadmin ----------
Route::get('/musedock/$moduleLower', '$moduleNamespace\\\\MainController@adminIndex')
    ->middleware('superadmin');
Route::get('/musedock/$moduleLower/create', '$moduleNamespace\\\\MainController@adminCreate')
    ->middleware('superadmin');
Route::post('/musedock/$moduleLower/store', '$moduleNamespace\\\\MainController@adminStore')
    ->middleware('superadmin');
Route::get('/musedock/$moduleLower/{id}/edit', '$moduleNamespace\\\\MainController@adminEdit')
    ->middleware('superadmin');
Route::post('/musedock/$moduleLower/{id}/update', '$moduleNamespace\\\\MainController@adminUpdate')
    ->middleware('superadmin');
Route::post('/musedock/$moduleLower/{id}/delete', '$moduleNamespace\\\\MainController@adminDelete')
    ->middleware('superadmin');

// ---------- Rutas para los paneles de admin de tenants ----------
// Intentar obtener la ruta admin del tenant
\$tenantAdminPath = isset(\$GLOBALS['tenant']['admin_path']) ? 
    '/' . trim(\$GLOBALS['tenant']['admin_path'], '/') : 
    '/admin';

// Rutas tenant admin
Route::get("\$tenantAdminPath/$moduleLower", '$moduleNamespace\\\\MainController@adminIndex')
    ->middleware(['auth', 'permission:$moduleLower.view']);
Route::get("\$tenantAdminPath/$moduleLower/create", '$moduleNamespace\\\\MainController@adminCreate')
    ->middleware(['auth', 'permission:$moduleLower.create']);
Route::post("\$tenantAdminPath/$moduleLower/store", '$moduleNamespace\\\\MainController@adminStore')
    ->middleware(['auth', 'permission:$moduleLower.create']);
Route::get("\$tenantAdminPath/$moduleLower/{id}/edit", '$moduleNamespace\\\\MainController@adminEdit')
    ->middleware(['auth', 'permission:$moduleLower.edit']);
Route::post("\$tenantAdminPath/$moduleLower/{id}/update", '$moduleNamespace\\\\MainController@adminUpdate')
    ->middleware(['auth', 'permission:$moduleLower.edit']);
Route::post("\$tenantAdminPath/$moduleLower/{id}/delete", '$moduleNamespace\\\\MainController@adminDelete')
    ->middleware(['auth', 'permission:$moduleLower.delete']);
PHP;

createFile($moduleDir . '/routes.php', $routesContent);

// 4. Crear install.php
$installContent = <<<PHP
<?php
use Screenart\Musedock\Database;

/**
 * Script de instalación del módulo $moduleUpper
 */

// Crear tablas necesarias
Database::execute("
CREATE TABLE IF NOT EXISTS {$moduleLower}_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    description TEXT,
    content TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    tenant_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY (slug, tenant_id)
)
");

// Registrar permisos para el módulo
\$perms = [
    ['name' => '$moduleLower.view', 'description' => 'Ver elementos de $moduleUpper'],
    ['name' => '$moduleLower.create', 'description' => 'Crear elementos de $moduleUpper'],
    ['name' => '$moduleLower.edit', 'description' => 'Editar elementos de $moduleUpper'],
    ['name' => '$moduleLower.delete', 'description' => 'Eliminar elementos de $moduleUpper'],
];

foreach (\$perms as \$perm) {
    \$existing = Database::query("SELECT COUNT(*) FROM permissions WHERE name = :name", [
        'name' => \$perm['name']
    ])->fetchColumn();
    
    if (!\$existing) {
        Database::table('permissions')->insert([
            'name' => \$perm['name'],
            'description' => \$perm['description']
        ]);
    }
}

echo "Módulo $moduleUpper instalado correctamente";
PHP;

createFile($moduleDir . '/install.php', $installContent);

// 5. Crear MainController.php
$mainControllerContent = <<<PHP
<?php
namespace $moduleNamespace;

use Screenart\Musedock\Controller;
use Screenart\Musedock\View;
use Screenart\Musedock\Database;

class MainController extends Controller
{
    private \$isSuperAdmin = false;
    
    public function __construct()
    {
        // Comprobar si es superadmin o admin de tenant
        \$this->isSuperAdmin = isset(\$_SESSION['super_admin']);
    }
    
    // Método para determinar qué tipo de vista renderizar
    private function renderView(\$viewPath, \$data = [])
    {
        if (\$this->isSuperAdmin) {
            // Renderizar vista de superadmin
            return View::render('superadmin.$moduleLower.' . \$viewPath, \$data);
        } else {
            // Renderizar vista de tenant admin
            return View::renderAdmin('$moduleLower::admin.' . \$viewPath, \$data);
        }
    }
    
    // ---------- Métodos para el frontend ----------
    
    public function index()
    {
        \$tenantId = tenant_id();
        \$tenantCondition = \$tenantId !== null ? 
            "AND (tenant_id = {\$tenantId} OR tenant_id IS NULL)" : 
            "AND tenant_id IS NULL";
        
        \$items = Database::query("
            SELECT * FROM {$moduleLower}_items
            WHERE status = 'active' {\$tenantCondition}
            ORDER BY created_at DESC
        ")->fetchAll();
        
        return View::renderTheme('$moduleLower/index', [
            'items' => \$items,
            'title' => '$moduleUpper'
        ]);
    }
    
    public function show(\$slug)
    {
        \$tenantId = tenant_id();
        \$tenantCondition = \$tenantId !== null ? 
            "AND (tenant_id = {\$tenantId} OR tenant_id IS NULL)" : 
            "AND tenant_id IS NULL";
        
        \$item = Database::query("
            SELECT * FROM {$moduleLower}_items
            WHERE slug = :slug AND status = 'active' {\$tenantCondition}
        ", ['slug' => \$slug])->fetch();
        
        if (!\$item) {
            http_response_code(404);
            return View::renderTheme('404', ['message' => 'Elemento no encontrado']);
        }
        
        return View::renderTheme('$moduleLower/show', [
            'item' => \$item,
            'title' => \$item['title']
        ]);
    }
    
    // ---------- Métodos para el panel admin ----------
    
    public function adminIndex()
    {
        \$tenantId = tenant_id();
        \$tenantCondition = '';
        
        if (\$this->isSuperAdmin) {
            // Superadmin ve todos los elementos o puede filtrar por tenant
            \$selectedTenant = \$_GET['tenant_id'] ?? null;
            if (\$selectedTenant) {
                \$tenantCondition = "WHERE tenant_id = " . (int)\$selectedTenant;
            }
        } else {
            // Admin de tenant solo ve sus elementos
            \$tenantCondition = "WHERE tenant_id = " . (int)\$tenantId . " OR tenant_id IS NULL";
        }
        
        \$items = Database::query("
            SELECT * FROM {$moduleLower}_items
            {\$tenantCondition}
            ORDER BY created_at DESC
        ")->fetchAll();
        
        // Lista de tenants para el superadmin
        \$tenants = [];
        if (\$this->isSuperAdmin) {
            \$tenants = Database::query("SELECT id, name FROM tenants")->fetchAll();
        }
        
        return \$this->renderView('index', [
            'items' => \$items,
            'tenants' => \$tenants,
            'title' => 'Gestionar $moduleUpper'
        ]);
    }
    
    public function adminCreate()
    {
        // Lista de tenants para el superadmin
        \$tenants = [];
        if (\$this->isSuperAdmin) {
            \$tenants = Database::query("SELECT id, name FROM tenants")->fetchAll();
        }
        
        return \$this->renderView('create', [
            'tenants' => \$tenants,
            'title' => 'Nuevo $moduleUpper'
        ]);
    }
    
    public function adminStore()
    {
        // Validación de campos
        if (!validate([
            'title' => 'required|min:3',
            'content' => 'required'
        ])) {
            return back();
        }
        
        // Generar slug único
        \$slug = \$this->generateSlug(\$_POST['title']);
        
        // Determinar tenant_id según el contexto
        \$tenantId = null;
        if (!\$this->isSuperAdmin) {
            \$tenantId = tenant_id();
        } elseif (isset(\$_POST['tenant_id']) && !\empty(\$_POST['tenant_id'])) {
            \$tenantId = (int)\$_POST['tenant_id'];
        }
        
        // Insertar en base de datos
        Database::table('{$moduleLower}_items')->insert([
            'title' => \$_POST['title'],
            'slug' => \$slug,
            'description' => \$_POST['description'] ?? null,
            'content' => \$_POST['content'],
            'status' => \$_POST['status'] ?? 'active',
            'tenant_id' => \$tenantId,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        flash('success', '$moduleUpper creado correctamente');
        
        return redirect(\$this->isSuperAdmin ? '/musedock/$moduleLower' : admin_url() . '/$moduleLower');
    }
    
    public function adminEdit(\$id)
    {
        \$tenantId = tenant_id();
        \$tenantCondition = '';
        
        if (!\$this->isSuperAdmin) {
            // Admin de tenant solo puede editar sus elementos
            \$tenantCondition = "AND (tenant_id = " . (int)\$tenantId . " OR tenant_id IS NULL)";
        }
        
        \$item = Database::query("
            SELECT * FROM {$moduleLower}_items
            WHERE id = :id {\$tenantCondition}
        ", ['id' => \$id])->fetch();
        
        if (!\$item) {
            flash('error', 'Elemento no encontrado o no tienes permiso para editarlo');
            return redirect(\$this->isSuperAdmin ? '/musedock/$moduleLower' : admin_url() . '/$moduleLower');
        }
        
        // Lista de tenants para el superadmin
        \$tenants = [];
        if (\$this->isSuperAdmin) {
            \$tenants = Database::query("SELECT id, name FROM tenants")->fetchAll();
        }
        
        return \$this->renderView('edit', [
            'item' => \$item,
            'tenants' => \$tenants,
            'title' => 'Editar $moduleUpper'
        ]);
    }
    
    public function adminUpdate(\$id)
    {
        // Validación de campos
        if (!validate([
            'title' => 'required|min:3',
            'content' => 'required'
        ])) {
            return back();
        }
        
        \$tenantId = tenant_id();
        \$tenantCondition = '';
        
        if (!\$this->isSuperAdmin) {
            // Admin de tenant solo puede actualizar sus elementos
            \$tenantCondition = "AND (tenant_id = " . (int)\$tenantId . " OR tenant_id IS NULL)";
        }
        
        // Verificar que el elemento existe y pertenece al tenant
        \$item = Database::query("
            SELECT * FROM {$moduleLower}_items
            WHERE id = :id {\$tenantCondition}
        ", ['id' => \$id])->fetch();
        
        if (!\$item) {
            flash('error', 'Elemento no encontrado o no tienes permiso para editarlo');
            return redirect(\$this->isSuperAdmin ? '/musedock/$moduleLower' : admin_url() . '/$moduleLower');
        }
        
        // Si el título ha cambiado, generar nuevo slug
        \$slug = \$item['slug'];
        if (\$_POST['title'] !== \$item['title']) {
            \$slug = \$this->generateSlug(\$_POST['title']);
        }
        
        // Preparar datos para actualizar
        \$updateData = [
            'title' => \$_POST['title'],
            'slug' => \$slug,
            'description' => \$_POST['description'] ?? null,
            'content' => \$_POST['content'],
            'status' => \$_POST['status'] ?? 'active',
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Superadmin puede cambiar el tenant
        if (\$this->isSuperAdmin && isset(\$_POST['tenant_id'])) {
            \$updateData['tenant_id'] = empty(\$_POST['tenant_id']) ? null : (int)\$_POST['tenant_id'];
        }
        
        // Actualizar en base de datos
        Database::table('{$moduleLower}_items')
            ->where('id', \$id)
            ->update(\$updateData);
        
        flash('success', '$moduleUpper actualizado correctamente');
        
        return redirect(\$this->isSuperAdmin ? '/musedock/$moduleLower' : admin_url() . '/$moduleLower');
    }
    
    public function adminDelete(\$id)
    {
        \$tenantId = tenant_id();
        \$tenantCondition = '';
        
        if (!\$this->isSuperAdmin) {
            // Admin de tenant solo puede eliminar sus elementos
            \$tenantCondition = "AND (tenant_id = " . (int)\$tenantId . " OR tenant_id IS NULL)";
        }
        
        // Verificar que el elemento existe y pertenece al tenant
        \$item = Database::query("
            SELECT * FROM {$moduleLower}_items
            WHERE id = :id {\$tenantCondition}
        ", ['id' => \$id])->fetch();
        
        if (!\$item) {
            flash('error', 'Elemento no encontrado o no tienes permiso para eliminarlo');
            return redirect(\$this->isSuperAdmin ? '/musedock/$moduleLower' : admin_url() . '/$moduleLower');
        }
        
        // Eliminar de base de datos
        Database::table('{$moduleLower}_items')
            ->where('id', \$id)
            ->delete();
        
        flash('success', '$moduleUpper eliminado correctamente');
        
        return redirect(\$this->isSuperAdmin ? '/musedock/$moduleLower' : admin_url() . '/$moduleLower');
    }
    
    // Método auxiliar para generar slugs únicos
    private function generateSlug(\$text)
    {
        // Convertir a minúsculas y quitar acentos
        \$text = strtolower(\$text);
        \$text = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ', ' '], 
            ['a', 'e', 'i', 'o', 'u', 'u', 'n', '-'], 
            \$text
        );
        
        // Eliminar caracteres no alfanuméricos
        \$text = preg_replace('/[^a-z0-9\-]/', '', \$text);
        \$text = preg_replace('/-+/', '-', \$text);
        \$text = trim(\$text, '-');
        
        // Comprobar si el slug ya existe
        \$slug = \$text;
        \$count = 0;
        
        \$tenantId = tenant_id();
        \$tenantCondition = '';
        
        if (!\$this->isSuperAdmin) {
            \$tenantCondition = "AND tenant_id = " . (int)\$tenantId;
        }
        
        while (Database::query("
            SELECT COUNT(*) FROM {$moduleLower}_items
            WHERE slug = :slug {\$tenantCondition}
        ", ['slug' => \$slug])->fetchColumn()) {
            \$count++;
            \$slug = \$text . '-' . \$count;
        }
        
        return \$slug;
    }
}
PHP;

createFile($moduleDir . '/controllers/MainController.php', $mainControllerContent);

// 6. Crear vistas para superadmin
if ($includeAdmin) {
    // Crear directorio en core/views/superadmin
    $superadminDir = $baseDir . 'core/views/superadmin/' . $moduleLower;
    if (!is_dir($superadminDir)) {
        mkdir($superadminDir, 0755, true);
        echo "  Creado: $superadminDir\n";
    }
    
    // Crear vista index
    $indexViewContent = <<<PHP
@extends('layouts.app')
@section('title', \$title)
@section('content')
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h3 class="card-title">Gestión de $moduleUpper</h3>
    <a href="/musedock/$moduleLower/create" class="btn btn-primary btn-sm">
      <i class="fas fa-plus"></i> Nuevo $moduleUpper
    </a>
  </div>
  <div class="card-body">
    @if (session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
      <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if(count(\$tenants) > 0)
    <div class="mb-4">
      <form method="GET" class="form-inline">
        <div class="form-group mr-2">
          <label for="tenant_id" class="mr-2">Filtrar por tenant:</label>
          <select name="tenant_id" id="tenant_id" class="form-control form-control-sm" onchange="this.form.submit()">
            <option value="">Todos los tenants</option>
            @foreach(\$tenants as \$tenant)
              <option value="{{ \$tenant['id'] }}" {{ isset(\$_GET['tenant_id']) && \$_GET['tenant_id'] == \$tenant['id'] ? 'selected' : '' }}>
                {{ \$tenant['name'] }}
              </option>
            @endforeach
          </select>
        </div>
      </form>
    </div>
    @endif

    <div class="table-responsive">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>ID</th>
            <th>Título</th>
            <th>Estado</th>
            <th>Tenant</th>
            <th>Fecha</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          @foreach(\$items as \$item)
          <tr>
            <td>{{ \$item['id'] }}</td>
            <td>{{ \$item['title'] }}</td>
            <td>
              @if(\$item['status'] == 'active')
                <span class="badge bg-success">Activo</span>
              @else
                <span class="badge bg-secondary">Inactivo</span>
              @endif
            </td>
            <td>
              @if(\$item['tenant_id'])
                @php
                  \$tenantName = '';
                  foreach(\$tenants as \$tenant) {
                    if(\$tenant['id'] == \$item['tenant_id']) {
                      \$tenantName = \$tenant['name'];
                      break;
                    }
                  }
                @endphp
                {{ \$tenantName }}
              @else
                <span class="badge bg-info">Global</span>
              @endif
            </td>
            <td>{{ \$item['created_at'] }}</td>
            <td>
              <div class="btn-group">
                <a href="/musedock/$moduleLower/{{ \$item['id'] }}/edit" class="btn btn-sm btn-primary">
                  <i class="fas fa-edit"></i>
                </a>
                <form method="POST" action="/musedock/$moduleLower/{{ \$item['id'] }}/delete" class="d-inline" onsubmit="return confirm('¿Seguro que deseas eliminar este elemento?')">
                  {!! csrf_field() !!}
                  <button type="submit" class="btn btn-sm btn-danger">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
PHP;

    createFile($superadminDir . '/index.blade.php', $indexViewContent);
    
    // Crear vista create
    $createViewContent = <<<PHP
@extends('layouts.app')
@section('title', \$title)
@section('content')
<div class="card">
  <div class="card-header">
    <h3 class="card-title">Nuevo $moduleUpper</h3>
  </div>
  <div class="card-body">
    @if (session('error'))
      <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <form method="POST" action="/musedock/$moduleLower/store">
      {!! csrf_field() !!}
      
      <div class="form-group mb-3">
        <label for="title">Título</label>
        <input type="text" class="form-control" id="title" name="title" value="{{ old('title') }}" required>
        {!! form_error('title') !!}
      </div>
      
      <div class="form-group mb-3">
        <label for="description">Descripción</label>
        <textarea class="form-control" id="description" name="description" rows="3">{{ old('description') }}</textarea>
      </div>
      
      <div class="form-group mb-3">
        <label for="tenant_id">Tenant</label>
        <select class="form-control" id="tenant_id" name="tenant_id">
          <option value="">Global (todos los tenants)</option>
          @foreach(\$tenants as \$tenant)
            <option value="{{ \$tenant['id'] }}" {{ old('tenant_id') == \$tenant['id'] ? 'selected' : '' }}>
              {{ \$tenant['name'] }}
            </option>
          @endforeach
        </select>
        <small class="form-text text-muted">Si no seleccionas ningún tenant, el elemento será visible para todos.</small>
      </div>
      
      <div class="form-group mb-3">
        <label for="content">Contenido</label>
        <textarea class="form-control" id="content" name="content" rows="10" required>{{ old('content') }}</textarea>
        {!! form_error('content') !!}
      </div>
      
      <div class="form-group mb-3">
        <label for="status">Estado</label>
        <select class="form-control" id="status" name="status">
          <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Activo</option>
		  <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactivo</option>
        </select>
      </div>
      
      <div class="form-group">
        <button type="submit" class="btn btn-primary">Guardar</button>
        <a href="/musedock/$moduleLower" class="btn btn-secondary">Cancelar</a>
      </div>
    </form>
  </div>
</div>

<script>
  // Si tienes un editor WYSIWYG disponible, puedes inicializarlo aquí
  document.addEventListener('DOMContentLoaded', function() {
    if (typeof CKEDITOR !== 'undefined') {
      CKEDITOR.replace('content');
    }
  });
</script>
@endsection
PHP;

    createFile($superadminDir . '/create.blade.php', $createViewContent);
    
    // Crear vista edit
    $editViewContent = <<<PHP
@extends('layouts.app')
@section('title', \$title)
@section('content')
<div class="card">
  <div class="card-header">
    <h3 class="card-title">Editar $moduleUpper</h3>
  </div>
  <div class="card-body">
    @if (session('error'))
      <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <form method="POST" action="/musedock/$moduleLower/{{ \$item['id'] }}/update">
      {!! csrf_field() !!}
      
      <div class="form-group mb-3">
        <label for="title">Título</label>
        <input type="text" class="form-control" id="title" name="title" value="{{ old('title', \$item['title']) }}" required>
        {!! form_error('title') !!}
      </div>
      
      <div class="form-group mb-3">
        <label for="description">Descripción</label>
        <textarea class="form-control" id="description" name="description" rows="3">{{ old('description', \$item['description']) }}</textarea>
      </div>
      
      <div class="form-group mb-3">
        <label for="tenant_id">Tenant</label>
        <select class="form-control" id="tenant_id" name="tenant_id">
          <option value="">Global (todos los tenants)</option>
          @foreach(\$tenants as \$tenant)
            <option value="{{ \$tenant['id'] }}" {{ old('tenant_id', \$item['tenant_id']) == \$tenant['id'] ? 'selected' : '' }}>
              {{ \$tenant['name'] }}
            </option>
          @endforeach
        </select>
        <small class="form-text text-muted">Si no seleccionas ningún tenant, el elemento será visible para todos.</small>
      </div>
      
      <div class="form-group mb-3">
        <label for="content">Contenido</label>
        <textarea class="form-control" id="content" name="content" rows="10" required>{{ old('content', \$item['content']) }}</textarea>
        {!! form_error('content') !!}
      </div>
      
      <div class="form-group mb-3">
        <label for="status">Estado</label>
        <select class="form-control" id="status" name="status">
          <option value="active" {{ old('status', \$item['status']) == 'active' ? 'selected' : '' }}>Activo</option>
          <option value="inactive" {{ old('status', \$item['status']) == 'inactive' ? 'selected' : '' }}>Inactivo</option>
        </select>
      </div>
      
      <div class="form-group">
        <button type="submit" class="btn btn-primary">Actualizar</button>
        <a href="/musedock/$moduleLower" class="btn btn-secondary">Cancelar</a>
      </div>
    </form>
  </div>
</div>

<script>
  // Si tienes un editor WYSIWYG disponible, puedes inicializarlo aquí
  document.addEventListener('DOMContentLoaded', function() {
    if (typeof CKEDITOR !== 'undefined') {
      CKEDITOR.replace('content');
    }
  });
</script>
@endsection
PHP;

    createFile($superadminDir . '/edit.blade.php', $editViewContent);
}

// 7. Crear vistas para tenant admin
if ($includeTenant) {
    // Crear vistas similares a las de superadmin pero adaptadas para el panel de tenant
    $adminIndexViewContent = <<<PHP
@extends('layouts.panel')
@section('title', \$title)
@section('content')
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h3 class="card-title">Gestión de $moduleUpper</h3>
    <a href="{{ admin_url('$moduleLower/create') }}" class="btn btn-primary btn-sm">
      <i class="fas fa-plus"></i> Nuevo $moduleUpper
    </a>
  </div>
  <div class="card-body">
    @if (session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
      <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="table-responsive">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>Título</th>
            <th>Estado</th>
            <th>Fecha</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          @foreach(\$items as \$item)
          <tr>
            <td>{{ \$item['title'] }}</td>
            <td>
              @if(\$item['status'] == 'active')
                <span class="badge bg-success">Activo</span>
              @else
                <span class="badge bg-secondary">Inactivo</span>
              @endif
            </td>
            <td>{{ \$item['created_at'] }}</td>
            <td>
              <div class="btn-group">
                <a href="{{ admin_url('$moduleLower/' . \$item['id'] . '/edit') }}" class="btn btn-sm btn-primary">
                  <i class="fas fa-edit"></i>
                </a>
                <form method="POST" action="{{ admin_url('$moduleLower/' . \$item['id'] . '/delete') }}" class="d-inline" onsubmit="return confirm('¿Seguro que deseas eliminar este elemento?')">
                  {!! csrf_field() !!}
                  <button type="submit" class="btn btn-sm btn-danger">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
PHP;

    createFile($moduleDir . '/views/admin/index.blade.php', $adminIndexViewContent);
    
    // Crear vista create para tenant
    $adminCreateViewContent = <<<PHP
@extends('layouts.panel')
@section('title', \$title)
@section('content')
<div class="card">
  <div class="card-header">
    <h3 class="card-title">Nuevo $moduleUpper</h3>
  </div>
  <div class="card-body">
    @if (session('error'))
      <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ admin_url('$moduleLower/store') }}">
      {!! csrf_field() !!}
      
      <div class="form-group mb-3">
        <label for="title">Título</label>
        <input type="text" class="form-control" id="title" name="title" value="{{ old('title') }}" required>
        {!! form_error('title') !!}
      </div>
      
      <div class="form-group mb-3">
        <label for="description">Descripción</label>
        <textarea class="form-control" id="description" name="description" rows="3">{{ old('description') }}</textarea>
      </div>
      
      <div class="form-group mb-3">
        <label for="content">Contenido</label>
        <textarea class="form-control" id="content" name="content" rows="10" required>{{ old('content') }}</textarea>
        {!! form_error('content') !!}
      </div>
      
      <div class="form-group mb-3">
        <label for="status">Estado</label>
        <select class="form-control" id="status" name="status">
          <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Activo</option>
          <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactivo</option>
        </select>
      </div>
      
      <div class="form-group">
        <button type="submit" class="btn btn-primary">Guardar</button>
        <a href="{{ admin_url('$moduleLower') }}" class="btn btn-secondary">Cancelar</a>
      </div>
    </form>
  </div>
</div>

<script>
  // Si tienes un editor WYSIWYG disponible, puedes inicializarlo aquí
  document.addEventListener('DOMContentLoaded', function() {
    if (typeof CKEDITOR !== 'undefined') {
      CKEDITOR.replace('content');
    }
  });
</script>
@endsection
PHP;

    createFile($moduleDir . '/views/admin/create.blade.php', $adminCreateViewContent);
    
    // Crear vista edit para tenant
    $adminEditViewContent = <<<PHP
@extends('layouts.panel')
@section('title', \$title)
@section('content')
<div class="card">
  <div class="card-header">
    <h3 class="card-title">Editar $moduleUpper</h3>
  </div>
  <div class="card-body">
    @if (session('error'))
      <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ admin_url('$moduleLower/' . \$item['id'] . '/update') }}">
      {!! csrf_field() !!}
      
      <div class="form-group mb-3">
        <label for="title">Título</label>
        <input type="text" class="form-control" id="title" name="title" value="{{ old('title', \$item['title']) }}" required>
        {!! form_error('title') !!}
      </div>
      
      <div class="form-group mb-3">
        <label for="description">Descripción</label>
        <textarea class="form-control" id="description" name="description" rows="3">{{ old('description', \$item['description']) }}</textarea>
      </div>
      
      <div class="form-group mb-3">
        <label for="content">Contenido</label>
        <textarea class="form-control" id="content" name="content" rows="10" required>{{ old('content', \$item['content']) }}</textarea>
        {!! form_error('content') !!}
      </div>
      
      <div class="form-group mb-3">
        <label for="status">Estado</label>
        <select class="form-control" id="status" name="status">
          <option value="active" {{ old('status', \$item['status']) == 'active' ? 'selected' : '' }}>Activo</option>
          <option value="inactive" {{ old('status', \$item['status']) == 'inactive' ? 'selected' : '' }}>Inactivo</option>
        </select>
      </div>
      
      <div class="form-group">
        <button type="submit" class="btn btn-primary">Actualizar</button>
        <a href="{{ admin_url('$moduleLower') }}" class="btn btn-secondary">Cancelar</a>
      </div>
    </form>
  </div>
</div>

<script>
  // Si tienes un editor WYSIWYG disponible, puedes inicializarlo aquí
  document.addEventListener('DOMContentLoaded', function() {
    if (typeof CKEDITOR !== 'undefined') {
      CKEDITOR.replace('content');
    }
  });
</script>
@endsection
PHP;

    createFile($moduleDir . '/views/admin/edit.blade.php', $adminEditViewContent);
}

// 8. Crear vistas para el frontend
if ($includeFront) {
    // Crear directorio en el tema default
    $frontDir = $baseDir . 'themes/default/views/' . $moduleLower;
    if (!is_dir($frontDir)) {
        mkdir($frontDir, 0755, true);
        echo "  Creado: $frontDir\n";
    }
    
    // Crear vista index
    $frontIndexViewContent = <<<PHP
@extends('layouts.master')
@section('title', \$title)
@section('content')
<div class="container my-5">
  <h1 class="mb-4">$moduleUpper</h1>
  
  @if(count(\$items) > 0)
    <div class="row">
      @foreach(\$items as \$item)
        <div class="col-md-4 mb-4">
          <div class="card h-100">
            <div class="card-body">
              <h5 class="card-title">{{ \$item['title'] }}</h5>
              <p class="card-text text-muted small">
                <span>{{ \$item['created_at'] }}</span>
              </p>
              <div class="card-text mb-3">
                {{ \$item['description'] ?? substr(strip_tags(\$item['content']), 0, 150) . '...' }}
              </div>
              <a href="/$moduleLower/{{ \$item['slug'] }}" class="btn btn-primary btn-sm">Ver más</a>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  @else
    <div class="alert alert-info">
      No hay elementos disponibles todavía.
    </div>
  @endif
</div>
@endsection
PHP;

    createFile($frontDir . '/index.blade.php', $frontIndexViewContent);
    
    // Crear vista show
    $frontShowViewContent = <<<PHP
@extends('layouts.master')
@section('title', \$title)
@section('content')
<div class="container my-5">
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="/">Inicio</a></li>
      <li class="breadcrumb-item"><a href="/$moduleLower">$moduleUpper</a></li>
      <li class="breadcrumb-item active" aria-current="page">{{ \$item['title'] }}</li>
    </ol>
  </nav>
  
  <article>
    <h1 class="mb-4">{{ \$item['title'] }}</h1>
    
    <div class="d-flex align-items-center text-muted small mb-4">
      <span class="me-3">
        <i class="fas fa-calendar"></i> {{ \$item['created_at'] }}
      </span>
    </div>
    
    <div class="content-body">
      {!! \$item['content'] !!}
    </div>
  </article>
</div>
@endsection
PHP;

    createFile($frontDir . '/show.blade.php', $frontShowViewContent);
}

// Mostrar instrucciones finales
echo "\n";
echo "¡Módulo '$moduleUpper' creado con éxito!\n";
echo "\nParas finalizar la instalación:\n";
echo "1. Asegúrate de que el módulo está registrado en la base de datos:\n";
echo "   - Visita '/musedock/modules' para verificar que aparece en la lista\n";
echo "   - Si no aparece, ejecuta 'ModuleManager::syncModulesWithDisk()'\n";
echo "\n2. Ejecuta el script de instalación para crear las tablas necesarias:\n";
echo "   - Visita '/musedock/modules/$moduleLower/install.php'\n";
echo "\n3. Activa el módulo desde el panel de administración:\n";
echo "   - Marca como activo\n";
echo "   - Habilita para CMS si quieres que esté disponible en el dominio principal\n";
echo "   - Asigna a los tenants que desees\n";
echo "\n¡Listo! Tu módulo ya debería estar funcionando.\n";
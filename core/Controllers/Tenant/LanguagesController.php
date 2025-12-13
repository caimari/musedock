<?php

namespace Screenart\Musedock\Controllers\Tenant;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Traits\RequiresPermission;

class LanguagesController
{
    use RequiresPermission;

    /**
     * Lista los idiomas del tenant actual
     */
    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.view');

        $tenantId = tenant('id');

        if (!$tenantId) {
            flash('error', 'No se pudo identificar el tenant actual.');
            header('Location: /' . admin_path());
            exit;
        }

        // Obtener idiomas del tenant
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT * FROM languages
            WHERE tenant_id = :tenant_id
            ORDER BY order_position ASC, id ASC
        ");
        $stmt->execute(['tenant_id' => $tenantId]);
        $languages = $stmt->fetchAll(\PDO::FETCH_OBJ);

        // Obtener idioma por defecto del tenant
        $defaultLang = setting('default_lang', 'es');

        return View::renderTenant('languages.index', [
            'title' => 'Idiomas',
            'languages' => $languages,
            'default_lang' => $defaultLang
        ]);
    }

    /**
     * Formulario para crear un nuevo idioma
     */
    public function create()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        return View::renderTenant('languages.create', [
            'title' => 'Añadir Idioma'
        ]);
    }

    /**
     * Guarda un nuevo idioma para el tenant
     */
    public function store()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        $tenantId = tenant('id');

        if (!$tenantId) {
            flash('error', 'No se pudo identificar el tenant actual.');
            header('Location: /' . admin_path() . '/languages');
            exit;
        }

        $code = trim($_POST['code'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;

        // Validaciones
        if (empty($code) || empty($name)) {
            flash('error', 'El código y nombre del idioma son obligatorios.');
            header('Location: /' . admin_path() . '/languages/create');
            exit;
        }

        // Validar formato del código (2-5 caracteres, letras minúsculas)
        if (!preg_match('/^[a-z]{2,5}$/', $code)) {
            flash('error', 'El código debe tener entre 2-5 letras minúsculas (ej: es, en, pt-br).');
            header('Location: /' . admin_path() . '/languages/create');
            exit;
        }

        // Verificar si ya existe para este tenant
        $existing = Database::table('languages')
            ->where('code', $code)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($existing) {
            flash('error', 'Ya existe un idioma con este código en tu sitio.');
            header('Location: /' . admin_path() . '/languages/create');
            exit;
        }

        // Obtener la siguiente posición
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(order_position), -1) + 1 as next_pos FROM languages WHERE tenant_id = :tenant_id");
        $stmt->execute(['tenant_id' => $tenantId]);
        $nextPos = $stmt->fetchColumn();

        // Insertar
        Database::table('languages')->insert([
            'code' => $code,
            'name' => $name,
            'active' => $active,
            'tenant_id' => $tenantId,
            'order_position' => $nextPos,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        flash('success', 'Idioma añadido correctamente.');
        header('Location: /' . admin_path() . '/languages');
        exit;
    }

    /**
     * Formulario para editar un idioma
     */
    public function edit($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        $tenantId = tenant('id');

        // Verificar que el idioma pertenece al tenant
        $language = Database::table('languages')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$language) {
            flash('error', 'Idioma no encontrado.');
            header('Location: /' . admin_path() . '/languages');
            exit;
        }

        return View::renderTenant('languages.edit', [
            'title' => 'Editar Idioma',
            'language' => $language
        ]);
    }

    /**
     * Actualiza un idioma
     */
    public function update($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        $tenantId = tenant('id');

        // Verificar que el idioma pertenece al tenant
        $language = Database::table('languages')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$language) {
            flash('error', 'Idioma no encontrado.');
            header('Location: /' . admin_path() . '/languages');
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;

        if (empty($name)) {
            flash('error', 'El nombre del idioma es obligatorio.');
            header('Location: /' . admin_path() . '/languages/' . $id . '/edit');
            exit;
        }

        // Si se está desactivando, verificar que no sea el último activo
        if ($active === 0) {
            $activeCount = Database::table('languages')
                ->where('tenant_id', $tenantId)
                ->where('active', 1)
                ->count();

            if ($activeCount <= 1) {
                flash('error', 'No puedes desactivar el último idioma activo.');
                header('Location: /' . admin_path() . '/languages/' . $id . '/edit');
                exit;
            }
        }

        Database::table('languages')
            ->where('id', $id)
            ->update([
                'name' => $name,
                'active' => $active
            ]);

        flash('success', 'Idioma actualizado correctamente.');
        header('Location: /' . admin_path() . '/languages');
        exit;
    }

    /**
     * Elimina un idioma
     */
    public function delete($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        $tenantId = tenant('id');

        // Verificar contraseña
        $password = $_POST['password'] ?? '';
        if (empty($password)) {
            flash('error', 'Debes confirmar con tu contraseña.');
            header('Location: /' . admin_path() . '/languages');
            exit;
        }

        // Obtener usuario actual y verificar contraseña
        $auth = SessionSecurity::getAuthenticatedUser();
        $user = Database::table('users')->where('id', $auth['id'])->first();

        if (!$user || !password_verify($password, $user->password)) {
            flash('error', 'Contraseña incorrecta.');
            header('Location: /' . admin_path() . '/languages');
            exit;
        }

        // Verificar que el idioma pertenece al tenant
        $language = Database::table('languages')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$language) {
            flash('error', 'Idioma no encontrado.');
            header('Location: /' . admin_path() . '/languages');
            exit;
        }

        // Verificar que no sea el último idioma
        $count = Database::table('languages')
            ->where('tenant_id', $tenantId)
            ->count();

        if ($count <= 1) {
            flash('error', 'No puedes eliminar el último idioma.');
            header('Location: /' . admin_path() . '/languages');
            exit;
        }

        Database::table('languages')
            ->where('id', $id)
            ->delete();

        flash('success', 'Idioma eliminado correctamente.');
        header('Location: /' . admin_path() . '/languages');
        exit;
    }

    /**
     * Toggle activo/inactivo
     */
    public function toggle($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        $tenantId = tenant('id');

        $lang = Database::table('languages')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$lang) {
            flash('error', 'Idioma no encontrado.');
            header('Location: /' . admin_path() . '/languages');
            exit;
        }

        $newStatus = ($lang->active ?? 0) ? 0 : 1;

        // Si se está desactivando, verificar que no sea el último activo
        if ($newStatus === 0) {
            $activeCount = Database::table('languages')
                ->where('tenant_id', $tenantId)
                ->where('active', 1)
                ->count();

            if ($activeCount <= 1) {
                flash('error', 'No puedes desactivar el último idioma activo.');
                header('Location: /' . admin_path() . '/languages');
                exit;
            }
        }

        Database::table('languages')
            ->where('id', $id)
            ->update(['active' => $newStatus]);

        flash('success', 'Estado del idioma actualizado.');
        header('Location: /' . admin_path() . '/languages');
        exit;
    }

    /**
     * Actualiza el orden de los idiomas (drag & drop)
     */
    public function updateOrder()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        header('Content-Type: application/json');

        $tenantId = tenant('id');
        $input = json_decode(file_get_contents('php://input'), true);
        $order = $input['order'] ?? [];

        if (empty($order)) {
            echo json_encode(['success' => false, 'error' => 'No se recibieron datos de orden']);
            exit;
        }

        $pdo = Database::connect();

        foreach ($order as $position => $id) {
            // Verificar que el idioma pertenece al tenant antes de actualizar
            $stmt = $pdo->prepare("UPDATE languages SET order_position = ? WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$position, $id, $tenantId]);
        }

        echo json_encode(['success' => true]);
        exit;
    }

    /**
     * Establece el idioma por defecto del tenant
     */
    public function setDefault()
    {
        SessionSecurity::startSession();
        $this->checkPermission('settings.edit');

        $tenantId = tenant('id');
        $defaultLang = $_POST['default_lang'] ?? '';

        if (empty($defaultLang)) {
            flash('error', 'Debes seleccionar un idioma por defecto.');
            header('Location: /' . admin_path() . '/languages');
            exit;
        }

        // Verificar que el idioma existe y pertenece al tenant
        $langExists = Database::table('languages')
            ->where('code', $defaultLang)
            ->where('tenant_id', $tenantId)
            ->where('active', 1)
            ->first();

        if (!$langExists) {
            flash('error', 'El idioma seleccionado no existe o no está activo.');
            header('Location: /' . admin_path() . '/languages');
            exit;
        }

        // Guardar en tenant_settings
        $existing = Database::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->where('key', 'default_lang')
            ->first();

        if ($existing) {
            Database::table('tenant_settings')
                ->where('tenant_id', $tenantId)
                ->where('key', 'default_lang')
                ->update(['value' => $defaultLang]);
        } else {
            Database::table('tenant_settings')->insert([
                'tenant_id' => $tenantId,
                'key' => 'default_lang',
                'value' => $defaultLang
            ]);
        }

        flash('success', 'Idioma por defecto actualizado a: ' . strtoupper($defaultLang));
        header('Location: /' . admin_path() . '/languages');
        exit;
    }
}

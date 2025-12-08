<?php

namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Security\SessionSecurity;

class ProfileController
{
    /**
     * Obtiene la tabla de BD según el tipo de usuario
     */
    private function getTableByUserType($type)
    {
        switch ($type) {
            case 'super_admin':
                return 'super_admins';
            case 'admin':
                return 'admins';
            case 'user':
                return 'users';
            default:
                return null;
        }
    }

    /**
     * Obtiene la clave de sesión según el tipo de usuario
     */
    private function getSessionKeyByUserType($type)
    {
        switch ($type) {
            case 'super_admin':
                return 'super_admin';
            case 'admin':
                return 'admin';
            case 'user':
                return 'user';
            default:
                return null;
        }
    }
    /**
     * Muestra el formulario de perfil
     */
    public function index()
    {
        $user = SessionSecurity::getAuthenticatedUser();

        if (!$user) {
            flash('error', 'Debes iniciar sesión.');
            header('Location: /musedock/login');
            exit;
        }

        // Obtener datos del usuario según su tipo
        $pdo = Database::connect();
        $userData = null;

        switch ($user['type']) {
            case 'super_admin':
                $stmt = $pdo->prepare("SELECT id, name, email, avatar FROM super_admins WHERE id = ?");
                $stmt->execute([$user['id']]);
                $userData = $stmt->fetch(\PDO::FETCH_OBJ);
                break;

            case 'admin':
                $stmt = $pdo->prepare("SELECT id, name, email, avatar FROM admins WHERE id = ?");
                $stmt->execute([$user['id']]);
                $userData = $stmt->fetch(\PDO::FETCH_OBJ);
                break;

            case 'user':
                $stmt = $pdo->prepare("SELECT id, name, email, avatar FROM users WHERE id = ?");
                $stmt->execute([$user['id']]);
                $userData = $stmt->fetch(\PDO::FETCH_OBJ);
                break;

            default:
                flash('error', 'Tipo de usuario no válido.');
                header('Location: /musedock/login');
                exit;
        }

        if (!$userData) {
            flash('error', 'Usuario no encontrado.');
            header('Location: /musedock');
            exit;
        }

        return View::renderSuperadmin('profile.index', [
            'title' => 'Mi Perfil',
            'user' => $userData,
            'userType' => $user['type']
        ]);
    }

    /**
     * Actualiza el nombre del usuario
     */
    public function updateName()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            flash('error', 'Método no permitido.');
            header('Location: /musedock/profile');
            exit;
        }

        $user = SessionSecurity::getAuthenticatedUser();

        if (!$user) {
            flash('error', 'Debes iniciar sesión.');
            header('Location: /musedock/login');
            exit;
        }

        $name = trim($_POST['name'] ?? '');

        if (empty($name)) {
            flash('error', 'El nombre es obligatorio.');
            header('Location: /musedock/profile');
            exit;
        }

        $table = $this->getTableByUserType($user['type']);
        $sessionKey = $this->getSessionKeyByUserType($user['type']);

        if (!$table || !$sessionKey) {
            flash('error', 'Tipo de usuario no válido.');
            header('Location: /musedock/login');
            exit;
        }

        $pdo = Database::connect();

        try {
            $stmt = $pdo->prepare("UPDATE {$table} SET name = ? WHERE id = ?");
            $stmt->execute([$name, $user['id']]);

            // Actualizar sesión
            $_SESSION[$sessionKey]['name'] = $name;

            flash('success', 'Nombre actualizado correctamente.');
            header('Location: /musedock/profile');
            exit;

        } catch (\Exception $e) {
            flash('error', 'Error al actualizar el nombre: ' . $e->getMessage());
            header('Location: /musedock/profile');
            exit;
        }
    }

    /**
     * Actualiza el email del usuario (requiere confirmación de contraseña)
     */
    public function updateEmail()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            flash('error', 'Método no permitido.');
            header('Location: /musedock/profile');
            exit;
        }

        $user = SessionSecurity::getAuthenticatedUser();

        if (!$user) {
            flash('error', 'Debes iniciar sesión.');
            header('Location: /musedock/login');
            exit;
        }

        $email = trim($_POST['email'] ?? '');
        $currentPassword = $_POST['current_password'] ?? '';

        if (empty($email)) {
            flash('error', 'El email es obligatorio.');
            header('Location: /musedock/profile');
            exit;
        }

        // Validar email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'El email no es válido.');
            header('Location: /musedock/profile');
            exit;
        }

        if (empty($currentPassword)) {
            flash('error', 'Debes ingresar tu contraseña actual para cambiar el email.');
            header('Location: /musedock/profile');
            exit;
        }

        $table = $this->getTableByUserType($user['type']);
        $sessionKey = $this->getSessionKeyByUserType($user['type']);

        if (!$table || !$sessionKey) {
            flash('error', 'Tipo de usuario no válido.');
            header('Location: /musedock/login');
            exit;
        }

        $pdo = Database::connect();

        try {
            $pdo->beginTransaction();

            // Verificar contraseña actual
            $stmt = $pdo->prepare("SELECT password FROM {$table} WHERE id = ?");
            $stmt->execute([$user['id']]);
            $userRecord = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!password_verify($currentPassword, $userRecord['password'])) {
                throw new \Exception('La contraseña actual es incorrecta.');
            }

            // Verificar si el email ya existe (para otro usuario)
            $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user['id']]);
            if ($stmt->fetch()) {
                throw new \Exception('El email ya está en uso por otro usuario.');
            }

            // Actualizar email
            $stmt = $pdo->prepare("UPDATE {$table} SET email = ? WHERE id = ?");
            $stmt->execute([$email, $user['id']]);

            // Actualizar sesión
            $_SESSION[$sessionKey]['email'] = $email;

            $pdo->commit();
            flash('success', 'Email actualizado correctamente.');
            header('Location: /musedock/profile');
            exit;

        } catch (\Exception $e) {
            $pdo->rollBack();
            flash('error', 'Error al actualizar el email: ' . $e->getMessage());
            header('Location: /musedock/profile');
            exit;
        }
    }

    /**
     * Actualiza la contraseña del usuario
     */
    public function updatePassword()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            flash('error', 'Método no permitido.');
            header('Location: /musedock/profile');
            exit;
        }

        $user = SessionSecurity::getAuthenticatedUser();

        if (!$user) {
            flash('error', 'Debes iniciar sesión.');
            header('Location: /musedock/login');
            exit;
        }

        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            flash('error', 'Todos los campos de contraseña son obligatorios.');
            header('Location: /musedock/profile');
            exit;
        }

        $table = $this->getTableByUserType($user['type']);

        if (!$table) {
            flash('error', 'Tipo de usuario no válido.');
            header('Location: /musedock/login');
            exit;
        }

        $pdo = Database::connect();

        try {
            // Verificar contraseña actual
            $stmt = $pdo->prepare("SELECT password FROM {$table} WHERE id = ?");
            $stmt->execute([$user['id']]);
            $userRecord = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!password_verify($currentPassword, $userRecord['password'])) {
                throw new \Exception('La contraseña actual es incorrecta.');
            }

            // Validar nueva contraseña
            if (strlen($newPassword) < 6) {
                throw new \Exception('La nueva contraseña debe tener al menos 6 caracteres.');
            }

            if ($newPassword !== $confirmPassword) {
                throw new \Exception('Las contraseñas no coinciden.');
            }

            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            // Actualizar contraseña
            $stmt = $pdo->prepare("UPDATE {$table} SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $user['id']]);

            flash('success', 'Contraseña actualizada correctamente.');
            header('Location: /musedock/profile');
            exit;

        } catch (\Exception $e) {
            flash('error', 'Error al actualizar la contraseña: ' . $e->getMessage());
            header('Location: /musedock/profile');
            exit;
        }
    }

    /**
     * Sube un nuevo avatar
     */
    public function uploadAvatar()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Método no permitido']);
            exit;
        }

        $user = SessionSecurity::getAuthenticatedUser();

        if (!$user) {
            echo json_encode(['success' => false, 'error' => 'Debes iniciar sesión']);
            exit;
        }

        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'No se recibió ningún archivo']);
            exit;
        }

        $file = $_FILES['avatar'];
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $maxSize = 2 * 1024 * 1024; // 2MB

        // Validar tipo
        if (!in_array($file['type'], $allowedTypes)) {
            echo json_encode(['success' => false, 'error' => 'Tipo de archivo no permitido. Solo JPG, PNG o GIF.']);
            exit;
        }

        // Validar tamaño
        if ($file['size'] > $maxSize) {
            echo json_encode(['success' => false, 'error' => 'El archivo es muy grande. Máximo 2MB.']);
            exit;
        }

        $table = $this->getTableByUserType($user['type']);
        $sessionKey = $this->getSessionKeyByUserType($user['type']);

        if (!$table || !$sessionKey) {
            echo json_encode(['success' => false, 'error' => 'Tipo de usuario no válido']);
            exit;
        }

        $pdo = Database::connect();

        try {
            // Obtener avatar actual
            $stmt = $pdo->prepare("SELECT avatar FROM {$table} WHERE id = ?");
            $stmt->execute([$user['id']]);
            $currentAvatar = $stmt->fetchColumn();

            // Crear directorio de avatares si no existe
            $avatarDir = APP_ROOT . '/storage/avatars';
            if (!is_dir($avatarDir)) {
                mkdir($avatarDir, 0755, true);
            }

            // Generar nombre único
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . $user['type'] . '_' . $user['id'] . '_' . time() . '.' . $extension;
            $destination = $avatarDir . '/' . $filename;

            // Mover archivo
            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                throw new \Exception('Error al guardar el archivo.');
            }

            // Actualizar BD
            $stmt = $pdo->prepare("UPDATE {$table} SET avatar = ? WHERE id = ?");
            $stmt->execute([$filename, $user['id']]);

            // Eliminar avatar anterior si existe
            if ($currentAvatar && file_exists($avatarDir . '/' . $currentAvatar)) {
                unlink($avatarDir . '/' . $currentAvatar);
            }

            // Actualizar sesión
            $_SESSION[$sessionKey]['avatar'] = $filename;

            echo json_encode([
                'success' => true,
                'avatar_url' => '/musedock/avatar/' . $filename
            ]);
            exit;

        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * Elimina el avatar actual
     */
    public function deleteAvatar()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'Método no permitido']);
            exit;
        }

        $user = SessionSecurity::getAuthenticatedUser();

        if (!$user) {
            echo json_encode(['success' => false, 'error' => 'Debes iniciar sesión']);
            exit;
        }

        $table = $this->getTableByUserType($user['type']);
        $sessionKey = $this->getSessionKeyByUserType($user['type']);

        if (!$table || !$sessionKey) {
            echo json_encode(['success' => false, 'error' => 'Tipo de usuario no válido']);
            exit;
        }

        $pdo = Database::connect();

        try {
            // Obtener avatar actual
            $stmt = $pdo->prepare("SELECT avatar FROM {$table} WHERE id = ?");
            $stmt->execute([$user['id']]);
            $currentAvatar = $stmt->fetchColumn();

            if ($currentAvatar) {
                // Eliminar archivo
                $avatarPath = APP_ROOT . '/storage/avatars/' . $currentAvatar;
                if (file_exists($avatarPath)) {
                    unlink($avatarPath);
                }

                // Actualizar BD
                $stmt = $pdo->prepare("UPDATE {$table} SET avatar = NULL WHERE id = ?");
                $stmt->execute([$user['id']]);

                // Actualizar sesión
                $_SESSION[$sessionKey]['avatar'] = null;
            }

            echo json_encode(['success' => true]);
            exit;

        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * Sirve el avatar del usuario (ruta privada)
     */
    public function serveAvatar($filename)
    {
        $avatarPath = APP_ROOT . '/storage/avatars/' . basename($filename);

        if (!file_exists($avatarPath)) {
            header('HTTP/1.0 404 Not Found');
            exit;
        }

        // Obtener tipo MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $avatarPath);
        finfo_close($finfo);

        // Enviar headers
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($avatarPath));
        header('Cache-Control: public, max-age=31536000');

        // Enviar archivo
        readfile($avatarPath);
        exit;
    }
}

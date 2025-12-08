<?php

namespace Screenart\Musedock\Controllers\Tenant;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Helpers\FileUploadValidator;

class ProfileController
{
    /**
     * Muestra el formulario de perfil
     */
    public function index()
    {
        $user = SessionSecurity::getAuthenticatedUser();

        if (!$user || $user['type'] !== 'admin') {
            flash('error', 'Acceso denegado.');
            header('Location: ' . admin_url('/login'));
            exit;
        }

        // Obtener datos actuales del admin desde la BD
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT id, name, email, avatar FROM admins WHERE id = ?");
        $stmt->execute([$user['id']]);
        $admin = $stmt->fetch(\PDO::FETCH_OBJ);

        if (!$admin) {
            flash('error', 'Usuario no encontrado.');
            header('Location: ' . admin_url());
            exit;
        }

        return View::renderTenantAdmin('profile.index', [
            'title' => 'Mi Perfil',
            'user' => $admin
        ]);
    }

    /**
     * Actualiza el nombre del usuario
     */
    public function updateName()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            flash('error', 'Método no permitido.');
            header('Location: ' . admin_url('/profile'));
            exit;
        }

        $user = SessionSecurity::getAuthenticatedUser();

        if (!$user || $user['type'] !== 'admin') {
            flash('error', 'Acceso denegado.');
            header('Location: ' . admin_url('/login'));
            exit;
        }

        $name = trim($_POST['name'] ?? '');

        if (empty($name)) {
            flash('error', 'El nombre es obligatorio.');
            header('Location: ' . admin_url('/profile'));
            exit;
        }

        $pdo = Database::connect();

        try {
            $stmt = $pdo->prepare("UPDATE admins SET name = ? WHERE id = ?");
            $stmt->execute([$name, $user['id']]);

            // Actualizar sesión
            $_SESSION['admin']['name'] = $name;

            flash('success', 'Nombre actualizado correctamente.');
            header('Location: ' . admin_url('/profile'));
            exit;

        } catch (\Exception $e) {
            flash('error', 'Error al actualizar el nombre: ' . $e->getMessage());
            header('Location: ' . admin_url('/profile'));
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
            header('Location: ' . admin_url('/profile'));
            exit;
        }

        $user = SessionSecurity::getAuthenticatedUser();

        if (!$user || $user['type'] !== 'admin') {
            flash('error', 'Acceso denegado.');
            header('Location: ' . admin_url('/login'));
            exit;
        }

        $email = trim($_POST['email'] ?? '');
        $currentPassword = $_POST['current_password'] ?? '';

        if (empty($email)) {
            flash('error', 'El email es obligatorio.');
            header('Location: ' . admin_url('/profile'));
            exit;
        }

        // Validar email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'El email no es válido.');
            header('Location: ' . admin_url('/profile'));
            exit;
        }

        if (empty($currentPassword)) {
            flash('error', 'Debes ingresar tu contraseña actual para cambiar el email.');
            header('Location: ' . admin_url('/profile'));
            exit;
        }

        $pdo = Database::connect();

        try {
            $pdo->beginTransaction();

            // Verificar contraseña actual
            $stmt = $pdo->prepare("SELECT password FROM admins WHERE id = ?");
            $stmt->execute([$user['id']]);
            $userRecord = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!password_verify($currentPassword, $userRecord['password'])) {
                throw new \Exception('La contraseña actual es incorrecta.');
            }

            // Verificar si el email ya existe (para otro usuario)
            $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user['id']]);
            if ($stmt->fetch()) {
                throw new \Exception('El email ya está en uso por otro usuario.');
            }

            // Actualizar email
            $stmt = $pdo->prepare("UPDATE admins SET email = ? WHERE id = ?");
            $stmt->execute([$email, $user['id']]);

            // Actualizar sesión
            $_SESSION['admin']['email'] = $email;

            $pdo->commit();
            flash('success', 'Email actualizado correctamente.');
            header('Location: ' . admin_url('/profile'));
            exit;

        } catch (\Exception $e) {
            $pdo->rollBack();
            flash('error', 'Error al actualizar el email: ' . $e->getMessage());
            header('Location: ' . admin_url('/profile'));
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
            header('Location: ' . admin_url('/profile'));
            exit;
        }

        $user = SessionSecurity::getAuthenticatedUser();

        if (!$user || $user['type'] !== 'admin') {
            flash('error', 'Acceso denegado.');
            header('Location: ' . admin_url('/login'));
            exit;
        }

        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            flash('error', 'Todos los campos de contraseña son obligatorios.');
            header('Location: ' . admin_url('/profile'));
            exit;
        }

        $pdo = Database::connect();

        try {
            // Verificar contraseña actual
            $stmt = $pdo->prepare("SELECT password FROM admins WHERE id = ?");
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
            $stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $user['id']]);

            flash('success', 'Contraseña actualizada correctamente.');
            header('Location: ' . admin_url('/profile'));
            exit;

        } catch (\Exception $e) {
            flash('error', 'Error al actualizar la contraseña: ' . $e->getMessage());
            header('Location: ' . admin_url('/profile'));
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

        if (!$user || $user['type'] !== 'admin') {
            echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
            exit;
        }

        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'No se recibió ningún archivo']);
            exit;
        }

        $file = $_FILES['avatar'];

        // 🔒 SECURITY: Validación robusta con FileUploadValidator
        // Previene: MIME spoofing, polyglot files, RCE
        $validation = FileUploadValidator::validateImage($file, 2 * 1024 * 1024);

        if (!$validation['valid']) {
            echo json_encode(['success' => false, 'error' => $validation['error']]);
            exit;
        }

        $pdo = Database::connect();

        try {
            // Obtener avatar actual
            $stmt = $pdo->prepare("SELECT avatar FROM admins WHERE id = ?");
            $stmt->execute([$user['id']]);
            $currentAvatar = $stmt->fetchColumn();

            // Crear directorio de avatares si no existe
            $avatarDir = APP_ROOT . '/storage/avatars';
            if (!is_dir($avatarDir)) {
                mkdir($avatarDir, 0755, true);
            }

            // Generar nombre seguro
            $extension = $validation['extension'];
            $filename = FileUploadValidator::generateSecureFilename($extension, 'avatar_admin_' . $user['id']);
            $destination = $avatarDir . '/' . $filename;

            // Mover archivo
            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                throw new \Exception('Error al guardar el archivo.');
            }

            // Actualizar BD
            $stmt = $pdo->prepare("UPDATE admins SET avatar = ? WHERE id = ?");
            $stmt->execute([$filename, $user['id']]);

            // Eliminar avatar anterior si existe
            if ($currentAvatar && file_exists($avatarDir . '/' . $currentAvatar)) {
                unlink($avatarDir . '/' . $currentAvatar);
            }

            // Actualizar sesión
            $_SESSION['admin']['avatar'] = $filename;

            echo json_encode([
                'success' => true,
                'avatar_url' => admin_url('/avatar/' . $filename)
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

        if (!$user || $user['type'] !== 'admin') {
            echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
            exit;
        }

        $pdo = Database::connect();

        try {
            // Obtener avatar actual
            $stmt = $pdo->prepare("SELECT avatar FROM admins WHERE id = ?");
            $stmt->execute([$user['id']]);
            $currentAvatar = $stmt->fetchColumn();

            if ($currentAvatar) {
                // Eliminar archivo
                $avatarPath = APP_ROOT . '/storage/avatars/' . $currentAvatar;
                if (file_exists($avatarPath)) {
                    unlink($avatarPath);
                }

                // Actualizar BD
                $stmt = $pdo->prepare("UPDATE admins SET avatar = NULL WHERE id = ?");
                $stmt->execute([$user['id']]);

                // Actualizar sesión
                $_SESSION['admin']['avatar'] = null;
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
        // 🔒 SECURITY: Validación estricta para prevenir path traversal
        $filename = basename($filename);

        // Validar que solo contenga caracteres seguros y coincida con el patrón esperado
        if (!preg_match('/^[a-zA-Z0-9_-]+\.(jpg|jpeg|png|gif|webp)$/i', $filename)) {
            header('HTTP/1.0 403 Forbidden');
            exit;
        }

        $avatarPath = APP_ROOT . '/storage/avatars/' . $filename;

        // Verificar que el path resultante está dentro del directorio permitido
        $realPath = realpath(dirname($avatarPath));
        $expectedPath = realpath(APP_ROOT . '/storage/avatars');

        if ($realPath !== $expectedPath || $realPath === false) {
            header('HTTP/1.0 403 Forbidden');
            exit;
        }

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

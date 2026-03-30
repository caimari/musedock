<?php

namespace Screenart\Musedock\Controllers\Tenant;

use Screenart\Musedock\View;
use Screenart\Musedock\Database;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Models\User;
use Screenart\Musedock\Models\Admin;
use Screenart\Musedock\Mail\Mailer;

class AuthController
{
    public function loginForm()
    {
        SessionSecurity::startSession();

        // Si el usuario ya está autenticado, redirigir al dashboard
        if (isset($_SESSION['admin']) || isset($_SESSION['user'])) {
            header("Location: /" . admin_path() . "/dashboard");
            exit;
        }

        // Intentar restaurar sesión desde token "recordarme" si no hay sesión activa
        if (SessionSecurity::checkRemembered()) {
            // Verificar que la sesión restaurada sea de admin o user (no customer ni super_admin)
            if (isset($_SESSION['admin']) || isset($_SESSION['user'])) {
                header("Location: /" . admin_path() . "/dashboard");
                exit;
            }
        }

        return View::renderTenantAdmin('auth.login', [
            'title' => __('login_title'),
            'flash' => consume_flash('logout_success'),
        ]);
    }

    public function login()
    {
        SessionSecurity::startSession();

        error_log("Estado inicial de sesión: " . json_encode($_SESSION));

        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $tenantId = tenant_id();
        $adminPath = '/' . admin_path();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // --- RATE LIMITING CON DOBLE BLOQUEO ---
        $identifier = $email . '|' . $tenantId . '|' . $ip;
        $rateCheck = \Screenart\Musedock\Security\RateLimiter::checkDual($identifier, $email);

        if (!$rateCheck['allowed']) {
            flash('error', $rateCheck['message']);
            header("Location: {$adminPath}/login");
            exit;
        }

        // Si detectamos ataque distribuido, mostrar advertencia pero permitir login
        if ($rateCheck['reason'] === 'under_attack') {
            flash('warning', __('auth.account_under_attack'));
        }
        // ----------------------

        try {
            $db = Database::connect();

            // 🔒 SECURITY: Hash email antes de loguear para prevenir information disclosure
            $emailHash = substr(hash('sha256', $email), 0, 8);
            error_log("Intento de login (email hash: {$emailHash}, tenant: $tenantId)");

            // Primero intentamos con admin
            $stmt = $db->prepare("SELECT * FROM admins WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            $admin = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($admin) {
                // Es un admin, verificamos tenant y contraseña
                if ((int)$admin['tenant_id'] !== (int)$tenantId) {
                    error_log("Tenant ID incorrecto. Admin: {$admin['tenant_id']}, Solicitado: $tenantId");
                    flash('error', __('auth.access_denied_wrong_tenant') ?? 'Tu cuenta no está asociada a este panel.');
                    header("Location: {$adminPath}/login");
                    exit;
                }

                // Verificar contraseña
                $passwordValid = password_verify($password, $admin['password']);
                // 🔒 SECURITY: No loguear email completo
                error_log("Verificación de contraseña para admin (hash: {$emailHash}): " . ($passwordValid ? 'OK' : 'FALLO'));

                if ($passwordValid) {
                    // Limpiar intentos fallidos (específico + global)
                    \Screenart\Musedock\Security\RateLimiter::clearDual($identifier, $email);
                    // Guardar datos en sesión antes de regenerar
                    $_SESSION['admin'] = [
                        'id'         => $admin['id'],
                        'email'      => $admin['email'],
                        'name'       => $admin['name'] ?? 'Admin',
                        'tenant_id'  => $tenantId,
                        'role'       => $admin['role'] ?? 'admin',
                        'avatar'     => $admin['avatar'] ?? null
                    ];
                    
                    // Actualizar tiempo de actividad
                    $_SESSION['last_active'] = time();

                    // 🔒 SECURITY: Solo loguear session ID, no el contenido completo
                    error_log("Sesión antes de regenerar (admin): " . session_id());

                    // Regenerar ID de sesión para mayor seguridad
                    SessionSecurity::regenerate();

                    // 🔒 SECURITY: Solo loguear session ID, no el contenido completo
                    error_log("Sesión después de regenerar (admin): " . session_id());

                    if (!empty($_POST['remember'])) {
                        // Eliminar tokens antiguos para prevenir duplicados
                        $this->removeOldTokens($admin['id'], 'admin');
                        
                        // Crear nuevo token de recordarme
                        SessionSecurity::rememberMe($admin['id'], 'admin');
                    }
                    
                    // Registrar o actualizar la actividad del usuario en user_activity
                    $activityStored = $this->updateUserActivity($admin['id'], 'admin');
                    
                    if (!$activityStored) {
                        error_log("ADVERTENCIA: No se pudo actualizar la actividad del admin en user_activity");
                    }

                    // 🔒 SECURITY: No loguear contenido de sesión
                    error_log("Login exitoso de admin, redirigiendo a dashboard");

                    header("Location: {$adminPath}/dashboard");
                    exit;
                }
            } else {
                // No es admin, intentamos con usuario regular
                $stmt = $db->prepare("SELECT * FROM users WHERE email = :email AND tenant_id = :tenant_id LIMIT 1");
                $stmt->execute(['email' => $email, 'tenant_id' => $tenantId]);
                $user = $stmt->fetch(\PDO::FETCH_ASSOC);

                if (!$user) {
                    // 🔒 SECURITY: No loguear email completo
                    error_log("Usuario no encontrado (hash: {$emailHash}, tenant: $tenantId)");
                    flash('error', __('auth.invalid_credentials') ?? 'Credenciales incorrectas');
                    header("Location: {$adminPath}/login");
                    exit;
                }

                // Verificar contraseña
                $passwordValid = password_verify($password, $user['password']);
                // 🔒 SECURITY: No loguear email completo
                error_log("Verificación de contraseña para usuario (hash: {$emailHash}): " . ($passwordValid ? 'OK' : 'FALLO'));

                if ($passwordValid) {
                    // Limpiar intentos fallidos (específico + global)
                    \Screenart\Musedock\Security\RateLimiter::clearDual($identifier, $email);
                    // Guardar datos en sesión antes de regenerar
                    $_SESSION['user'] = [
                        'id'         => $user['id'],
                        'email'      => $user['email'],
                        'name'       => $user['name'] ?? 'Usuario',
                        'tenant_id'  => $tenantId,
                        'role'       => $user['role'] ?? 'user'
                    ];

                    // Actualizar tiempo de actividad
                    $_SESSION['last_active'] = time();

                    // 🔒 SECURITY: Solo loguear session ID, no el contenido completo
                    error_log("Sesión antes de regenerar (usuario): " . session_id());

                    // Regenerar ID de sesión para mayor seguridad
                    SessionSecurity::regenerate();

                    // 🔒 SECURITY: Solo loguear session ID, no el contenido completo
                    error_log("Sesión después de regenerar (usuario): " . session_id());

                    if (!empty($_POST['remember'])) {
                        // Eliminar tokens antiguos para prevenir duplicados
                        $this->removeOldTokens($user['id'], 'user');
                        
                        // Crear nuevo token de recordarme
                        SessionSecurity::rememberMe($user['id'], 'user');
                    }
                    
                    // Registrar o actualizar la actividad del usuario en user_activity
                    $activityStored = $this->updateUserActivity($user['id'], 'user');
                    
                    if (!$activityStored) {
                        error_log("ADVERTENCIA: No se pudo actualizar la actividad del usuario en user_activity");
                    }

                    // 🔒 SECURITY: No loguear contenido de sesión
                    error_log("Login exitoso de usuario, redirigiendo a dashboard");

                    header("Location: {$adminPath}/dashboard");
                    exit;
                }
            }

        } catch (\Exception $e) {
            error_log("Error al autenticar usuario/admin: " . $e->getMessage());
            // 🔒 SECURITY: No loguear trace completo en producción
            if (getenv('APP_ENV') === 'development') {
                error_log("Traza de error: " . $e->getTraceAsString());
            }
        }

        // Fallo de autenticación - Incrementar contador (específico + global)
        $attempts = \Screenart\Musedock\Security\RateLimiter::incrementDual($identifier, $email);
        $remaining = \Screenart\Musedock\Security\RateLimiter::remaining($identifier);

        // 🔒 SECURITY: No loguear email completo
        error_log("Login fallido (hash: {$emailHash}). Intentos restantes: {$remaining}, Global: {$attempts['global_attempts']}");

        if ($remaining > 0) {
            flash('error', __('auth.invalid_credentials_attempts', ['attempts' => $remaining]) ?? "Credenciales incorrectas. Te quedan {$remaining} intentos.");
        } else {
            flash('error', __('auth.invalid_credentials') ?? 'Credenciales incorrectas');
        }

        header("Location: {$adminPath}/login");
        exit;
    }

 public function logout()
{
    SessionSecurity::startSession();

    $userId = $_SESSION['admin']['id'] ?? $_SESSION['user']['id'] ?? null;
    $userType = isset($_SESSION['admin']) ? 'admin' : 'user';

    if ($userId) {
        try {
            $db = Database::connect();

            // Eliminar registros de actividad
            if ($userType === 'admin') {
                $db->prepare("DELETE FROM admin_activity WHERE admin_id = :id")
                   ->execute(['id' => $userId]);
            } elseif ($userType === 'user') {
                $db->prepare("DELETE FROM user_activity WHERE user_id = :id")
                   ->execute(['id' => $userId]);
            }

            error_log("Actividad eliminada para $userType $userId");

        } catch (\Exception $e) {
            error_log("Error al cerrar sesión: " . $e->getMessage());
        }
    }

    // Usar logout selectivo - solo cierra sesión del tipo actual
    if ($userType) {
        SessionSecurity::logoutUserType($userType);
    }

    // Nueva sesión para flash message (la sesión PHP sigue activa)
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    flash('logout_success', 'Has cerrado sesión correctamente.');
    header("Location: /" . admin_path() . "/login");
    exit;
}


    private function removeOldTokens($userId, $type)
    {
        try {
            $db = Database::connect();
            $stmt = $db->prepare("DELETE FROM session_tokens WHERE user_id = :id AND user_type = :type");
            $stmt->execute(['id' => $userId, 'type' => $type]);
            
            return true;
        } catch (\Exception $e) {
            error_log("Error al eliminar tokens antiguos: " . $e->getMessage());
            return false;
        }
    }

   private function updateUserActivity($userId, $type)
{
    try {
        error_log("Actualizando actividad de usuario - Usuario ID: $userId, Tipo: $type, Session ID: " . session_id());

        $db = Database::connect();

        // Utilizamos REPLACE INTO para garantizar un solo registro por usuario
        // REPLACE INTO funciona como INSERT, pero si existe un registro con la misma clave primaria,
        // lo elimina y crea uno nuevo (operación UPSERT)

        if ($type === 'admin') {
            $stmt = $db->prepare("REPLACE INTO admin_activity 
                                  (admin_id, tenant_id, ip, user_agent, last_active, created_at) 
                                  VALUES (:id, :tenant_id, :ip, :ua, NOW(), NOW())");
            $params = [
                'id' => $userId,
                'tenant_id' => $_SESSION['admin']['tenant_id'] ?? null,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                'ua' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ];
        } elseif ($type === 'user') {
            $stmt = $db->prepare("REPLACE INTO user_activity 
                                  (user_id, tenant_id, ip, user_agent, last_active, created_at) 
                                  VALUES (:id, :tenant_id, :ip, :ua, NOW(), NOW())");
            $params = [
                'id' => $userId,
                'tenant_id' => $_SESSION['user']['tenant_id'] ?? null,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                'ua' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ];
        } elseif ($type === 'super_admin') {
            $stmt = $db->prepare("REPLACE INTO super_admin_activity 
                                  (super_admin_id, ip, user_agent, last_active, created_at) 
                                  VALUES (:id, :ip, :ua, NOW(), NOW())");
            $params = [
                'id' => $userId,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                'ua' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ];
        } else {
            error_log("Tipo de usuario no reconocido en updateUserActivity: $type");
            return false;
        }

        $success = $stmt->execute($params);

        if ($success) {
            error_log("Actividad de usuario actualizada correctamente");
        } else {
            error_log("Error al actualizar actividad de usuario: " . json_encode($stmt->errorInfo()));
        }

        return $success;
    } catch (\Exception $e) {
        error_log("Excepción al actualizar actividad de usuario: " . $e->getMessage());
        return false;
    }
}


    public function forgotPasswordForm()
    {
        return View::renderTenantAdmin('auth.password', [
            'title' => __('auth.forgot_password_title')
        ]);
    }

    public function sendResetLink()
    {
        $email = trim($_POST['email'] ?? '');
        $tenantId = tenant_id();
        $adminPath = '/' . admin_path();

        try {
            $db = Database::connect();

            // Verificar si el email existe en admins (para panel admin) o users
            $stmt = $db->prepare("SELECT id, email, name FROM admins WHERE email = :email AND tenant_id = :tenant_id LIMIT 1");
            $stmt->execute(['email' => $email, 'tenant_id' => $tenantId]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Si no es admin, verificar en users
            if (!$user) {
                $stmt = $db->prepare("SELECT id, email, name FROM users WHERE email = :email AND tenant_id = :tenant_id LIMIT 1");
                $stmt->execute(['email' => $email, 'tenant_id' => $tenantId]);
                $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            }

            // Por seguridad, no revelamos si el email existe o no
            if (!$user) {
                // 🔒 SECURITY: No loguear email completo para prevenir information disclosure
                $emailHash = substr(hash('sha256', $email), 0, 8);
                error_log("Intento de recuperación de contraseña para email no existente (hash: {$emailHash})");
                flash('success', 'Si el correo existe en nuestro sistema, recibirás un enlace de recuperación.');
                header("Location: {$adminPath}/password/forgot");
                exit;
            }

            // Generar token único
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);

            // Eliminar tokens antiguos del mismo email
            $db->prepare("DELETE FROM password_resets WHERE email = :email")
               ->execute(['email' => $email]);

            // Guardar nuevo token
            $db->prepare("INSERT INTO password_resets (email, token, created_at) VALUES (:email, :token, NOW())")
               ->execute(['email' => $email, 'token' => $tokenHash]);

            // Construir URL de recuperación
            $baseUrl = getenv('APP_URL') ?: 'https://' . $_SERVER['HTTP_HOST'];
            $resetUrl = "{$baseUrl}{$adminPath}/password/reset/{$token}";

            // 🔒 SECURITY: No loguear tokens ni URLs completas para prevenir information disclosure
            $emailHash = substr(hash('sha256', $email), 0, 8);
            error_log("Token de recuperación generado para usuario (hash: {$emailHash})");

            // Preparar y enviar email
            $userName = $user['name'] ?? 'Usuario';
            $expiryMinutes = getenv('PASSWORD_RESET_EXPIRY') ?: 60;
            $expiryTime = $expiryMinutes . ' minutos';

            $htmlBody = Mailer::passwordResetTemplate($resetUrl, $userName, $expiryTime);
            $textBody = Mailer::passwordResetTextTemplate($resetUrl, $userName, $expiryTime);

            $sent = Mailer::send(
                $email,
                'Recuperación de Contraseña - ' . (getenv('APP_NAME') ?: 'MuseDock CMS'),
                $htmlBody,
                $textBody
            );

            // 🔒 SECURITY: No loguear email completo para prevenir information disclosure
            if ($sent) {
                error_log("Email de recuperación enviado exitosamente (hash: {$emailHash})");
            } else {
                error_log("⚠️ ADVERTENCIA: No se pudo enviar el email de recuperación (hash: {$emailHash})");
            }

            flash('success', 'Si el correo existe en nuestro sistema, recibirás un enlace de recuperación en tu bandeja de entrada.');
            header("Location: {$adminPath}/password/forgot");
            exit;

        } catch (\Exception $e) {
            error_log("Error al procesar recuperación de contraseña: " . $e->getMessage());
            flash('error', 'Hubo un problema al procesar tu solicitud. Por favor, intenta de nuevo.');
            header("Location: {$adminPath}/password/forgot");
            exit;
        }
    }

    /**
     * 🔒 SECURITY: Primera visita con token - guardar en sesión y redirigir
     * Esto evita que el token quede en logs/historial del navegador
     */
    public function resetPasswordForm($token)
    {
        $adminPath = '/' . admin_path();

        // Guardar token en sesión de forma segura
        $_SESSION['password_reset_token'] = $token;
        $_SESSION['password_reset_token_time'] = time();

        // Redirigir a URL sin token
        header("Location: {$adminPath}/password/reset");
        exit;
    }

    /**
     * 🔒 SECURITY: Mostrar formulario sin token en URL
     */
    public function resetPasswordFormSecure()
    {
        $adminPath = '/' . admin_path();

        // Verificar que hay token en sesión
        if (!isset($_SESSION['password_reset_token'])) {
            flash('error', 'Token no válido. Por favor, solicita un nuevo enlace.');
            header("Location: {$adminPath}/password/forgot");
            exit;
        }

        // Verificar que el token no sea muy antiguo (máximo 5 minutos en sesión)
        $tokenAge = time() - ($_SESSION['password_reset_token_time'] ?? 0);
        if ($tokenAge > 300) { // 5 minutos
            unset($_SESSION['password_reset_token']);
            unset($_SESSION['password_reset_token_time']);
            flash('error', 'La sesión ha expirado. Por favor, usa el enlace del email nuevamente.');
            header("Location: {$adminPath}/password/forgot");
            exit;
        }

        return View::renderTenantAdmin('auth.reset', [
            'title' => 'Nueva contraseña'
        ]);
    }

    /**
     * 🔒 SECURITY: Procesar reset usando token de sesión (no de URL)
     */
    public function processPasswordReset()
    {
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirmation'] ?? '';
        $adminPath = '/' . admin_path();
        $tenantId = tenant_id();

        // Obtener token de sesión
        $token = $_SESSION['password_reset_token'] ?? null;

        if (!$token) {
            flash('error', 'Token no válido. Por favor, solicita un nuevo enlace.');
            header("Location: {$adminPath}/password/forgot");
            exit;
        }

        try {
            // Validar contraseñas
            if (empty($password) || empty($confirm)) {
                flash('error', 'Todos los campos son obligatorios.');
                header("Location: {$adminPath}/password/reset");
                exit;
            }

            if ($password !== $confirm) {
                flash('error', 'Las contraseñas no coinciden.');
                header("Location: {$adminPath}/password/reset");
                exit;
            }

            // Validar longitud mínima
            if (strlen($password) < 6) {
                flash('error', 'La contraseña debe tener al menos 6 caracteres.');
                header("Location: {$adminPath}/password/reset");
                exit;
            }

            $tokenHash = hash('sha256', $token);
            $db = Database::connect();

            // Verificar token con expiración
            $expiryMinutes = getenv('PASSWORD_RESET_EXPIRY') ?: 60;

            $stmt = $db->prepare("
                SELECT * FROM password_resets
                WHERE token = :token
                AND created_at > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
                LIMIT 1
            ");
            $stmt->execute(['token' => $tokenHash, 'minutes' => $expiryMinutes]);
            $reset = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$reset) {
                // 🔒 SECURITY: No loguear tokens completos
                error_log("Password reset fallido: token inválido o expirado");
                flash('error', 'El enlace ha expirado o no es válido. Por favor, solicita uno nuevo.');
                header("Location: {$adminPath}/password/forgot");
                exit;
            }

            $email = $reset['email'];
            $newHash = password_hash($password, PASSWORD_DEFAULT);

            // 🔒 SECURITY: Hash email antes de loguear
            $emailHash = substr(hash('sha256', $email), 0, 8);

            // Verificar si es admin o user y actualizar correspondiente
            $stmt = $db->prepare("SELECT COUNT(*) FROM admins WHERE email = :email AND tenant_id = :tenant_id");
            $stmt->execute(['email' => $email, 'tenant_id' => $tenantId]);
            $isAdmin = $stmt->fetchColumn() > 0;

            if ($isAdmin) {
                $db->prepare("UPDATE admins SET password = :password WHERE email = :email AND tenant_id = :tenant_id")
                   ->execute(['password' => $newHash, 'email' => $email, 'tenant_id' => $tenantId]);
                // 🔒 SECURITY: No loguear email completo
                error_log("Contraseña actualizada para admin (hash: {$emailHash})");
            } else {
                $db->prepare("UPDATE users SET password = :password WHERE email = :email AND tenant_id = :tenant_id")
                   ->execute(['password' => $newHash, 'email' => $email, 'tenant_id' => $tenantId]);
                // 🔒 SECURITY: No loguear email completo
                error_log("Contraseña actualizada para user (hash: {$emailHash})");
            }

            // Eliminar token usado
            $db->prepare("DELETE FROM password_resets WHERE email = :email")
               ->execute(['email' => $email]);

            // 🔒 SECURITY: Limpiar token de sesión
            unset($_SESSION['password_reset_token']);
            unset($_SESSION['password_reset_token_time']);

            flash('success', 'Contraseña actualizada correctamente. Ahora puedes iniciar sesión con tu nueva contraseña.');
            header("Location: {$adminPath}/login");
            exit;

        } catch (\Exception $e) {
            error_log("Error al procesar reset de contraseña: " . $e->getMessage());
            flash('error', 'Hubo un problema al actualizar tu contraseña. Por favor, intenta de nuevo.');
            // 🔒 SECURITY: Redirigir sin token en URL
            header("Location: {$adminPath}/password/reset");
            exit;
        }
    }

    public function registerForm()
    {
        SessionSecurity::startSession();
        return View::renderTenantAdmin('auth.register', [
            'title' => __('register_title')
        ]);
    }

    public function register()
    {
        SessionSecurity::startSession();

        // Recibir los datos del formulario
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirmation = $_POST['password_confirmation'] ?? '';
        $terms = isset($_POST['terms']);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $tenantId = tenant_id();
        $adminPath = '/' . admin_path();

        // 🔒 SECURITY: Hash email antes de loguear para prevenir information disclosure
        $emailHash = substr(hash('sha256', $email), 0, 8);
        error_log("Intento de registro (tenant: $tenantId, email hash: {$emailHash})");

        // Validaciones
        if (!$name || !$email || !$password || !$password_confirmation || !$terms) {
            // 🔒 SECURITY: NUNCA loguear passwords, incluso si están vacíos
            error_log("Registro fallido: campos incompletos (email hash: {$emailHash})");
            flash('error', 'Todos los campos son obligatorios.');
            header("Location: {$adminPath}/register");
            exit;
        }

        // Validar correo electrónico
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // 🔒 SECURITY: No loguear email completo
            error_log("Registro fallido: email no válido (hash: {$emailHash})");
            flash('error', 'El correo electrónico no es válido.');
            header("Location: {$adminPath}/register");
            exit;
        }

        // Validar contraseñas
        if ($password !== $password_confirmation) {
            // 🔒 SECURITY: No loguear email completo
            error_log("Registro fallido: contraseñas no coinciden (hash: {$emailHash})");
            flash('error', 'Las contraseñas no coinciden.');
            header("Location: {$adminPath}/register");
            exit;
        }

        // Verificar si el correo ya está registrado usando el QueryBuilder de Database
        $existing = Database::table('users')  // Usamos el QueryBuilder de Database
            ->where('email', '=', $email)
            ->where('tenant_id', '=', $tenantId)
            ->first();

        if ($existing) {
            // 🔒 SECURITY: No loguear email completo
            error_log("Registro fallido: email ya registrado (hash: {$emailHash}, tenant: $tenantId)");
            flash('error', 'Ya existe un usuario con ese correo.');
            header("Location: {$adminPath}/register");
            exit;
        }

        try {
            // Registrar el nuevo usuario
            $success = User::createUser([
                'name'         => $name,
                'email'        => $email,
                'password'     => password_hash($password, PASSWORD_DEFAULT),
                'tenant_id'    => $tenantId,
                'registered_ip'=> $ip,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);

            // Verificación de éxito
            if ($success) {
                // 🔒 SECURITY: No loguear email completo
                error_log("Usuario registrado exitosamente (hash: {$emailHash})");
                flash('success', 'Usuario creado correctamente. Ahora puedes iniciar sesión.');
                header("Location: {$adminPath}/login");
            } else {
                // 🔒 SECURITY: No loguear email completo
                error_log("No se pudo crear el usuario (hash: {$emailHash})");
                throw new \Exception("No se pudo crear el usuario");
            }
            exit;

        } catch (\Exception $e) {
            error_log("Error al registrar usuario: " . $e->getMessage());
            flash('error', 'Hubo un problema al crear tu cuenta.');
            header("Location: {$adminPath}/register");
            exit;
        }
    }
}

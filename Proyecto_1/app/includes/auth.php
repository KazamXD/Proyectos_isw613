
<?php

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/remember.php';

// Comprobar si hay un usuario autenticado
function estaAutenticado(): bool
{
    return isset($_SESSION['usuario_id']);
}

//Ver si el usuario esta activo usando la aplicacion
function verificarUsuarioActivo(PDO $conn): void
{
    if (!estaAutenticado()) {
        return;
    }

    $stmt = $conn->prepare("
        SELECT rol_id
        FROM usuarios
        WHERE id = :id
          AND desactivado_en IS NULL
        LIMIT 1
    ");

    $stmt->execute([
        'id' => (int) $_SESSION['usuario_id']
    ]);

    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    // Si el usuario fue desactivado o eliminado
    if (!$usuario) {
        cerrarSesionActual();
        header('Location: /Proyecto_1/app/public/index.php');
        exit;
    }

    // Sincronizar el rol con MariaDB
    $_SESSION['rol_id'] = (int) $usuario['rol_id'];
}

// Exigir que el usuario haya iniciado sesión
function requerirLogin(): void
{
    global $conn;

    // Intentar recuperar la sesión mediante "Recordarme"
    if (!estaAutenticado()) {
        recuperarRemember($conn);
    }

    // Si no se pudo recuperar, enviar al login
    if (!estaAutenticado()) {
        header('Location: /Proyecto_1/app/public/index.php');
        exit;
    }

    // Comprobar que la cuenta siga activa
    verificarUsuarioActivo($conn);
}

function cerrarSesionActual(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax'
        ]);
    }

    session_destroy();
}

// Comprobar si tiene un rol específico
function tieneRol(int $rolId): bool
{
    return estaAutenticado()
        && (int) ($_SESSION['rol_id'] ?? 0) === $rolId;
}

// Exigir un rol para acceder
function requerirRol(int $rolId): void
{
    requerirLogin();

    if (!tieneRol($rolId)) {
        http_response_code(403);
        exit('Acceso denegado: permisos insuficientes.');
    }
}

//Funcion para comprobar si el usuario tiene un permiso específico
function tienePermiso(PDO $conn, string $clave): bool
{
    if (!estaAutenticado()) {
        return false;
    }

    $stmt = $conn->prepare("
        SELECT 1
        FROM rol_permiso rp
        INNER JOIN permisos p
            ON rp.permiso_id = p.id_permiso
        WHERE rp.rol_id = :rol_id
          AND p.clave = :clave
        LIMIT 1
    ");

    $stmt->execute([
        'rol_id' => $_SESSION['rol_id'],
        'clave' => $clave
    ]);

    return (bool) $stmt->fetchColumn();
}

// Funcion para negar funciones a usuarios con un rol específico

function requerirPermiso(PDO $conn, string $clave): void
{
    requerirLogin();

    // Verifica la cuenta antes de comprobar permisos
    verificarUsuarioActivo($conn);

    if (!tienePermiso($conn, $clave)) {
        http_response_code(403);
        exit('Acceso denegado: no tienes este permiso.');
    }
}


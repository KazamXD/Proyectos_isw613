
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Comprobar si hay un usuario autenticado
function estaAutenticado(): bool
{
    return isset($_SESSION['usuario_id']);
}

// Exigir que el usuario haya iniciado sesión
function requerirLogin(): void
{
    if (!estaAutenticado()) {
        header('Location: ../public/index.php');
        exit;
    }
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

    if (!tienePermiso($conn, $clave)) {
        http_response_code(403);
        exit('Acceso denegado: no tienes este permiso.');
    }
}


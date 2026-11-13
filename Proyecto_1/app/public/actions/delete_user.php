<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/bitacora.php';

requerirPermiso($conn, 'usuarios.eliminar');

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}

// Validar CSRF
$token = $_POST['csrf_token'] ?? '';

if (!is_string($token) || !validarTokenCSRF($token)) {
    http_response_code(403);
    exit('Solicitud no autorizada.');
}

// Obtener el ID del usuario
$usuarioId = filter_var(
    $_POST['usuario_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$usuarioId || $usuarioId <= 0) {
    http_response_code(400);
    exit('Usuario no válido.');
}

// Evitar que el administrador se desactive a sí mismo
if ($usuarioId === (int) $_SESSION['usuario_id']) {
    header('Location: ../pages/users.php?error=propio');
    exit;
}

try {
    $conn->beginTransaction();

    // Obtener y bloquear el usuario
    $stmt = $conn->prepare("
        SELECT
            id,
            nombre,
            apellido,
            email,
            nom_usuario,
            rol_id,
            desactivado_en
        FROM usuarios
        WHERE id = :id
          AND desactivado_en IS NULL
        FOR UPDATE
    ");

    $stmt->execute(['id' => $usuarioId]);

    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        throw new RuntimeException('Usuario no disponible.');
    }

    // Comprobar si tiene préstamos pendientes
    $stmt = $conn->prepare("
        SELECT 1
        FROM prestamos
        WHERE usuario_id = :id
          AND fecha_devolucion IS NULL
          AND estado IN ('prestado', 'retrasado')
        LIMIT 1
    ");

    $stmt->execute(['id' => $usuarioId]);

    if ($stmt->fetchColumn() !== false) {
        $conn->rollBack();

        header('Location: ../pages/users.php?error=prestamos');
        exit;
    }

    // Desactivar usuario (eliminación lógica)
    $stmt = $conn->prepare("
        UPDATE usuarios
        SET desactivado_en = CURRENT_TIMESTAMP
        WHERE id = :id
          AND desactivado_en IS NULL
    ");

    $stmt->execute(['id' => $usuarioId]);

    // Obtener la fecha de desactivación
    $stmt = $conn->prepare("
        SELECT desactivado_en
        FROM usuarios
        WHERE id = :id
    ");

    $stmt->execute(['id' => $usuarioId]);

    $fechaDesactivacion = $stmt->fetchColumn();

    // Registrar DELETE en la bitácora
    registrarBitacora(
        $conn,
        (int) $_SESSION['usuario_id'],
        'DELETE',
        'usuarios',
        $usuarioId,
        [
            'nombre' => $usuario['nombre'],
            'apellido' => $usuario['apellido'],
            'email' => $usuario['email'],
            'nom_usuario' => $usuario['nom_usuario'],
            'rol_id' => (int) $usuario['rol_id'],
            'desactivado_en' => null
        ],
        [
            'desactivado_en' => $fechaDesactivacion
        ]
    );

    $conn->commit();

    header('Location: ../pages/users.php?success=eliminado');
    exit;

} catch (PDOException $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log('Error al desactivar usuario: ' . $e->getMessage());

    header('Location: ../pages/users.php?error=sistema');
    exit;

} catch (RuntimeException $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    header('Location: ../pages/users.php?error=no_disponible');
    exit;

} catch (Throwable $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log('Error al desactivar usuario: ' . $e->getMessage());

    header('Location: ../pages/users.php?error=sistema');
    exit;
}
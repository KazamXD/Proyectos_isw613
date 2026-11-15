<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/bitacora.php';

// Verificar permiso
requerirPermiso($conn, 'solicitudes.cancelar');

// Solo permitir POST
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

// Obtener ID de solicitud
$solicitudId = filter_var(
    $_POST['solicitud_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (
    $solicitudId === false ||
    $solicitudId === null ||
    $solicitudId <= 0
) {
    http_response_code(400);
    exit('Solicitud no válida.');
}

$usuarioId = (int) $_SESSION['usuario_id'];

try {

    $conn->beginTransaction();

    // Consultar y bloquear la solicitud
    $stmt = $conn->prepare("
        SELECT
            id_solicitud,
            usuario_id,
            estado
        FROM solicitudes
        WHERE id_solicitud = :id
        FOR UPDATE
    ");

    $stmt->execute([
        'id' => $solicitudId
    ]);

    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$solicitud) {
        throw new RuntimeException(
            'La solicitud no existe.'
        );
    }

    // Comprobar que pertenece al usuario autenticado
    if ((int) $solicitud['usuario_id'] !== $usuarioId) {
        throw new RuntimeException(
            'No puedes cancelar solicitudes de otros usuarios.'
        );
    }

    // Solo cancelar solicitudes pendientes
    if ($solicitud['estado'] !== 'pendiente') {
        throw new RuntimeException(
            'Solo puedes cancelar solicitudes pendientes.'
        );
    }

    // Cambiar estado a cancelada
    $stmt = $conn->prepare("
        UPDATE solicitudes
        SET estado = 'cancelada'
        WHERE id_solicitud = :id
          AND estado = 'pendiente'
    ");

    $stmt->execute([
        'id' => $solicitudId
    ]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException(
            'No se pudo cancelar la solicitud.'
        );
    }

    // Registrar modificación en bitácora
    registrarBitacora(
        $conn,
        $usuarioId,
        'UPDATE',
        'solicitudes',
        $solicitudId,
        [
            'estado' => 'pendiente'
        ],
        [
            'estado' => 'cancelada'
        ]
    );

    // Confirmar ambas operaciones
    $conn->commit();

    header(
        'Location: ../pages/requests.php?success=cancelada'
    );
    exit;

} catch (RuntimeException $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    http_response_code(409);

    exit(htmlspecialchars(
        $e->getMessage(),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ));

} catch (Throwable $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log(
        'Error al cancelar solicitud: ' . $e->getMessage()
    );

    header(
        'Location: ../pages/requests.php?error=sistema'
    );
    exit;
}
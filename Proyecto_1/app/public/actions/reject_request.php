<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/bitacora.php';

requerirPermiso($conn, 'solicitudes.rechazar');

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

// Obtener identificador
$solicitudId = filter_var(
    $_POST['solicitud_id'] ?? null,
    FILTER_VALIDATE_INT
);

// Obtener motivo
$motivoRecibido = $_POST['motivo_rechazo'] ?? '';

if (
    !$solicitudId ||
    !is_string($motivoRecibido)
) {
    http_response_code(400);
    exit('Datos no válidos.');
}

$motivo = trim($motivoRecibido);

// Validar motivo
if (
    $motivo === '' ||
    mb_strlen($motivo) > 255
) {
    http_response_code(400);
    exit('Debes proporcionar un motivo válido.');
}

$revisorId = (int) $_SESSION['usuario_id'];

try {

    $conn->beginTransaction();

    // Consultar y bloquear la solicitud
    $stmt = $conn->prepare("
        SELECT
            id_solicitud,
            usuario_id,
            estado,
            motivo_rechazo
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

    // Comprobar que siga pendiente
    if ($solicitud['estado'] !== 'pendiente') {
        throw new RuntimeException(
            'Esta solicitud ya fue procesada.'
        );
    }

    // Cambiar el estado
    $stmt = $conn->prepare("
        UPDATE solicitudes
        SET
            estado = 'rechazada',
            revisado_por = :revisor,
            fecha_revision = CURRENT_TIMESTAMP,
            motivo_rechazo = :motivo
        WHERE id_solicitud = :id
          AND estado = 'pendiente'
    ");

    $stmt->execute([
        'revisor' => $revisorId,
        'motivo' => $motivo,
        'id' => $solicitudId
    ]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException(
            'No se pudo rechazar la solicitud.'
        );
    }

    // Registrar UPDATE en bitácora
    registrarBitacora(
        $conn,
        $revisorId,
        'UPDATE',
        'solicitudes',
        $solicitudId,
        [
            'estado' => $solicitud['estado'],
            'motivo_rechazo' => $solicitud['motivo_rechazo']
        ],
        [
            'estado' => 'rechazada',
            'motivo_rechazo' => $motivo,
            'revisado_por' => $revisorId
        ]
    );

    $conn->commit();

    header(
        'Location: ../pages/requests.php?success=rechazada'
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
        'Error al rechazar solicitud: ' . $e->getMessage()
    );

    header(
        'Location: ../pages/requests.php?error=sistema'
    );
    exit;
}
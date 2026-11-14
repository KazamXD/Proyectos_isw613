<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/bitacora.php';
require_once __DIR__ . '/../../includes/loan_restrictions.php';

date_default_timezone_set('America/Costa_Rica');

// Solo usuarios autorizados
requerirPermiso($conn, 'solicitudes.aprobar');

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

// Obtener el ID
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

$revisorId = (int) $_SESSION['usuario_id'];

try {

    $conn->beginTransaction();

    // 1. Obtener y bloquear la solicitud
    $stmt = $conn->prepare("
        SELECT
            id_solicitud,
            usuario_id,
            fecha_inicio,
            fecha_fin,
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

    // 2. Comprobar estado pendiente
    if ($solicitud['estado'] !== 'pendiente') {
        throw new RuntimeException(
            'Esta solicitud ya fue procesada.'
        );
    }

    $usuarioId = (int) $solicitud['usuario_id'];

    // 3. Evitar autoaprobaciones
    if ($usuarioId === $revisorId) {
        throw new RuntimeException(
            'No puedes aprobar tu propia solicitud.'
        );
    }

    // 4. Comprobar que el solicitante siga activo
    $stmt = $conn->prepare("
        SELECT id
        FROM usuarios
        WHERE id = :id
          AND desactivado_en IS NULL
        LIMIT 1
    ");

    $stmt->execute([
        'id' => $usuarioId
    ]);

    if ($stmt->fetchColumn() === false) {
        throw new RuntimeException(
            'El solicitante ya no está activo.'
        );
    }

    // 5. Comprobar préstamos retrasados
    if (tienePrestamosRetrasados($conn, $usuarioId)) {
        throw new RuntimeException(
            'El solicitante tiene préstamos retrasados.'
        );
    }

    // 6. Verificar fechas
    $zona = new DateTimeZone('America/Costa_Rica');

    $fechaInicio = new DateTimeImmutable(
        $solicitud['fecha_inicio'],
        $zona
    );

    $fechaFin = new DateTimeImmutable(
        $solicitud['fecha_fin'],
        $zona
    );

    $ahora = new DateTimeImmutable('now', $zona);

    if (
        $fechaInicio <= $ahora ||
        $fechaFin <= $fechaInicio
    ) {
        throw new RuntimeException(
            'El horario de la solicitud ya no es válido.'
        );
    }

    // 7. Obtener equipos solicitados
    $stmt = $conn->prepare("
        SELECT equipo_id
        FROM solicitud_detalle
        WHERE solicitud_id = :id
        ORDER BY equipo_id
    ");

    $stmt->execute([
        'id' => $solicitudId
    ]);

    $equiposIds = array_map(
        'intval',
        $stmt->fetchAll(PDO::FETCH_COLUMN)
    );

    if (empty($equiposIds)) {
        throw new RuntimeException(
            'La solicitud no contiene equipos.'
        );
    }

    // 8. Bloquear los equipos en orden fijo
    $stmtEquipo = $conn->prepare("
        SELECT
            e.id_equipo,
            e.nombre_estado,
            e.eliminado_en,
            c.eliminado_en AS categoria_eliminada
        FROM equipos e
        INNER JOIN categorias c
            ON e.id_categoria = c.categoria_id
        WHERE e.id_equipo = :id
        FOR UPDATE
    ");
    
    foreach ($equiposIds as $equipoId) {

        $stmtEquipo->execute([
            'id' => $equipoId
        ]);
        $equipo = $stmtEquipo->fetch(PDO::FETCH_ASSOC);
        $stmtEquipo->closeCursor();
        if (
            !$equipo ||
            $equipo['eliminado_en'] !== null ||
            $equipo['categoria_eliminada'] !== null ||
            $equipo['nombre_estado'] === 'Mantenimiento'
        ) {
            throw new RuntimeException(
                'Uno de los equipos no puede reservarse.'
            );
        }
    }

    // 9. Comprobar reservaciones aprobadas superpuestas
    $stmtReserva = $conn->prepare("
        SELECT 1
        FROM solicitudes s
        INNER JOIN solicitud_detalle sd
            ON sd.solicitud_id = s.id_solicitud
        WHERE sd.equipo_id = :equipo_id
          AND s.id_solicitud <> :solicitud_id
          AND s.estado = 'aprobada'
          AND s.fecha_inicio < :fecha_fin
          AND s.fecha_fin > :fecha_inicio
        LIMIT 1
    ");

    // 10. Comprobar préstamos físicos activos
    $stmtPrestamo = $conn->prepare("
        SELECT 1
        FROM prestamos p
        INNER JOIN prestamo_detalle pd
            ON pd.prestamo_id = p.id_prestamo
        WHERE pd.equipo_id = :equipo_id
          AND p.fecha_devolucion IS NULL
          AND p.estado IN ('prestado', 'retrasado')
          AND (
              p.fecha_limite > :fecha_inicio
              OR p.fecha_limite < NOW()
          )
        LIMIT 1
    ");

    foreach ($equiposIds as $equipoId) {

        // Buscar conflictos con otras reservas
        $stmtReserva->execute([
            'equipo_id' => $equipoId,
            'solicitud_id' => $solicitudId,
            'fecha_inicio' => $solicitud['fecha_inicio'],
            'fecha_fin' => $solicitud['fecha_fin']
        ]);

        if ($stmtReserva->fetchColumn() !== false) {
            throw new RuntimeException(
                'Uno de los equipos ya está reservado para ese horario.'
            );
        }
        $stmtReserva->closeCursor();

        // Buscar conflictos con préstamos existentes
        $stmtPrestamo->execute([
            'equipo_id' => $equipoId,
            'fecha_inicio' => $solicitud['fecha_inicio']
        ]);
        if ($stmtPrestamo->fetchColumn() !== false) {
            throw new RuntimeException(
                'Uno de los equipos tiene un préstamo que impide la reserva.'
            );
        }

        $stmtPrestamo->closeCursor();
    }

    // 11. Aprobar la solicitud
    $stmt = $conn->prepare("
        UPDATE solicitudes
        SET
            estado = 'aprobada',
            revisado_por = :revisor,
            fecha_revision = CURRENT_TIMESTAMP
        WHERE id_solicitud = :id
          AND estado = 'pendiente'
    ");

    $stmt->execute([
        'revisor' => $revisorId,
        'id' => $solicitudId
    ]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException(
            'No se pudo aprobar la solicitud.'
        );
    }

    // 12. Registrar en bitácora
    registrarBitacora(
        $conn,
        $revisorId,
        'UPDATE',
        'solicitudes',
        $solicitudId,
        [
            'estado' => 'pendiente'
        ],
        [
            'estado' => 'aprobada',
            'revisado_por' => $revisorId,
            'equipos' => $equiposIds
        ]
    );

    // 13. Confirmar cambios
    $conn->commit();
    header(
        'Location: ../pages/requests.php?success=aprobada'
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
        'Error al aprobar solicitud: ' . $e->getMessage()
    );
    header(
        'Location: ../pages/requests.php?error=sistema'
    );
    exit;
}
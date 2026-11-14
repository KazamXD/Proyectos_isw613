<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/bitacora.php';
require_once __DIR__ . '/../../includes/loan_restrictions.php';

date_default_timezone_set('America/Costa_Rica');

requerirPermiso($conn, 'solicitudes.crear');

// Solo aceptar solicitudes POST
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

// Usuario que realiza la solicitud
$usuarioId = (int) $_SESSION['usuario_id'];

// Recibir equipos y fechas
$equiposRecibidos = $_POST['equipos'] ?? [];
$inicioRecibido = $_POST['fecha_inicio'] ?? '';
$finRecibido = $_POST['fecha_fin'] ?? '';

// Validar que los equipos sean un arreglo
if (
    !is_array($equiposRecibidos) ||
    count($equiposRecibidos) === 0 ||
    count($equiposRecibidos) > 30
) {
    http_response_code(400);
    exit('Debes seleccionar entre 1 y 30 equipos.');
}

// Validar identificadores
$equiposIds = [];

foreach ($equiposRecibidos as $id) {
    $equipoId = filter_var($id, FILTER_VALIDATE_INT);

    if ($equipoId === false || $equipoId === null || $equipoId <= 0) {
        http_response_code(400);
        exit('Se recibió un equipo no válido.');
    }

    $equiposIds[] = $equipoId;
}

// Eliminar duplicados
$equiposIds = array_values(array_unique($equiposIds));
sort($equiposIds, SORT_NUMERIC);

// Validar que las fechas sean cadenas
if (!is_string($inicioRecibido) || !is_string($finRecibido)) {
    http_response_code(400);
    exit('Fechas no válidas.');
}

// Configurar zona horaria
$zona = new DateTimeZone('America/Costa_Rica');

// Convertir fechas recibidas del formulario
$fechaInicio = DateTimeImmutable::createFromFormat(
    '!Y-m-d\TH:i',
    $inicioRecibido,
    $zona
);

$fechaFin = DateTimeImmutable::createFromFormat(
    '!Y-m-d\TH:i',
    $finRecibido,
    $zona
);

// Comprobar que las fechas sean válidas
if (
    !$fechaInicio ||
    !$fechaFin ||
    $fechaInicio->format('Y-m-d\TH:i') !== $inicioRecibido ||
    $fechaFin->format('Y-m-d\TH:i') !== $finRecibido
) {
    http_response_code(400);
    exit('El formato de las fechas no es válido.');
}

// Comprobar el intervalo
$ahora = new DateTimeImmutable('now', $zona);

if ($fechaInicio <= $ahora || $fechaFin <= $fechaInicio) {
    http_response_code(400);
    exit('El intervalo solicitado no es válido.');
}

// Convertir fechas a formato MariaDB
$inicioSQL = $fechaInicio->format('Y-m-d H:i:s');
$finSQL = $fechaFin->format('Y-m-d H:i:s');

try {
    $conn->beginTransaction();

    // Bloquear la fila del solicitante para coordinar sus operaciones
    $stmt = $conn->prepare("
        SELECT id
        FROM usuarios
        WHERE id = :id
          AND desactivado_en IS NULL
        FOR UPDATE
    ");

    $stmt->execute(['id' => $usuarioId]);

    if ($stmt->fetchColumn() === false) {
        throw new RuntimeException('Usuario no disponible.');
    }

    // REQUISITO INDIVIDUAL:
    // impedir solicitudes de usuarios con préstamos retrasados
    if (tienePrestamosRetrasados($conn, $usuarioId)) {
        throw new RuntimeException(
            'No puedes solicitar equipos mientras tengas préstamos retrasados.'
        );
    }

    // Construir marcadores para la consulta IN
    $marcadores = implode(
        ',',
        array_fill(0, count($equiposIds), '?')
    );

    // Consultar los equipos solicitados y sus límites
    $stmt = $conn->prepare("
        SELECT
            e.id_equipo,
            c.dias_max_prestamo
        FROM equipos e
        INNER JOIN categorias c
            ON e.id_categoria = c.categoria_id
        WHERE e.id_equipo IN ($marcadores)
          AND e.eliminado_en IS NULL
          AND c.eliminado_en IS NULL
          AND e.nombre_estado <> 'Mantenimiento'
        ORDER BY e.id_equipo
        FOR UPDATE
    ");

    $stmt->execute($equiposIds);

    $equiposValidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($equiposValidos) !== count($equiposIds)) {
        throw new RuntimeException(
            'Uno de los equipos seleccionados no está disponible.'
        );
    }

    // Obtener el límite más restrictivo
    $diasMaximos = min(
        array_map(
            fn($equipo) => (int) $equipo['dias_max_prestamo'],
            $equiposValidos
        )
    );

    if ($diasMaximos < 1) {
        throw new RuntimeException('Límite de préstamo no válido.');
    }

    // Comprobar la duración desde la fecha de inicio solicitada
    $fechaMaxima = $fechaInicio->modify(
        '+' . $diasMaximos . ' days'
    );

    if ($fechaFin > $fechaMaxima) {
        throw new RuntimeException(
            'La solicitud supera el máximo permitido de ' .
            $diasMaximos . ' días.'
        );
    }

    // Insertar la solicitud principal
    $stmt = $conn->prepare("
        INSERT INTO solicitudes (
            usuario_id,
            fecha_inicio,
            fecha_fin,
            estado
        )
        VALUES (
            :usuario_id,
            :inicio,
            :fin,
            'pendiente'
        )
    ");

    $stmt->execute([
        'usuario_id' => $usuarioId,
        'inicio' => $inicioSQL,
        'fin' => $finSQL
    ]);

    $solicitudId = (int) $conn->lastInsertId();

    // Insertar los equipos solicitados
    $stmtDetalle = $conn->prepare("
        INSERT INTO solicitud_detalle (
            solicitud_id,
            equipo_id
        )
        VALUES (
            :solicitud_id,
            :equipo_id
        )
    ");

    foreach ($equiposIds as $equipoId) {
        $stmtDetalle->execute([
            'solicitud_id' => $solicitudId,
            'equipo_id' => $equipoId
        ]);
    }

    // Registrar CREATE en la bitácora
    registrarBitacora(
        $conn,
        $usuarioId,
        'CREATE',
        'solicitudes',
        $solicitudId,
        null,
        [
            'usuario_id' => $usuarioId,
            'fecha_inicio' => $inicioSQL,
            'fecha_fin' => $finSQL,
            'equipos' => $equiposIds,
            'estado' => 'pendiente'
        ]
    );

    $conn->commit();

    header('Location: ../pages/request_form.php?success=creada');
    exit;

} catch (PDOException $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log('Error al crear solicitud: ' . $e->getMessage());

    header('Location: ../pages/request_form.php?error=sistema');
    exit;

} catch (RuntimeException $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    http_response_code(400);
    exit(htmlspecialchars(
        $e->getMessage(),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ));

} catch (Throwable $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log('Error al crear solicitud: ' . $e->getMessage());

    header('Location: ../pages/request_form.php?error=sistema');
    exit;
}
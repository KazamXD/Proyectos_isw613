<?php
date_default_timezone_set('America/Costa_Rica');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/bitacora.php';

requerirPermiso($conn, 'prestamos.crear');

// Solo aceptar solicitudes POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}

// Validar token CSRF
$token = $_POST['csrf_token'] ?? '';

if (!is_string($token) || !validarTokenCSRF($token)) {
    http_response_code(403);
    exit('Solicitud no autorizada.');
}

// Recibir datos del formulario
$usuarioId = filter_var(
    $_POST['usuario_id'] ?? null,
    FILTER_VALIDATE_INT
);

$equiposIds = $_POST['equipos'] ?? [];
$fechaLimite = $_POST['fecha_limite'] ?? '';

$operadorId = (int) $_SESSION['usuario_id'];

// Validar usuario
if ($usuarioId === false || $usuarioId === null || $usuarioId <= 0) {
    exit('Usuario no válido.');
}

// Validar equipos
if (!is_array($equiposIds) || count($equiposIds) === 0) {
    exit('Debes seleccionar al menos un equipo.');
}

$idsValidados = [];

foreach ($equiposIds as $id) {
    $idValidado = filter_var($id, FILTER_VALIDATE_INT);

    if ($idValidado === false || $idValidado === null || $idValidado <= 0) {
        exit('Se recibió un equipo no válido.');
    }

    $idsValidados[] = $idValidado;
}

// Evitar equipos repetidos y mantener un orden fijo
$idsValidados = array_values(array_unique($idsValidados));
sort($idsValidados, SORT_NUMERIC);

// Validar fecha
// Validar fecha y hora límite
if (!is_string($fechaLimite)) {
    exit('Fecha y hora no válidas.');
}

$fecha = DateTimeImmutable::createFromFormat(
    '!Y-m-d\TH:i',
    $fechaLimite
);

$erroresFecha = DateTimeImmutable::getLastErrors();

if (
    $fecha === false ||
    ($erroresFecha !== false &&
        ($erroresFecha['warning_count'] > 0 ||
         $erroresFecha['error_count'] > 0)) ||
    $fecha->format('Y-m-d\TH:i') !== $fechaLimite ||
    $fecha <= new DateTimeImmutable()
) {
    exit('La fecha y hora límite deben ser posteriores al momento actual.');
}

// Convertir al formato DATETIME de MariaDB
$fechaLimite = $fecha->format('Y-m-d H:i:s');

try {
    $conn->beginTransaction();

    // Verificar que el usuario exista y esté activo
    $stmt = $conn->prepare("
        SELECT id
        FROM usuarios
        WHERE id = :usuario_id
          AND desactivado_en IS NULL
    ");

    $stmt->execute(['usuario_id' => $usuarioId]);

    if (!$stmt->fetchColumn()) {
        throw new RuntimeException('El usuario no está disponible.');
    }

    // Crear marcadores para la lista de equipos
    $marcadores = implode(
        ',',
        array_fill(0, count($idsValidados), '?')
    );

    // Bloquear los equipos seleccionados durante la transacción
    $stmt = $conn->prepare("
        SELECT id_equipo
        FROM equipos
        WHERE id_equipo IN ($marcadores)
          AND eliminado_en IS NULL
          AND nombre_estado = 'Disponible'
        ORDER BY id_equipo
        FOR UPDATE
    ");

    $stmt->execute($idsValidados);
    $equiposDisponibles = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($equiposDisponibles) !== count($idsValidados)) {
        throw new RuntimeException(
            'Uno de los equipos ya no está disponible.'
        );
    }

    // Verificar que ningún equipo tenga un préstamo activo
    $stmt = $conn->prepare("
        SELECT pd.equipo_id
        FROM prestamo_detalle pd
        INNER JOIN prestamos p
            ON pd.prestamo_id = p.id_prestamo
        WHERE pd.equipo_id IN ($marcadores)
          AND p.estado IN ('prestado', 'retrasado')
          AND p.fecha_devolucion IS NULL
        LIMIT 1
    ");

    $stmt->execute($idsValidados);

    if ($stmt->fetchColumn() !== false) {
        throw new RuntimeException(
            'Uno de los equipos ya tiene un préstamo activo.'
        );
    }

    // Insertar préstamo principal
    $stmt = $conn->prepare("
        INSERT INTO prestamos (
            usuario_id,
            operador_id,
            fecha_limite,
            estado
        )
        VALUES (
            :usuario_id,
            :operador_id,
            :fecha_limite,
            'prestado'
        )
    ");

    $stmt->execute([
        'usuario_id' => $usuarioId,
        'operador_id' => $operadorId,
        'fecha_limite' => $fechaLimite
    ]);

    $prestamoId = (int) $conn->lastInsertId();

    // Insertar los equipos asociados al préstamo
    $stmtDetalle = $conn->prepare("
        INSERT INTO prestamo_detalle (
            prestamo_id,
            equipo_id,
            cantidad
        )
        VALUES (
            :prestamo_id,
            :equipo_id,
            1
        )
    ");

    // Actualizar el estado de los equipos
    $stmtEstado = $conn->prepare("
        UPDATE equipos
        SET nombre_estado = 'Prestado'
        WHERE id_equipo = :equipo_id
    ");

    foreach ($idsValidados as $equipoId) {
        $stmtDetalle->execute([
            'prestamo_id' => $prestamoId,
            'equipo_id' => $equipoId
        ]);

        $stmtEstado->execute([
            'equipo_id' => $equipoId
        ]);
    }

    registrarBitacora(
        $conn,
        $operadorId,
        'CREATE',
        'prestamos',
        $prestamoId,
        null,
        [
            'usuario_id' => $usuarioId,
            'operador_id' => $operadorId,
            'fecha_limite' => $fechaLimite,
            'equipos' => $idsValidados,
            'estado' => 'prestado'
        ]
    );

    // Confirmar todos los cambios
    $conn->commit();

    header('Location: ../pages/loans.php?creado=1');
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

    error_log('Error al crear préstamo: ' . $e->getMessage());

    http_response_code(500);
    exit('No se pudo registrar el préstamo.');
}
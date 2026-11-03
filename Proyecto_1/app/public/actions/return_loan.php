<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/bitacora.php';

date_default_timezone_set('America/Costa_Rica');

requerirPermiso($conn, 'prestamos.devolver');

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

// Validar ID del préstamo
$prestamoId = filter_var(
    $_POST['prestamo_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (
    $prestamoId === false ||
    $prestamoId === null ||
    $prestamoId <= 0
) {
    http_response_code(400);
    exit('Préstamo no válido.');
}

try {
    $conn->beginTransaction();

    // Buscar el préstamo y bloquearlo mientras se procesa
    $stmt = $conn->prepare("
        SELECT
            id_prestamo,
            usuario_id,
            estado,
            fecha_devolucion
        FROM prestamos
        WHERE id_prestamo = :id
        FOR UPDATE
    ");

    $stmt->execute(['id' => $prestamoId]);

    $prestamo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prestamo) {
        throw new RuntimeException('El préstamo no existe.');
    }

    // Evitar devoluciones duplicadas
    if (
        $prestamo['estado'] === 'devuelto' ||
        $prestamo['fecha_devolucion'] !== null
    ) {
        throw new RuntimeException('Este préstamo ya fue devuelto.');
    }

    // Obtener los equipos asociados
    $stmt = $conn->prepare("
        SELECT equipo_id
        FROM prestamo_detalle
        WHERE prestamo_id = :prestamo_id
        ORDER BY equipo_id
    ");

    $stmt->execute(['prestamo_id' => $prestamoId]);

    $equiposIds = array_map(
        'intval',
        $stmt->fetchAll(PDO::FETCH_COLUMN)
    );

    // Registrar la devolución
    $fechaDevolucion = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("
        UPDATE prestamos
        SET
            estado = 'devuelto',
            fecha_devolucion = :fecha_devolucion
        WHERE id_prestamo = :prestamo_id
    ");

    $stmt->execute([
        'fecha_devolucion' => $fechaDevolucion,
        'prestamo_id' => $prestamoId
    ]);

    // Liberar los equipos que no tengan otros préstamos activos
    $stmtEstado = $conn->prepare("
        UPDATE equipos e
        SET e.nombre_estado = 'Disponible'
        WHERE e.id_equipo = :equipo_id
          AND e.nombre_estado = 'Prestado'
          AND NOT EXISTS (
              SELECT 1
              FROM prestamo_detalle pd
              INNER JOIN prestamos p
                  ON pd.prestamo_id = p.id_prestamo
              WHERE pd.equipo_id = e.id_equipo
                AND p.estado IN ('prestado', 'retrasado')
                AND p.fecha_devolucion IS NULL
          )
    ");

    foreach ($equiposIds as $equipoId) {
        $stmtEstado->execute([
            'equipo_id' => $equipoId
        ]);
    }

    // Registrar la operación en la bitácora
    registrarBitacora(
        $conn,
        (int) $_SESSION['usuario_id'],
        'UPDATE',
        'prestamos',
        $prestamoId,
        [
            'estado' => $prestamo['estado'],
            'fecha_devolucion' => $prestamo['fecha_devolucion']
        ],
        [
            'estado' => 'devuelto',
            'fecha_devolucion' => $fechaDevolucion,
            'equipos' => $equiposIds
        ]
    );

    // Confirmar todos los cambios
    $conn->commit();

    header('Location: ../pages/loans.php?devuelto=1');
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

    error_log('Error al devolver préstamo: ' . $e->getMessage());

    http_response_code(500);
    exit('No se pudo registrar la devolución.');
}
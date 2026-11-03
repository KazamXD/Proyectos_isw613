<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/bitacora.php';

requerirPermiso($conn, 'equipos.eliminar');

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

// Validar el ID del equipo
$equipoId = filter_var(
    $_POST['equipo_id'] ?? null,
    FILTER_VALIDATE_INT
);

if ($equipoId === false || $equipoId === null || $equipoId <= 0) {
    http_response_code(400);
    exit('Equipo no válido.');
}

try {
    $conn->beginTransaction();

    // Obtener el equipo y bloquearlo durante la operación
    $stmt = $conn->prepare("
        SELECT
            id_equipo,
            codigo,
            id_categoria,
            nombre_estado,
            eliminado_en
        FROM equipos
        WHERE id_equipo = :id
          AND eliminado_en IS NULL
        FOR UPDATE
    ");

    $stmt->execute(['id' => $equipoId]);
    $equipo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$equipo) {
        throw new RuntimeException('El equipo no existe o ya fue eliminado.');
    }

    // Comprobar que no tenga préstamos activos
    $stmt = $conn->prepare("
        SELECT 1
        FROM prestamo_detalle pd
        INNER JOIN prestamos p
            ON pd.prestamo_id = p.id_prestamo
        WHERE pd.equipo_id = :id
          AND p.estado IN ('prestado', 'retrasado')
          AND p.fecha_devolucion IS NULL
        LIMIT 1
    ");

    $stmt->execute(['id' => $equipoId]);

    if ($stmt->fetchColumn() !== false) {
        throw new RuntimeException(
            'No se puede eliminar un equipo que tiene un préstamo activo.'
        );
    }

    // Eliminar lógicamente
    $stmt = $conn->prepare("
        UPDATE equipos
        SET eliminado_en = CURRENT_TIMESTAMP
        WHERE id_equipo = :id
          AND eliminado_en IS NULL
    ");

    $stmt->execute(['id' => $equipoId]);

    // Obtener la fecha que MariaDB almacenó
    $stmt = $conn->prepare("
        SELECT eliminado_en
        FROM equipos
        WHERE id_equipo = :id
    ");

    $stmt->execute(['id' => $equipoId]);
    $fechaEliminacion = $stmt->fetchColumn();

    // Registrar la eliminación en bitácora
    registrarBitacora(
        $conn,
        (int) $_SESSION['usuario_id'],
        'DELETE',
        'equipos',
        $equipoId,
        [
            'codigo' => $equipo['codigo'],
            'id_categoria' => (int) $equipo['id_categoria'],
            'nombre_estado' => $equipo['nombre_estado'],
            'eliminado_en' => null
        ],
        [
            'codigo' => $equipo['codigo'],
            'eliminado_en' => $fechaEliminacion
        ]
    );

    $conn->commit();

    header('Location: ../pages/equipment.php?success=eliminado');
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

    error_log('Error al eliminar equipo: ' . $e->getMessage());

    http_response_code(500);
    exit('No se pudo eliminar el equipo.');
}
<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/bitacora.php';

// Comprobar permiso
requerirPermiso($conn, 'categorias.eliminar');

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

// Obtener ID de la categoría
$categoriaId = filter_var(
    $_POST['categoria_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (
    $categoriaId === false ||
    $categoriaId === null ||
    $categoriaId <= 0
) {
    http_response_code(400);
    exit('Categoría no válida.');
  }

try {
    $conn->beginTransaction();

    // Obtener y bloquear la categoría
    $stmt = $conn->prepare("
        SELECT
            categoria_id,
            categoria_nombre,
            dias_max_prestamo,
            eliminado_en
        FROM categorias
        WHERE categoria_id = :id
          AND eliminado_en IS NULL
        FOR UPDATE
    ");
    $stmt->execute(['id' => $categoriaId]);
    $categoria = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$categoria) {
        throw new RuntimeException(
            'La categoría no existe o ya fue eliminada.'
        );
    }

    // Verificar si tiene equipos activos
    $stmt = $conn->prepare("
        SELECT 1
        FROM equipos
        WHERE id_categoria = :id
          AND eliminado_en IS NULL
        LIMIT 1
    ");
    $stmt->execute(['id' => $categoriaId]);

    if ($stmt->fetchColumn() !== false) {
        throw new RuntimeException(
            'No se puede eliminar una categoría con equipos activos.'
        );
    }

    // Realizar eliminación lógica
    $stmt = $conn->prepare("
        UPDATE categorias
        SET eliminado_en = CURRENT_TIMESTAMP
        WHERE categoria_id = :id
          AND eliminado_en IS NULL
    ");
    $stmt->execute(['id' => $categoriaId]);
    // Obtener la fecha de eliminación
    $stmt = $conn->prepare("
        SELECT eliminado_en
        FROM categorias
        WHERE categoria_id = :id
    ");

    $stmt->execute(['id' => $categoriaId]);
    $fechaEliminacion = $stmt->fetchColumn();

    // Registrar DELETE en bitácora
    registrarBitacora(
        $conn,
        (int) $_SESSION['usuario_id'],
        'DELETE',
        'categorias',
        $categoriaId,
        [
            'categoria_nombre' => $categoria['categoria_nombre'],
            'dias_max_prestamo' => (int) $categoria['dias_max_prestamo'],
            'eliminado_en' => null
        ],
        [
            'categoria_nombre' => $categoria['categoria_nombre'],
            'dias_max_prestamo' => (int) $categoria['dias_max_prestamo'],
            'eliminado_en' => $fechaEliminacion
        ]
    );
    $conn->commit();
    header('Location: ../pages/categories.php?success=eliminado');
    exit;

} catch (RuntimeException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    // Error de regla de negocio
    header('Location: ../pages/categories.php?error=asociada');
    exit;

} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log(
        'Error al eliminar categoría: ' . $e->getMessage()
    );
    header('Location: ../pages/categories.php?error=sistema');
    exit;
}
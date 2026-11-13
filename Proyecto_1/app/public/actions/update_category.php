<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/bitacora.php';

requerirPermiso($conn, 'categorias.editar');

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

// Obtener datos
$categoriaId = filter_var(
    $_POST['categoria_id'] ?? null,
    FILTER_VALIDATE_INT
);

$nombreRecibido = $_POST['categoria_nombre'] ?? '';

$diasMaximos = filter_var(
    $_POST['dias_max_prestamo'] ?? null,
    FILTER_VALIDATE_INT
);

// Validar datos recibidos
if (
    !$categoriaId ||
    !is_string($nombreRecibido) ||
    $diasMaximos === false ||
    $diasMaximos === null
) {
    http_response_code(400);
    exit('Datos no válidos.');
}

$nombre = trim($nombreRecibido);

if (
    $nombre === '' ||
    mb_strlen($nombre) > 50 ||
    $diasMaximos < 1 ||
    $diasMaximos > 365
) {
    header(
        'Location: ../pages/edit_category.php?id=' .
        $categoriaId .
        '&error=campos'
    );
    exit;
}

try {
    $conn->beginTransaction();

    // Consultar categoría y bloquear el registro
    $stmt = $conn->prepare("
        SELECT
            categoria_id,
            categoria_nombre,
            dias_max_prestamo
        FROM categorias
        WHERE categoria_id = :id
          AND eliminado_en IS NULL
        FOR UPDATE
    ");

    $stmt->execute(['id' => $categoriaId]);

    $categoriaAnterior = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$categoriaAnterior) {
        throw new RuntimeException('La categoría no existe.');
    }

    // Comprobar nombre duplicado en otra categoría
    $stmt = $conn->prepare("
        SELECT categoria_id
        FROM categorias
        WHERE categoria_nombre = :nombre
          AND categoria_id <> :id
        LIMIT 1
    ");

    $stmt->execute([
        'nombre' => $nombre,
        'id' => $categoriaId
    ]);

    if ($stmt->fetchColumn() !== false) {
        $conn->rollBack();

        header(
            'Location: ../pages/edit_category.php?id=' .
            $categoriaId .
            '&error=duplicado'
        );
        exit;
    }

    // Datos anteriores
    $valoresAntes = [
        'categoria_nombre' => $categoriaAnterior['categoria_nombre'],
        'dias_max_prestamo' => (int) $categoriaAnterior['dias_max_prestamo']
    ];

    // Datos nuevos
    $valoresDespues = [
        'categoria_nombre' => $nombre,
        'dias_max_prestamo' => $diasMaximos
    ];

    // No registrar cambios ficticios
    if ($valoresAntes === $valoresDespues) {
        $conn->commit();

        header('Location: ../pages/categories.php?success=sin_cambios');
        exit;
    }

    // Actualizar categoría
    $stmt = $conn->prepare("
        UPDATE categorias
        SET
            categoria_nombre = :nombre,
            dias_max_prestamo = :dias
        WHERE categoria_id = :id
          AND eliminado_en IS NULL
    ");

    $stmt->execute([
        'nombre' => $nombre,
        'dias' => $diasMaximos,
        'id' => $categoriaId
    ]);

    // Registrar UPDATE en bitácora
    registrarBitacora(
        $conn,
        (int) $_SESSION['usuario_id'],
        'UPDATE',
        'categorias',
        $categoriaId,
        $valoresAntes,
        $valoresDespues
    );

    $conn->commit();

    header('Location: ../pages/categories.php?success=editado');
    exit;

} catch (PDOException $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log('Error al editar categoría: ' . $e->getMessage());

    // Por si existe una restricción UNIQUE
    if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
        header(
            'Location: ../pages/edit_category.php?id=' .
            $categoriaId .
            '&error=duplicado'
        );
        exit;
    }

    header(
        'Location: ../pages/edit_category.php?id=' .
        $categoriaId .
        '&error=sistema'
    );
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

    error_log('Error al editar categoría: ' . $e->getMessage());

    http_response_code(500);
    exit('No se pudo actualizar la categoría.');
}
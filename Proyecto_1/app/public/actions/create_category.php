<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/bitacora.php';

requerirPermiso($conn, 'categorias.crear');

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

// Recibir datos
$nombreRecibido = $_POST['categoria_nombre'] ?? '';

$diasMaximos = filter_var(
    $_POST['dias_max_prestamo'] ?? null,
    FILTER_VALIDATE_INT
);

// Validaciones
if (!is_string($nombreRecibido)) {
    header('Location: ../pages/category_form.php?error=campos');
    exit;
}

$nombre = trim($nombreRecibido);

if (
    $nombre === '' ||
    mb_strlen($nombre) > 50 ||
    $diasMaximos === false ||
    $diasMaximos === null ||
    $diasMaximos < 1 ||
    $diasMaximos > 365
) {
    header('Location: ../pages/category_form.php?error=campos');
    exit;
}

try {
    // Verificar si el nombre ya está registrado
    $stmt = $conn->prepare("
        SELECT categoria_id
        FROM categorias
        WHERE categoria_nombre = :nombre
        LIMIT 1
    ");

    $stmt->execute(['nombre' => $nombre]);

    if ($stmt->fetchColumn() !== false) {
        header('Location: ../pages/category_form.php?error=duplicado');
        exit;
    }

    // Iniciar transacción
    $conn->beginTransaction();

    // Insertar categoría
    $stmt = $conn->prepare("
        INSERT INTO categorias (
            categoria_nombre,
            dias_max_prestamo
        )
        VALUES (
            :nombre,
            :dias
        )
    ");

    $stmt->execute([
        'nombre' => $nombre,
        'dias' => $diasMaximos
    ]);

    $categoriaId = (int) $conn->lastInsertId();

    // Registrar creación en bitácora
    registrarBitacora(
        $conn,
        (int) $_SESSION['usuario_id'],
        'CREATE',
        'categorias',
        $categoriaId,
        null,
        [
            'categoria_nombre' => $nombre,
            'dias_max_prestamo' => $diasMaximos
        ]
    );

    $conn->commit();

    header('Location: ../pages/categories.php?success=creado');
    exit;

} catch (PDOException $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log('Error al crear categoría: ' . $e->getMessage());

    if ((int)($e->errorInfo[1] ?? 0) === 1062) {
        header('Location: ../pages/category_form.php?error=duplicado');
        exit;
    }

    header('Location: ../pages/category_form.php?error=sistema');
    exit;

} catch (Throwable $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log('Error al crear categoría: ' . $e->getMessage());

    header('Location: ../pages/category_form.php?error=sistema');
    exit;
}
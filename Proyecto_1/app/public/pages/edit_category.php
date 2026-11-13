<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';

requerirPermiso($conn, 'categorias.editar');

$categoriaId = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$categoriaId || $categoriaId <= 0) {
    http_response_code(400);
    exit('Categoría no válida.');
}

$stmt = $conn->prepare("
    SELECT categoria_id, categoria_nombre, dias_max_prestamo
    FROM categorias
    WHERE categoria_id = :id
      AND eliminado_en IS NULL
");

$stmt->execute(['id' => $categoriaId]);
$categoria = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$categoria) {
    http_response_code(404);
    exit('Categoría no encontrada.');
}

$tokenCSRF = generarTokenCSRF();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar categoría</title>
</head>
<body>

<h1>Editar categoría</h1>

<?php
$errores = [
    'duplicado' => 'Ya existe una categoría con ese nombre.',
    'campos' => 'Los datos ingresados no son válidos.',
    'sistema' => 'No se pudo actualizar la categoría.'
];

$error = $_GET['error'] ?? '';

if (is_string($error) && isset($errores[$error])): ?>
    <p style="color: red;">
        <?= htmlspecialchars($errores[$error], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
    </p>
<?php endif; ?>

<form action="../actions/update_category.php" method="POST">

    <input type="hidden" name="csrf_token"
        value="<?= htmlspecialchars($tokenCSRF, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

    <input type="hidden" name="categoria_id"
        value="<?= (int)$categoria['categoria_id'] ?>">

    <label for="categoria_nombre">Nombre:</label>
    <input
        type="text"
        name="categoria_nombre"
        id="categoria_nombre"
        maxlength="50"
        value="<?= htmlspecialchars($categoria['categoria_nombre'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
        required
    >

    <br><br>

    <label for="dias_max_prestamo">Días máximos:</label>
    <input
        type="number"
        name="dias_max_prestamo"
        id="dias_max_prestamo"
        min="1"
        max="365"
        value="<?= (int)$categoria['dias_max_prestamo'] ?>"
        required
    >

    <br><br>

    <button type="submit">Guardar cambios</button>
</form>

<p><a href="categories.php">Volver al listado</a></p>

</body>
</html>
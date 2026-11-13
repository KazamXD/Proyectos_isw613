<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';

requerirPermiso($conn, 'categorias.crear');

$tokenCSRF = generarTokenCSRF();

$errores = [
    'campos' => 'Revisa los datos ingresados.',
    'duplicado' => 'Ya existe una categoría con ese nombre.',
    'sistema' => 'No se pudo registrar la categoría.'
];

$error = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar categoría</title>
</head>
<body>

<h1>Registrar categoría</h1>

<?php if (is_string($error) && isset($errores[$error])): ?>
    <p style="color: red;">
        <?= htmlspecialchars(
            $errores[$error],
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ) ?>
    </p>
<?php endif; ?>

<form action="../actions/create_category.php" method="POST">

    <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars($tokenCSRF, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
    >

    <label for="categoria_nombre">Nombre de la categoría:</label>
    <input
        type="text"
        id="categoria_nombre"
        name="categoria_nombre"
        maxlength="50"
        required
    >

    <br><br>

    <label for="dias_max_prestamo">Días máximos de préstamo:</label>
    <input
        type="number"
        id="dias_max_prestamo"
        name="dias_max_prestamo"
        min="1"
        max="365"
        value="7"
        required
    >

    <br><br>

    <button type="submit">Guardar categoría</button>

</form>

<p><a href="categories.php">Volver al listado</a></p>



</body>
</html>
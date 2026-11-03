<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';

requerirPermiso($conn, 'equipos.crear');

// Obtener categorías activas
$stmt = $conn->prepare("
    SELECT categoria_id, categoria_nombre
    FROM categorias
    WHERE eliminado_en IS NULL
    ORDER BY categoria_nombre ASC
");

$stmt->execute();
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tokenCSRF = generarTokenCSRF();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar equipo</title>
</head>
<body>

<h1>Registrar equipo</h1>
<?php
$errores = [
    'campos' => 'Revisa los datos ingresados.',
    'estado' => 'El estado seleccionado no es válido.',
    'categoria' => 'La categoría no existe o está desactivada.',
    'duplicado' => 'Ya existe un equipo con ese código.',
    'sistema' => 'No se pudo registrar el equipo.',
    'imagen' => 'La imagen no es válida o supera los 2 MB.'
];

$error = $_GET['error'] ?? '';

if (is_string($error) && isset($errores[$error])): ?>
    <p style="color: red;">
        <?= htmlspecialchars(
            $errores[$error],
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ) ?>
    </p>
<?php endif; ?>

<form action="../actions/create_equipment.php" method="POST" enctype="multipart/form-data">
    <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars($tokenCSRF, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
    >
    <label for="codigo">Código del equipo:</label>
    <input
        type="text"
        id="codigo"
        name="codigo"
        maxlength="30"
        required
    >
    <br>
    <label for="id_categoria">Categoría:</label>
    <select name="id_categoria" id="id_categoria" required>
        <option value="">Seleccione una categoría</option>
        <?php foreach ($categorias as $categoria): ?>
            <option value="<?= (int)$categoria['categoria_id'] ?>">
                <?= htmlspecialchars(
                    $categoria['categoria_nombre'],
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                ) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <br>
    <label for="nombre_estado">Estado:</label>
    <select name="nombre_estado" id="nombre_estado" required>
        <option value="Disponible">Disponible</option>
        <option value="Mantenimiento">Mantenimiento</option>
    </select>
    <br>
    <label for="imagen">Imagen del equipo (opcional):</label>
    <input
        type="file"
        id="imagen"
        name="imagen"
        accept="image/jpeg,image/png,image/webp"
    >
    <p>Máximo 2 MB. Formatos JPG, PNG o WebP.</p>
    <br>
    <button type="submit">Guardar equipo</button>

</form>

<p>
    <a href="equipment.php">Volver al listado</a>
</p>

</body>
</html>
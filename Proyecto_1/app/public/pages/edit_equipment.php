<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';

requerirPermiso($conn, 'equipos.editar');

// Obtener y validar el ID
$equipoId = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$equipoId || $equipoId <= 0) {
    http_response_code(400);
    exit('ID de equipo no válido.');
}

// Consultar el equipo
$stmt = $conn->prepare("
    SELECT id_equipo, id_categoria, codigo, nombre_estado
    FROM equipos
    WHERE id_equipo = :id
      AND eliminado_en IS NULL
");

$stmt->execute(['id' => $equipoId]);
$equipo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$equipo) {
    http_response_code(404);
    exit('Equipo no encontrado.');
}

// Consultar categorías activas
$stmt = $conn->prepare("
    SELECT categoria_id, categoria_nombre
    FROM categorias
    WHERE eliminado_en IS NULL
    ORDER BY categoria_nombre ASC
");

$stmt->execute();
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Comprobar si tiene un préstamo activo
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
$estaPrestado = $stmt->fetchColumn() !== false;

$tokenCSRF = generarTokenCSRF();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar equipo</title>
</head>
<body>

<h1>Editar equipo</h1>

    <?php
    $errores = [
        'duplicado' => 'Ya existe otro equipo con ese código.',
        'campos' => 'Los datos ingresados no son válidos.',
        'sistema' => 'No se pudo actualizar el equipo.'
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

<?php if ($estaPrestado): ?>
    <p>
        Este equipo tiene un préstamo activo.
        Debe registrarse su devolución antes de editarlo.
    </p>
<?php else: ?>
    <form action="../actions/update_equipment.php" method="POST" enctype="multipart/form-data">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($tokenCSRF, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
        >
        <input
            type="hidden"
            name="equipo_id"
            value="<?= (int)$equipo['id_equipo'] ?>"
        >
        <label for="codigo">Código:</label>
        <input
            type="text"
            id="codigo"
            name="codigo"
            maxlength="30"
            value="<?= htmlspecialchars($equipo['codigo'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
            required
        >
        <br>
        <label for="id_categoria">Categoría:</label>
        <select name="id_categoria" id="id_categoria" required>
            <?php foreach ($categorias as $categoria): ?>
                <option
                    value="<?= (int)$categoria['categoria_id'] ?>"
                    <?= (int)$categoria['categoria_id'] === (int)$equipo['id_categoria'] ? 'selected' : '' ?>
                >
                    <?= htmlspecialchars($categoria['categoria_nombre'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>

        <br>
        <label for="nombre_estado">Estado:</label>
        <select name="nombre_estado" id="nombre_estado" required>
            <option
                value="Disponible"
                <?= $equipo['nombre_estado'] === 'Disponible' ? 'selected' : '' ?>
            >
                Disponible
            </option>
            <option
                value="Mantenimiento"
                <?= $equipo['nombre_estado'] === 'Mantenimiento' ? 'selected' : '' ?>
            >
                Mantenimiento
            </option>
        </select>
        <br>
            
        <label for="imagen">Cambiar imagen del equipo (opcional):</label>
        <input
            type="file"
            id="imagen"
            name="imagen"
            accept="image/jpeg,image/png,image/webp"
        >
        <p>Deja este campo vacío para conservar la imagen actual.</p>

        <br>

        <button type="submit">Guardar cambios</button>
    </form>
<?php endif; ?>
<p><a href="equipment.php">Volver al listado</a></p>

</body>
</html>
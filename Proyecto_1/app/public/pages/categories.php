<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';

requerirPermiso($conn, 'categorias.ver');

$tokenCSRF = generarTokenCSRF();
$puedeEliminar = tienePermiso($conn, 'categorias.eliminar');

// Consultar categorías activas
$stmt = $conn->prepare("
    SELECT
        categoria_id,
        categoria_nombre,
        dias_max_prestamo
    FROM categorias
    WHERE eliminado_en IS NULL
    ORDER BY categoria_nombre ASC
");

$stmt->execute();
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

$puedeCrear = tienePermiso($conn, 'categorias.crear');
$puedeEditar = tienePermiso($conn, 'categorias.editar');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Categorías</title>
</head>
<body>

    <h1>Gestión de categorías</h1>

    <?php if (tienePermiso($conn, 'categorias.ver')): ?>
        <p>
            <a href="../actions/export_categories.php">
                Exportar categorías a CSV
            </a>
        </p>
    <?php endif; ?>

    <!-- Mensajes de error o exito -->
    <?php if (($_GET['success'] ?? '') === 'creado'): ?>
        <p style="color: green;">
            Categoría registrada correctamente.
        </p>
    <?php endif; ?>

    <?php if (($_GET['success'] ?? '') === 'editado'): ?>
        <p style="color: green;">
            Categoría actualizada correctamente.
        </p>

    <?php elseif (($_GET['success'] ?? '') === 'sin_cambios'): ?>
        <p>No se realizaron cambios.</p>
    <?php endif; ?>

    <?php if (($_GET['success'] ?? '') === 'eliminado'): ?>
        <p style="color: green;">
            Categoría eliminada correctamente.
        </p>

    <?php elseif (($_GET['error'] ?? '') === 'asociada'): ?>
        <p style="color: red;">
            No se puede eliminar la categoría porque tiene equipos asociados
            o ya no está disponible.
        </p>

    <?php elseif (($_GET['error'] ?? '') === 'sistema'): ?>
        <p style="color: red;">
            No se pudo eliminar la categoría.
        </p>
    <?php endif; ?>

    <table border="1">
        <thead>
            <tr>
                <th>ID</th>
                <th>Categoría</th>
                <th>Días máximos</th>
                <th>Acciones</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($categorias as $categoria): ?>
                <tr>
                    <td><?= (int)$categoria['categoria_id'] ?></td>

                    <td>
                        <?= htmlspecialchars(
                            $categoria['categoria_nombre'],
                            ENT_QUOTES | ENT_SUBSTITUTE,
                            'UTF-8'
                        ) ?>
                    </td>

                    <td><?= (int)$categoria['dias_max_prestamo'] ?></td>

                    <td>
                        <?php if ($puedeEditar): ?>
                            <a href="edit_category.php?id=<?= (int)$categoria['categoria_id'] ?>">
                                Editar
                            </a>
                        <?php endif; ?> 
                        <?php if ($puedeEliminar): ?>
                            <form
                                action="../actions/delete_category.php"
                                method="POST"
                                style="display: inline;"
                                onsubmit="return confirm('¿Seguro que deseas eliminar esta categoría?');"
                            >
                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars(
                                        $tokenCSRF,
                                        ENT_QUOTES | ENT_SUBSTITUTE,
                                        'UTF-8'
                                    ) ?>"
                                >
                                <input
                                    type="hidden"
                                    name="categoria_id"
                                    value="<?= (int)$categoria['categoria_id'] ?>"
                                >
                                <button type="submit">Eliminar</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (empty($categorias)): ?>
        <p>No hay categorías registradas.</p>
    <?php endif; ?>

    <?php if ($puedeCrear): ?>
        <p>
            <a href="category_form.php">Registrar categoría</a>
        </p>
    <?php endif; ?>

    <p>
        <a href="dashboard.php">Volver al dashboard</a>
    </p>

</body>
</html>
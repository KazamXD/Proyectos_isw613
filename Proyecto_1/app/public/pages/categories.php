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

<?php require_once __DIR__ . '/../../includes/header.php'; ?>

    <div class="d-flex">
    <!-- Menú lateral -->
        <aside
            class="bg-dark text-white"
            style="width: 250px; min-height: 100vh; flex-shrink: 0;"
        >
            <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
        </aside>


        <main class="flex-grow-1 p-4">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="fw-bold mb-1">
                        Gestión de Categorías
                    </h1>
                    <p class="text-muted mb-0">
                        Administración de categorías y días máximos de préstamo.
                    </p>
                </div>

                <?php if ($puedeCrear): ?>
                    <a
                        href="category_form.php"
                        class="btn btn-primary"
                    >
                        + Nueva categoría
                    </a>
                <?php endif; ?>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        Categorías registradas
                    </h5>
                </div>
                <div class="card-body">

                <!-- Mensajes de error o exito -->
                <?php if (($_GET['success'] ?? '') === 'creado'): ?>
                    <div class="alert alert-success">
                        Categoría registrada correctamente.
                    </div>
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

                <div class="table-responsive">

                    <table class="table table-striped table-hover align-middle">

                        <thead class="table-dark">
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
                                            <a
                                                href="edit_category.php?id=<?= (int)$categoria['categoria_id'] ?>"
                                                class="btn btn-warning btn-sm"
                                            >
                                                Editar
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($puedeEliminar): ?>
                                            <form
                                                action="../actions/delete_category.php"
                                                method="POST"
                                                class="d-inline"
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
                                                <button type="submit" class="btn btn-danger btn-sm"> Eliminar </button>
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

                </div>
                <?php if (tienePermiso($conn, 'categorias.ver')): ?>
                    <div>
                        <a href="../actions/export_categories.php" class= "btn btn-success">
                            Exportar categorías a CSV
                        </a>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
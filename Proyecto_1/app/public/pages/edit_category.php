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

<?php require_once __DIR__ . '/../../includes/header.php'; ?>

<div class="d-flex">

    <!-- Menú lateral -->
    <aside
        class="bg-dark text-white"
        style="width: 250px; min-height: 100vh; flex-shrink: 0;"
    >
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    </aside>

    <!-- Contenido principal -->
    <main class="flex-grow-1 p-4">

        <!-- Encabezado -->
        <div class="mb-4">
            <h1 class="fw-bold mb-1">
                Editar categoría
            </h1>
            <p class="text-muted">
                Modifica la información de la categoría seleccionada.
            </p>
        </div>

        <!-- Tarjeta principal -->
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    Información de la categoría
                </h5>
            </div>

            <div class="card-body">

                <!-- Mensajes de error -->
                <?php
                $errores = [
                    'duplicado' => 'Ya existe una categoría con ese nombre.',
                    'campos' => 'Los datos ingresados no son válidos.',
                    'sistema' => 'No se pudo actualizar la categoría.'
                ];
                $error = $_GET['error'] ?? '';
                ?>

                <?php if (is_string($error) && isset($errores[$error])): ?>
                    <div class="alert alert-danger">
                        <?= htmlspecialchars(
                            $errores[$error],
                            ENT_QUOTES | ENT_SUBSTITUTE,
                            'UTF-8'
                        ) ?>

                    </div>
                <?php endif; ?>

                <!-- Formulario -->
                <form
                    action="../actions/update_category.php"
                    method="POST"
                >
                    <!-- Token CSRF -->
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                            $tokenCSRF,
                            ENT_QUOTES | ENT_SUBSTITUTE,
                            'UTF-8'
                        ) ?>"
                    >
                    <!-- ID de la categoría -->
                    <input
                        type="hidden"
                        name="categoria_id"
                        value="<?= (int) $categoria['categoria_id'] ?>"
                    >
                    <div class="row g-3">

                        <!-- Nombre -->
                        <div class="col-md-6">
                            <label
                                for="categoria_nombre"
                                class="form-label"
                            >
                                Nombre de la categoría
                            </label>
                            <input
                                type="text"
                                name="categoria_nombre"
                                id="categoria_nombre"
                                class="form-control"
                                maxlength="50"
                                value="<?= htmlspecialchars(
                                    $categoria['categoria_nombre'],
                                    ENT_QUOTES | ENT_SUBSTITUTE,
                                    'UTF-8'
                                ) ?>"
                                required
                            >
                        </div>

                        <!-- Días máximos -->
                        <div class="col-md-6">
                            <label
                                for="dias_max_prestamo"
                                class="form-label"
                            >
                                Días máximos de préstamo
                            </label>
                            <input
                                type="number"
                                name="dias_max_prestamo"
                                id="dias_max_prestamo"
                                class="form-control"
                                min="1"
                                max="365"
                                value="<?= (int) $categoria['dias_max_prestamo'] ?>"
                                required
                            >
                            <div class="form-text">
                                Cantidad máxima de días que se puede prestar
                                un equipo de esta categoría.
                            </div>
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="d-flex gap-2 mt-4">
                        <button
                            type="submit"
                            class="btn btn-success"
                        >
                            Guardar cambios
                        </button>
                        <a
                            href="categories.php"
                            class="btn btn-secondary"
                        >
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
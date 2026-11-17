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

        <!-- Encabezado -->
        <div class="mb-4">
            <h1 class="fw-bold">
                Registrar categoría
            </h1>
            <p class="text-muted">
                Completa el formulario para agregar una nueva categoría
            </p>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    Categoría
                </h5>
            </div>

            <?php if (is_string($error) && isset($errores[$error])): ?>
                <p style="color: red;">
                    <?= htmlspecialchars(
                        $errores[$error],
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        'UTF-8'
                    ) ?>
                </p>
            <?php endif; ?>

            <div class="card-body">

                <form action="../actions/create_category.php" method="POST">

                    <div class="row g-3">
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars($tokenCSRF, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                        >

                        <div class="col-md-6">
                            <label for="categoria_nombre">Nombre de la categoría:</label>
                            <input
                                type="text"
                                id="categoria_nombre"
                                name="categoria_nombre"
                                maxlength="50"
                                required
                            >
                        </div>

                        <br>

                        <div class="col-md-6">
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
                        </div>

                        <br>

                        <div class="d-flex gap-2 mt-4">
                             <button type="submit" class="btn btn-primary">Guardar categoría</button>
                        </div>

                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>


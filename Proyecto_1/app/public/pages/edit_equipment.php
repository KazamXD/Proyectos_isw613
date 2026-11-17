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
    SELECT
        id_equipo,
        codigo,
        id_categoria,
        nombre_estado,
        ruta_imagen
    FROM equipos
    WHERE id_equipo = :id
      AND eliminado_en IS NULL
    LIMIT 1
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
                Editar equipo
            </h1>
            <p class="text-muted">
                Modifica la información del equipo seleccionado.
            </p>
        </div>

        <!-- Mensajes de error -->
        <?php
        $errores = [
            'duplicado' => 'Ya existe otro equipo con ese código.',
            'campos' => 'Los datos ingresados no son válidos.',
            'sistema' => 'No se pudo actualizar el equipo.',
            'imagen' => 'La imagen no es válida o supera los 2 MB.'
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

        <!-- Equipo prestado -->
        <?php if ($estaPrestado): ?>
            <div class="alert alert-warning shadow-sm">
                <h5 class="alert-heading">
                    Equipo actualmente prestado
                </h5>
                <p>
                    Este equipo tiene un préstamo activo
                    y no puede modificarse.
                </p>

                <hr>

                <p class="mb-0">
                    Debe registrarse su devolución antes
                    de editarlo.
                </p>
            </div>
        <?php else: ?>

            <!-- Tarjeta principal -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        Información del equipo
                    </h5>
                </div>

                <div class="card-body">
                    <form
                        action="../actions/update_equipment.php"
                        method="POST"
                        enctype="multipart/form-data"
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

                        <!-- ID del equipo -->
                        <input
                            type="hidden"
                            name="equipo_id"
                            value="<?= (int) $equipo['id_equipo'] ?>"
                        >
                        <div class="row g-3">

                            <!-- Código -->
                            <div class="col-md-6">
                                <label for="codigo" class="form-label">
                                    Código del equipo
                                </label>
                                <input
                                    type="text"
                                    id="codigo"
                                    name="codigo"
                                    class="form-control"
                                    maxlength="30"
                                    value="<?= htmlspecialchars(
                                        $equipo['codigo'],
                                        ENT_QUOTES | ENT_SUBSTITUTE,
                                        'UTF-8'
                                    ) ?>"
                                    required
                                >
                            </div>

                            <!-- Categoría -->
                            <div class="col-md-6">
                                <label for="id_categoria" class="form-label">
                                    Categoría
                                </label>
                                <select
                                    name="id_categoria"
                                    id="id_categoria"
                                    class="form-select"
                                    required
                                >
                                    <?php foreach ($categorias as $categoria): ?>
                                        <option
                                            value="<?= (int) $categoria['categoria_id'] ?>"
                                            <?= (int) $categoria['categoria_id'] === (int) $equipo['id_categoria']
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            <?= htmlspecialchars(
                                                $categoria['categoria_nombre'],
                                                ENT_QUOTES | ENT_SUBSTITUTE,
                                                'UTF-8'
                                            ) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Estado -->
                            <div class="col-md-6">
                                <label for="nombre_estado" class="form-label">
                                    Estado del equipo
                                </label>
                                <select
                                    name="nombre_estado"
                                    id="nombre_estado"
                                    class="form-select"
                                    required
                                >
                                    <option
                                        value="Disponible"
                                        <?= $equipo['nombre_estado'] === 'Disponible'
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Disponible
                                    </option>
                                    <option
                                        value="Mantenimiento"
                                        <?= $equipo['nombre_estado'] === 'Mantenimiento'
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Mantenimiento
                                    </option>
                                </select>
                            </div>

                            <!-- Imagen actual -->
                            <div class="col-md-6">
                                <label class="form-label">
                                    Imagen actual
                                </label>
                                <div>
                                    <?php if (!empty($equipo['ruta_imagen'])): ?>
                                        <img
                                            src="../uploads/equipment/<?= rawurlencode($equipo['ruta_imagen']) ?>"
                                            alt="Imagen actual del equipo"
                                            class="img-thumbnail"
                                            style="width: 150px; height: 110px; object-fit: cover;"
                                        >
                                    <?php else: ?>
                                        <p class="text-muted">
                                            Este equipo no tiene imagen.
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Cambiar imagen -->
                            <div class="col-12">
                                <label for="imagen" class="form-label">
                                    Cambiar imagen del equipo (opcional)
                                </label>
                                <input
                                    type="file"
                                    id="imagen"
                                    name="imagen"
                                    class="form-control"
                                    accept="image/jpeg,image/png,image/webp"
                                >
                                <div class="form-text">
                                    Deja este campo vacío para conservar
                                    la imagen actual.
                                    Tamaño máximo: 2 MB.
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
                                href="equipment.php"
                                class="btn btn-secondary"
                            >
                                Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Volver al listado -->
        <?php if ($estaPrestado): ?>
            <a
                href="equipment.php"
                class="btn btn-secondary mt-3"
            >
                Volver al listado
            </a>
        <?php endif; ?>
    </main>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
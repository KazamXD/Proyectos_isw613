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
                Registrar nuevo equipo
            </h1>
            <p class="text-muted">
                Completa el formulario para agregar un equipo al inventario.
            </p>
        </div>

        <!-- Tarjeta principal -->
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    Información del equipo
                </h5>
            </div>
            <div class="card-body">

                <!-- Mensajes de error -->
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
                    action="../actions/create_equipment.php"
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
                                placeholder="Ejemplo: LAP-006"
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
                                <option value="">
                                    Seleccione una categoría
                                </option>
                                <?php foreach ($categorias as $categoria): ?>
                                    <option
                                        value="<?= (int) $categoria['categoria_id'] ?>"
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
                                <option value="Disponible">
                                    Disponible
                                </option>
                                <option value="Mantenimiento">
                                    Mantenimiento
                                </option>
                            </select>
                        </div>
                        
                        <!-- Imagen -->
                        <div class="col-md-6">
                            <label for="imagen" class="form-label">
                                Imagen del equipo (opcional)
                            </label>
                            <input
                                type="file"
                                id="imagen"
                                name="imagen"
                                class="form-control"
                                accept="image/jpeg,image/png,image/webp"
                            >
                            <div class="form-text">
                                Tamaño máximo: 2 MB.
                                Formatos permitidos: JPG, PNG o WebP.
                            </div>
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="d-flex gap-2 mt-4">
                        <button
                            type="submit"
                            class="btn btn-success"
                        >
                            Guardar equipo
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
    </main>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
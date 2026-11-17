<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';

requerirPermiso($conn, 'prestamos.crear');

// Obtener usuarios activos
$stmt = $conn->prepare("
    SELECT id, nombre, apellido
    FROM usuarios
    WHERE desactivado_en IS NULL
    ORDER BY nombre ASC
");
$stmt->execute();

$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener equipos disponibles y sin préstamos activos
$stmt = $conn->prepare("
    SELECT e.id_equipo, e.codigo
    FROM equipos e
    WHERE e.eliminado_en IS NULL
      AND e.nombre_estado = 'Disponible'
      AND NOT EXISTS (
          SELECT 1
          FROM prestamo_detalle pd
          INNER JOIN prestamos p
              ON pd.prestamo_id = p.id_prestamo
          WHERE pd.equipo_id = e.id_equipo
            AND p.estado IN ('prestado', 'retrasado')
            AND p.fecha_devolucion IS NULL
      )
    ORDER BY e.codigo ASC
");
$stmt->execute();

$equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                Registrar nuevo préstamo
            </h1>
            <p class="text-muted">
                Selecciona un usuario, los equipos y la fecha límite de devolución.
            </p>
        </div>

        <!-- Tarjeta principal -->
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    Información del préstamo
                </h5>
            </div>

            <div class="card-body">
                <form
                    action="../actions/create_loan.php"
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
                    <div class="row g-3">

                        <!-- Usuario -->
                        <div class="col-md-6">
                            <label for="usuario_id" class="form-label">
                                Usuario
                            </label>
                            <select
                                name="usuario_id"
                                id="usuario_id"
                                class="form-select"
                                required
                            >
                                <option value="">
                                    Seleccione un usuario
                                </option>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <option
                                        value="<?= (int) $usuario['id'] ?>"
                                    >
                                        <?= htmlspecialchars(
                                            $usuario['nombre'] . ' ' . $usuario['apellido'],
                                            ENT_QUOTES | ENT_SUBSTITUTE,
                                            'UTF-8'
                                        ) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Fecha límite -->
                        <div class="col-md-6">
                            <label for="fecha_limite" class="form-label">
                                Fecha y hora límite de devolución
                            </label>
                            <input
                                type="datetime-local"
                                name="fecha_limite"
                                id="fecha_limite"
                                class="form-control"
                                min="<?= date('Y-m-d\TH:i') ?>"
                                required
                            >
                            <div class="form-text">
                                La fecha límite debe ser posterior al momento actual.
                            </div>
                        </div>

                        <!-- Equipos -->
                        <div class="col-12">
                            <label for="equipos" class="form-label">
                                Equipos disponibles
                            </label>
                            <select
                                name="equipos[]"
                                id="equipos"
                                class="form-select"
                                multiple
                                size="6"
                                required
                            >
                                <?php foreach ($equipos as $equipo): ?>
                                    <option
                                        value="<?= (int) $equipo['id_equipo'] ?>"
                                    >
                                        <?= htmlspecialchars(
                                            $equipo['codigo'],
                                            ENT_QUOTES | ENT_SUBSTITUTE,
                                            'UTF-8'
                                        ) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                Mantén presionada la tecla Ctrl y selecciona
                                los equipos que deseas prestar.
                            </div>
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="d-flex gap-2 mt-4">
                        <button
                            type="submit"
                            class="btn btn-success"
                        >
                            Registrar préstamo
                        </button>

                        <a
                            href="loans.php"
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
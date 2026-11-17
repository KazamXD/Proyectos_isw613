<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/loan_restrictions.php';

date_default_timezone_set('America/Costa_Rica');

// Verificar permisos
requerirPermiso($conn, 'solicitudes.crear');

// Usuario autenticado
$usuarioId = (int) $_SESSION['usuario_id'];

// Comprobar préstamos retrasados
$tieneRetrasos = tienePrestamosRetrasados($conn, $usuarioId);

// Consultar equipos activos que pueden solicitarse
$stmt = $conn->prepare("
    SELECT
        e.id_equipo,
        e.codigo,
        c.categoria_nombre,
        c.dias_max_prestamo
    FROM equipos e
    INNER JOIN categorias c
        ON e.id_categoria = c.categoria_id
    WHERE e.eliminado_en IS NULL
      AND c.eliminado_en IS NULL
      AND e.nombre_estado <> 'Mantenimiento'
    ORDER BY c.categoria_nombre, e.codigo
");

$stmt->execute();

$equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generar token CSRF
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
                Solicitud de Equipos
            </h1>
            <p class="text-muted">
                Selecciona los equipos y el período en que deseas utilizarlos.
            </p>
        </div>

        <!-- Mensajes -->
        <?php if (($_GET['success'] ?? '') === 'creada'): ?>
            <div class="alert alert-success">
                Solicitud enviada correctamente.
                Está pendiente de aprobación.
            </div>
        <?php elseif (($_GET['error'] ?? '') === 'sistema'): ?>
            <div class="alert alert-danger">
                No se pudo registrar la solicitud.
            </div>
        <?php endif; ?>

        <!-- Comprobar préstamos retrasados -->
        <?php if ($tieneRetrasos): ?>
            <div class="alert alert-danger shadow-sm">
                <h5 class="alert-heading">
                    No puedes realizar nuevas solicitudes
                </h5>
                <p>
                    Actualmente tienes préstamos retrasados
                    pendientes de devolución.
                </p>
                <hr>
                <p class="mb-0">
                    Debes devolver los equipos pendientes antes
                    de solicitar otros.
                </p>
            </div>
        <?php else: ?>

            <!-- Tarjeta del formulario -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        Información de la solicitud
                    </h5>
                </div>
                <div class="card-body">
                    <form
                        action="../actions/create_request.php"
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

                            <!-- Equipos -->
                            <div class="col-12">
                                <label for="equipos" class="form-label">
                                    Equipos solicitados
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
                                            data-dias="<?= (int) $equipo['dias_max_prestamo'] ?>"
                                        >
                                            <?= htmlspecialchars(
                                                $equipo['codigo'] . ' - ' .
                                                $equipo['categoria_nombre'],
                                                ENT_QUOTES | ENT_SUBSTITUTE,
                                                'UTF-8'
                                            ) ?>
                                            (Máximo <?= (int) $equipo['dias_max_prestamo'] ?> días)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">
                                    Mantén presionada la tecla Ctrl para
                                    seleccionar varios equipos.
                                </div>
                            </div>

                            <!-- Fecha de inicio -->
                            <div class="col-md-6">
                                <label for="fecha_inicio" class="form-label">
                                    Fecha y hora de inicio
                                </label>
                                <input
                                    type="datetime-local"
                                    name="fecha_inicio"
                                    id="fecha_inicio"
                                    class="form-control"
                                    min="<?= date('Y-m-d\TH:i') ?>"
                                    required
                                >
                            </div>

                            <!-- Fecha de devolución -->
                            <div class="col-md-6">
                                <label for="fecha_fin" class="form-label">
                                    Fecha y hora de devolución
                                </label>
                                <input
                                    type="datetime-local"
                                    name="fecha_fin"
                                    id="fecha_fin"
                                    class="form-control"
                                    required
                                    disabled
                                >
                                <div class="form-text">
                                    El límite se calculará automáticamente
                                    según los equipos seleccionados.
                                </div>
                            </div>
                        </div>

                        <!-- Botones -->
                        <div class="d-flex gap-2 mt-4">
                            <button
                                type="submit"
                                class="btn btn-success"
                            >
                                Enviar solicitud
                            </button>
                            <a
                                href="requests.php"
                                class="btn btn-secondary"
                            >
                                Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>
<?php if (!$tieneRetrasos): ?>

<!-- Script en JavaScritp para que la fecha de entrega no se pase de los dias max establecidos, que no pase de 7, 5 o 3 dias -->
<script>
document.addEventListener('DOMContentLoaded', function () {

    const equipos = document.getElementById('equipos');
    const fechaInicio = document.getElementById('fecha_inicio');
    const fechaFin = document.getElementById('fecha_fin');

    function actualizarFechas() {

        // Obtener equipos seleccionados
        const seleccionados = Array.from(
            equipos.selectedOptions
        );

        // Si no hay equipos o fecha de inicio
        if (
            seleccionados.length === 0 ||
            !fechaInicio.value
        ) {
            fechaFin.disabled = true;
            fechaFin.value = '';
            fechaFin.removeAttribute('min');
            fechaFin.removeAttribute('max');
            return;
        }

        // Obtener el límite más restrictivo
        const diasMaximos = Math.min(
            ...seleccionados.map(
                equipo => Number(equipo.dataset.dias)
            )
        );

        // Convertir fecha inicial
        const inicio = new Date(fechaInicio.value);

        // Calcular fecha máxima
        const fechaMaxima = new Date(inicio);

        fechaMaxima.setDate(
            fechaMaxima.getDate() + diasMaximos
        );

        // Formatear fecha local para datetime-local
        function formatearFecha(fecha) {

            const anio = fecha.getFullYear();

            const mes = String(
                fecha.getMonth() + 1
            ).padStart(2, '0');

            const dia = String(
                fecha.getDate()
            ).padStart(2, '0');

            const hora = String(
                fecha.getHours()
            ).padStart(2, '0');

            const minuto = String(
                fecha.getMinutes()
            ).padStart(2, '0');

            return `${anio}-${mes}-${dia}T${hora}:${minuto}`;
        }

        // Habilitar fecha de devolución
        fechaFin.disabled = false;

        // Fecha mínima: un minuto después del inicio
        const fechaMinima = new Date(inicio);

        fechaMinima.setMinutes(
            fechaMinima.getMinutes() + 1
        );

        fechaFin.min = formatearFecha(fechaMinima);
        fechaFin.max = formatearFecha(fechaMaxima);

        // Limpiar fecha fuera del rango permitido
        if (
            fechaFin.value &&
            (
                fechaFin.value < fechaFin.min ||
                fechaFin.value > fechaFin.max
            )
        ) {
            fechaFin.value = '';
        }
    }

    // Eventos
    equipos.addEventListener('change', actualizarFechas);

    fechaInicio.addEventListener('change', actualizarFechas);

    // Inicializar
    actualizarFechas();
});
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
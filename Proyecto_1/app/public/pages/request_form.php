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

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitar equipos</title>
</head>
<body>

    <h1>Solicitud de equipos</h1>

    <?php if (($_GET['success'] ?? '') === 'creada'): ?>
        <p style="color: green;">
            Solicitud enviada correctamente. Está pendiente de aprobación.
        </p>
    <?php elseif (($_GET['error'] ?? '') === 'sistema'): ?>
        <p style="color: red;">
            No se pudo registrar la solicitud.
        </p>
    <?php endif; ?>

    <?php if ($tieneRetrasos): ?>

        <p style="color: red;">
            No puedes realizar nuevas solicitudes porque tienes
            préstamos retrasados pendientes de devolución.
        </p>

        <p>
            Debes devolver los equipos pendientes antes de solicitar otros.
        </p>

    <?php else: ?>

        <form
            action="../actions/create_request.php"
            method="POST"
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
            <label for="equipos">Equipos solicitados:</label>
            <select
                name="equipos[]"
                id="equipos"
                multiple
                required
            >
                <?php foreach ($equipos as $equipo): ?>
                    <option value="<?= (int) $equipo['id_equipo'] ?>" data-dias="<?= (int) $equipo['dias_max_prestamo'] ?>">
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
            <p>Mantén presionada la tecla Ctrl para seleccionar varios equipos.</p>
            <br>
            <label for="fecha_inicio">Fecha y hora de inicio:</label>
            <input
                type="datetime-local"
                name="fecha_inicio"
                id="fecha_inicio"
                min="<?= date('Y-m-d\TH:i') ?>"
                required
            >
            <br>
            <label for="fecha_fin">Fecha y hora de devolución:</label>

            <input type="datetime-local" name="fecha_fin" id="fecha_fin" required>
            
            <br>
            <button type="submit">
                Enviar solicitud
            </button>
        </form>
    <?php endif; ?>
    <p>
        <a href="dashboard.php">Volver al dashboard</a>
    </p>

    <!-- Script para actualizar la fecha de devolución según los equipos seleccionados -->
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

                // Si no hay equipos o fecha de inicio, no calcular
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
                    const mes = String(fecha.getMonth() + 1).padStart(2, '0');
                    const dia = String(fecha.getDate()).padStart(2, '0');
                    const hora = String(fecha.getHours()).padStart(2, '0');
                    const minuto = String(fecha.getMinutes()).padStart(2, '0');

                    return `${anio}-${mes}-${dia}T${hora}:${minuto}`;
                }
                fechaFin.disabled = false;

                // La devolución debe ser posterior al inicio
                const fechaMinima = new Date(inicio);
                fechaMinima.setMinutes(fechaMinima.getMinutes() + 1);
                fechaFin.min = formatearFecha(fechaMinima);
                fechaFin.max = formatearFecha(fechaMaxima);

                // Limpiar la fecha si ya no está dentro del rango
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

            // Actualizar al cambiar los equipos o el inicio
            equipos.addEventListener('change', actualizarFechas);
            fechaInicio.addEventListener('change', actualizarFechas);

            // Inicializar
            actualizarFechas();
        });
        </script>

</body>
</html>
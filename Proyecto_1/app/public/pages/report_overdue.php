<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';

// Solo usuarios con permiso pueden consultar reportes
requerirPermiso($conn, 'reportes.ver');

// Obtener búsqueda
$busqueda = $_GET['buscar'] ?? '';

if (!is_string($busqueda)) {
    $busqueda = '';
}

$busqueda = trim($busqueda);

// Construir filtros
$filtro = "
    WHERE p.fecha_limite < NOW()
      AND p.fecha_devolucion IS NULL
";

$parametros = [];

if ($busqueda !== '') {

    $filtro .= "
        AND (
            u.nombre LIKE :nombre
            OR u.apellido LIKE :apellido
            OR u.nom_usuario LIKE :usuario
        )
    ";

    $valor = "%$busqueda%";

    $parametros = [
        'nombre' => $valor,
        'apellido' => $valor,
        'usuario' => $valor
    ];
}

// Consultar préstamos retrasados
$stmt = $conn->prepare("
    SELECT
        p.id_prestamo,
        u.nombre,
        u.apellido,
        u.nom_usuario,
        p.fecha_entrega,
        p.fecha_limite,
        TIMESTAMPDIFF(
            HOUR,
            p.fecha_limite,
            NOW()
        ) AS horas_retraso,

        GROUP_CONCAT(
            e.codigo
            ORDER BY e.codigo
            SEPARATOR ', '
        ) AS equipos

    FROM prestamos p

    INNER JOIN usuarios u
        ON p.usuario_id = u.id

    LEFT JOIN prestamo_detalle pd
        ON p.id_prestamo = pd.prestamo_id

    LEFT JOIN equipos e
        ON pd.equipo_id = e.id_equipo

    $filtro

    GROUP BY
        p.id_prestamo,
        u.nombre,
        u.apellido,
        u.nom_usuario,
        p.fecha_entrega,
        p.fecha_limite

    ORDER BY p.fecha_limite ASC
");

$stmt->execute($parametros);

$prestamos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Total de préstamos retrasados
$totalRetrasados = count($prestamos);

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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="fw-bold mb-1">
                    Préstamos Retrasados
                </h1>
                <p class="text-muted mb-0">
                    Consulta los préstamos que superaron su fecha límite de devolución.
                </p>
            </div>

            <!-- Exportación CSV -->
            <a
                href="../actions/export_loans.php?<?= http_build_query([
                    'tipo' => 'overdue',
                    'buscar' => $busqueda
                ]) ?>"
                class="btn btn-success"
            >
                Descargar CSV
            </a>
        </div>

        <!-- Resumen -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card shadow-sm border-danger">
                    <div class="card-body text-center">
                        <h6 class="text-muted">
                            Total de préstamos retrasados
                        </h6>
                        <h2 class="fw-bold text-danger">
                            <?= (int) $totalRetrasados ?>
                        </h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tarjeta principal -->
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    Detalle de préstamos retrasados
                </h5>
            </div>

            <div class="card-body">

                <!-- Filtro de búsqueda -->
                <form
                    method="GET"
                    action="report_overdue.php"
                    class="row g-3 mb-4"
                >
                    <div class="col-md-7">
                        <label for="buscar" class="form-label">
                            Buscar usuario
                        </label>
                        <input
                            type="text"
                            id="buscar"
                            name="buscar"
                            class="form-control"
                            placeholder="Nombre, apellido o usuario"
                            value="<?= htmlspecialchars(
                                $busqueda,
                                ENT_QUOTES | ENT_SUBSTITUTE,
                                'UTF-8'
                            ) ?>"
                        >
                    </div>

                    <div class="col-md-5 d-flex align-items-end gap-2">
                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Buscar
                        </button>
                        <a
                            href="report_overdue.php"
                            class="btn btn-secondary"
                        >
                            Limpiar búsqueda
                        </a>
                    </div>
                </form>

                <!-- Tabla -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>ID préstamo</th>
                                <th>Usuario</th>
                                <th>Nombre completo</th>
                                <th>Equipos</th>
                                <th>Fecha de préstamo</th>
                                <th>Fecha límite</th>
                                <th>Horas de retraso</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($prestamos as $prestamo): ?>
                                <tr>
                                    <!-- ID -->
                                    <td>
                                        <?= (int) $prestamo['id_prestamo'] ?>
                                    </td>

                                    <!-- Usuario -->
                                    <td>
                                        <?= htmlspecialchars(
                                            $prestamo['nom_usuario'],
                                            ENT_QUOTES | ENT_SUBSTITUTE,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <!-- Nombre completo -->
                                    <td>
                                        <?= htmlspecialchars(
                                            $prestamo['nombre'] . ' ' . $prestamo['apellido'],
                                            ENT_QUOTES | ENT_SUBSTITUTE,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <!-- Equipos -->
                                    <td>
                                        <?= htmlspecialchars(
                                            $prestamo['equipos'] ?? 'Sin equipos',
                                            ENT_QUOTES | ENT_SUBSTITUTE,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <!-- Fecha de préstamo -->
                                    <td>
                                        <?= htmlspecialchars(
                                            $prestamo['fecha_entrega'],
                                            ENT_QUOTES | ENT_SUBSTITUTE,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <!-- Fecha límite -->
                                    <td>
                                        <?= htmlspecialchars(
                                            $prestamo['fecha_limite'],
                                            ENT_QUOTES | ENT_SUBSTITUTE,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <!-- Horas de retraso -->
                                    <td>
                                        <span class="badge bg-danger">
                                            <?= (int) $prestamo['horas_retraso'] ?>
                                            horas
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Sin resultados -->
                <?php if (empty($prestamos)): ?>
                    <div class="alert alert-success">
                        No se encontraron préstamos retrasados.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Volver -->
        <div class="mt-3">
            <a
                href="reports.php"
                class="btn btn-secondary"
            >
                Volver a reportes
            </a>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
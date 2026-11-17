<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';

requerirPermiso($conn, 'reportes.ver');

// Obtener filtros
$busqueda = $_GET['buscar'] ?? '';
$estado = $_GET['estado'] ?? '';

if (!is_string($busqueda)) {
    $busqueda = '';
}

if (!is_string($estado)) {
    $estado = '';
}

$busqueda = trim($busqueda);

// Estados permitidos
$estadosPermitidos = [
    'prestado',
    'devuelto',
    'retrasado'
];

if (!in_array($estado, $estadosPermitidos, true)) {
    $estado = '';
}

// Construir filtros
$filtro = "WHERE 1=1";
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

    $parametros['nombre'] = $valor;
    $parametros['apellido'] = $valor;
    $parametros['usuario'] = $valor;
}

if ($estado !== '') {
    $filtro .= " AND p.estado = :estado";
    $parametros['estado'] = $estado;
}

// Consultar historial
$stmt = $conn->prepare("
    SELECT
        p.id_prestamo,
        u.nombre,
        u.apellido,
        u.nom_usuario,
        o.nombre AS operador,
        p.fecha_entrega,
        p.fecha_limite,
        p.fecha_devolucion,
        p.estado,

        GROUP_CONCAT(
            e.codigo
            ORDER BY e.codigo
            SEPARATOR ', '
        ) AS equipos

    FROM prestamos p

    INNER JOIN usuarios u
        ON p.usuario_id = u.id

    INNER JOIN usuarios o
        ON p.operador_id = o.id

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
        o.nombre,
        p.fecha_entrega,
        p.fecha_limite,
        p.fecha_devolucion,
        p.estado

    ORDER BY p.fecha_entrega DESC
");

$stmt->execute($parametros);

$prestamos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalRegistros = count($prestamos);

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
                    Historial de Préstamos
                </h1>
                <p class="text-muted mb-0">
                    Consulta el historial de préstamos registrados en SIGEL.
                </p>
            </div>

            <!-- Exportar CSV -->
            <a
                href="../actions/export_loans.php?<?= http_build_query([
                    'tipo' => 'history',
                    'buscar' => $busqueda,
                    'estado' => $estado
                ]) ?>"
                class="btn btn-success"
            >
                Descargar CSV
            </a>
        </div>

        <!-- Resumen -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card shadow-sm border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted">
                            Total de registros encontrados
                        </h6>
                        <h2 class="fw-bold text-primary">
                            <?= (int) $totalRegistros ?>
                        </h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tarjeta principal -->
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    Detalle del historial
                </h5>
            </div>
            <div class="card-body">
                <!-- Filtros -->
                <form
                    method="GET"
                    action="report_history.php"
                    class="row g-3 mb-4"
                >
                    <!-- Buscar usuario -->
                    <div class="col-md-5">
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

                    <!-- Estado -->
                    <div class="col-md-4">
                        <label for="estado" class="form-label">
                            Estado del préstamo
                        </label>
                        <select
                            name="estado"
                            id="estado"
                            class="form-select"
                        >
                            <option value="">
                                Todos los estados
                            </option>
                            <?php foreach ($estadosPermitidos as $opcion): ?>
                                <option
                                    value="<?= htmlspecialchars(
                                        $opcion,
                                        ENT_QUOTES | ENT_SUBSTITUTE,
                                        'UTF-8'
                                    ) ?>"
                                    <?= $estado === $opcion ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars(
                                        ucfirst($opcion),
                                        ENT_QUOTES | ENT_SUBSTITUTE,
                                        'UTF-8'
                                    ) ?>

                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Botones -->
                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Filtrar
                        </button>
                        <a
                            href="report_history.php"
                            class="btn btn-secondary"
                        >
                            Limpiar
                        </a>
                    </div>
                </form>

                <!-- Tabla -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Usuario</th>
                                <th>Nombre completo</th>
                                <th>Operador</th>
                                <th>Equipos</th>
                                <th>Fecha préstamo</th>
                                <th>Fecha límite</th>
                                <th>Fecha devolución</th>
                                <th>Estado</th>
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

                                    <!-- Operador -->
                                    <td>
                                        <?= htmlspecialchars(
                                            $prestamo['operador'],
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

                                    <!-- Fecha préstamo -->
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

                                    <!-- Fecha devolución -->
                                    <td>
                                        <?= htmlspecialchars(
                                            $prestamo['fecha_devolucion'] ?? 'Pendiente',
                                            ENT_QUOTES | ENT_SUBSTITUTE,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <!-- Estado -->
                                    <td>
                                        <?php
                                        $estadoPrestamo = $prestamo['estado'];
                                        $claseEstado = match ($estadoPrestamo) {
                                            'prestado' => 'bg-primary',
                                            'devuelto' => 'bg-success',
                                            'retrasado' => 'bg-danger',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?= $claseEstado ?>">
                                            <?= htmlspecialchars(
                                                ucfirst($estadoPrestamo),
                                                ENT_QUOTES | ENT_SUBSTITUTE,
                                                'UTF-8'
                                            ) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Sin resultados -->
                <?php if (empty($prestamos)): ?>
                    <div class="alert alert-info">
                        No se encontraron préstamos con esos criterios.
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
<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';

// Solo usuarios autorizados pueden consultar reportes
requerirPermiso($conn, 'reportes.ver');
requerirPermiso($conn, 'equipos.ver');

// Obtener filtros
$categoriaId = filter_var(
    $_GET['categoria'] ?? 0,
    FILTER_VALIDATE_INT
);

$estado = $_GET['estado'] ?? '';

// Estados permitidos
$estadosPermitidos = [
    'Disponible',
    'Prestado',
    'Mantenimiento'
];

// Validar filtros
if ($categoriaId === false || $categoriaId < 0) {
    $categoriaId = 0;
}

if (!is_string($estado) || !in_array($estado, $estadosPermitidos, true)) {
    $estado = '';
}

// Consultar categorías
$stmt = $conn->prepare("
    SELECT categoria_id, categoria_nombre
    FROM categorias
    WHERE eliminado_en IS NULL
    ORDER BY categoria_nombre ASC
");

$stmt->execute();
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Construir filtros del inventario
$filtro = "WHERE e.eliminado_en IS NULL";
$parametros = [];

if ($categoriaId > 0) {
    $filtro .= " AND e.id_categoria = :categoria";
    $parametros['categoria'] = $categoriaId;
}

if ($estado !== '') {
    $filtro .= " AND e.nombre_estado = :estado";
    $parametros['estado'] = $estado;
}

// Consultar inventario
$stmt = $conn->prepare("
    SELECT
        e.id_equipo,
        e.codigo,
        c.categoria_nombre,
        e.nombre_estado
    FROM equipos e
    INNER JOIN categorias c
        ON e.id_categoria = c.categoria_id
    $filtro
    ORDER BY c.categoria_nombre ASC, e.codigo ASC
");

$stmt->execute($parametros);
$equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas del inventario filtrado
$totalEquipos = count($equipos);

$disponibles = 0;
$prestados = 0;
$mantenimiento = 0;

foreach ($equipos as $equipo) {
    switch ($equipo['nombre_estado']) {
        case 'Disponible':
            $disponibles++;
            break;

        case 'Prestado':
            $prestados++;
            break;

        case 'Mantenimiento':
            $mantenimiento++;
            break;
    }
}

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
                    Reporte de Inventario
                </h1>
                <p class="text-muted mb-0">
                    Consulta el inventario y los estados de los equipos.
                </p>

            </div>
            <a
                href="../actions/export_equipment.php?<?= http_build_query([
                    'categoria' => $categoriaId,
                    'estado' => $estado
                ]) ?>"
                class="btn btn-success"
            >
                Descargar CSV
            </a>
        </div>

        <!-- Resumen -->
        <h4 class="mb-3">
            Resumen del inventario
        </h4>

        <div class="row g-3 mb-4">

            <!-- Total -->
            <div class="col-md-3">
                <div class="card shadow-sm border-primary h-100">
                    <div class="card-body text-center">
                        <h6 class="text-muted">
                            Total de equipos
                        </h6>
                        <h2 class="fw-bold text-primary">
                            <?= (int) $totalEquipos ?>
                        </h2>
                    </div>
                </div>
            </div>

            <!-- Disponibles -->
            <div class="col-md-3">
                <div class="card shadow-sm border-success h-100">
                    <div class="card-body text-center">
                        <h6 class="text-muted">
                            Disponibles
                        </h6>
                        <h2 class="fw-bold text-success">
                            <?= (int) $disponibles ?>
                        </h2>
                    </div>
                </div>
            </div>

            <!-- Prestados -->
            <div class="col-md-3">
                <div class="card shadow-sm border-warning h-100">
                    <div class="card-body text-center">
                        <h6 class="text-muted">
                            Prestados
                        </h6>
                        <h2 class="fw-bold text-warning">
                            <?= (int) $prestados ?>
                        </h2>
                    </div>
                </div>
            </div>

            <!-- Mantenimiento -->
            <div class="col-md-3">
                <div class="card shadow-sm border-danger h-100">
                    <div class="card-body text-center">
                        <h6 class="text-muted">
                            En mantenimiento
                        </h6>
                        <h2 class="fw-bold text-danger">
                            <?= (int) $mantenimiento ?>
                        </h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tarjeta del reporte -->
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    Detalle del inventario
                </h5>
            </div>

            <div class="card-body">

                <!-- Filtros -->
                <form
                    method="GET"
                    action="report_equipment.php"
                    class="row g-3 mb-4"
                >

                    <!-- Categoría -->
                    <div class="col-md-5">
                        <label for="categoria" class="form-label">
                            Categoría
                        </label>
                        <select
                            name="categoria"
                            id="categoria"
                            class="form-select"
                        >
                            <option value="0">
                                Todas las categorías
                            </option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option
                                    value="<?= (int) $categoria['categoria_id'] ?>"
                                    <?= $categoriaId === (int) $categoria['categoria_id']
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
                    <div class="col-md-4">
                        <label for="estado" class="form-label">
                            Estado
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
                                        $opcion,
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
                            href="report_equipment.php"
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
                                <th>Código</th>
                                <th>Categoría</th>
                                <th>Estado</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($equipos as $equipo): ?>
                                <tr>

                                    <!-- ID -->
                                    <td>
                                        <?= (int) $equipo['id_equipo'] ?>
                                    </td>

                                    <!-- Código -->
                                    <td>
                                        <?= htmlspecialchars(
                                            $equipo['codigo'],
                                            ENT_QUOTES | ENT_SUBSTITUTE,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <!-- Categoría -->
                                    <td>
                                        <?= htmlspecialchars(
                                            $equipo['categoria_nombre'],
                                            ENT_QUOTES | ENT_SUBSTITUTE,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <!-- Estado -->
                                    <td>
                                        <?php
                                        $estadoEquipo = $equipo['nombre_estado'];
                                        $claseEstado = match ($estadoEquipo) {
                                            'Disponible' => 'bg-success',
                                            'Prestado' => 'bg-warning text-dark',
                                            'Mantenimiento' => 'bg-danger',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?= $claseEstado ?>">
                                            <?= htmlspecialchars(
                                                $estadoEquipo,
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
                <?php if (empty($equipos)): ?>
                    <div class="alert alert-info">
                        No se encontraron equipos con los filtros seleccionados.
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
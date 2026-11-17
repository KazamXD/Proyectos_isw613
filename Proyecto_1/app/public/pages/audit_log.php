<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';

requerirPermiso($conn, 'reportes.ver');

// Obtener filtro
$accion = $_GET['accion'] ?? '';

$accionesPermitidas = [
    'CREATE',
    'UPDATE',
    'DELETE',
    'LOGIN'
];

if (!is_string($accion) || !in_array($accion, $accionesPermitidas, true)) {
    $accion = '';
}

// Construir consulta
$filtro = '';
$parametros = [];

if ($accion !== '') {
    $filtro = 'WHERE b.accion = :accion';
    $parametros['accion'] = $accion;
}

// Consultar bitácora
$stmt = $conn->prepare("
    SELECT
        b.usuario_id,
        u.nom_usuario,
        b.accion,
        b.entidad,
        b.entidad_id,
        b.valores_antes,
        b.valores_despues,
        b.ip,
        b.creado_en

    FROM bitacora b

    LEFT JOIN usuarios u
        ON b.usuario_id = u.id

    $filtro

    ORDER BY b.creado_en DESC
    LIMIT 100
");

$stmt->execute($parametros);

$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                Bitácora de Actividades
            </h1>
            <p class="text-muted">
                Consulta las operaciones realizadas por los usuarios del sistema.
            </p>
        </div>

        <!-- Tarjeta principal -->
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    Registro de actividades
                </h5>
            </div>

            <div class="card-body">

                <!-- Filtros -->
                <form
                    method="GET"
                    action="audit_log.php"
                    class="row g-3 mb-4"
                >

                    <div class="col-md-6">
                        <label for="accion" class="form-label">
                            Filtrar por acción
                        </label>

                        <select
                            name="accion"
                            id="accion"
                            class="form-select"
                        >
                            <option value="">
                                Todas las acciones
                            </option>

                            <?php foreach ($accionesPermitidas as $opcion): ?>
                                <option
                                    value="<?= htmlspecialchars(
                                        $opcion,
                                        ENT_QUOTES | ENT_SUBSTITUTE,
                                        'UTF-8'
                                    ) ?>"
                                    <?= $accion === $opcion ? 'selected' : '' ?>
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

                    <div class="col-md-6 d-flex align-items-end gap-2">
                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Filtrar
                        </button>
                        <a
                            href="audit_log.php"
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
                                <th>Usuario</th>
                                <th>Acción</th>
                                <th>Entidad</th>
                                <th>ID registro</th>
                                <th>Valores anteriores</th>
                                <th>Valores nuevos</th>
                                <th>IP</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($registros as $registro): ?>
                                <tr>
                                    <!-- Usuario -->
                                    <td>
                                        <?= htmlspecialchars(
                                            $registro['nom_usuario'] ?? 'Sistema',
                                            ENT_QUOTES | ENT_SUBSTITUTE,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <!-- Acción -->
                                    <td>
                                        <?php
                                        $accionRegistro = $registro['accion'];
                                        $claseAccion = match ($accionRegistro) {
                                            'CREATE' => 'bg-success',
                                            'UPDATE' => 'bg-warning text-dark',
                                            'DELETE' => 'bg-danger',
                                            'LOGIN' => 'bg-primary',
                                            default => 'bg-secondary'
                                        };
                                        ?>

                                        <span class="badge <?= $claseAccion ?>">
                                            <?= htmlspecialchars(
                                                $accionRegistro,
                                                ENT_QUOTES | ENT_SUBSTITUTE,
                                                'UTF-8'
                                            ) ?>
                                        </span>
                                    </td>

                                    <!-- Entidad -->
                                    <td>
                                        <?= htmlspecialchars(
                                            $registro['entidad'],
                                            ENT_QUOTES | ENT_SUBSTITUTE,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <!-- ID -->
                                    <td>
                                        <?= $registro['entidad_id'] !== null
                                            ? (int) $registro['entidad_id']
                                            : '—' ?>
                                    </td>

                                    <!-- Valores anteriores -->
                                    <td>
                                        <pre class="bg-light border rounded p-2 mb-0"
                                             style="max-width: 300px; max-height: 150px; overflow: auto;"><?= htmlspecialchars(
                                            $registro['valores_antes'] ?? '—',
                                            ENT_QUOTES | ENT_SUBSTITUTE,
                                            'UTF-8'
                                        ) ?></pre>
                                    </td>

                                    <!-- Valores nuevos -->
                                    <td>
                                        <pre class="bg-light border rounded p-2 mb-0"
                                             style="max-width: 300px; max-height: 150px; overflow: auto;"><?= htmlspecialchars(
                                            $registro['valores_despues'] ?? '—',
                                            ENT_QUOTES | ENT_SUBSTITUTE,
                                            'UTF-8'
                                        ) ?></pre>
                                    </td>

                                    <!-- IP -->
                                    <td>
                                        <?= htmlspecialchars(
                                            $registro['ip'] ?? '—',
                                            ENT_QUOTES | ENT_SUBSTITUTE,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <!-- Fecha -->
                                    <td>
                                        <?= htmlspecialchars(
                                            $registro['creado_en'],
                                            ENT_QUOTES | ENT_SUBSTITUTE,
                                            'UTF-8'
                                        ) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Sin resultados -->
                <?php if (empty($registros)): ?>
                    <div class="alert alert-info">
                        No se encontraron registros.
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
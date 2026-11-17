<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';

// Verificar autenticación
requerirLogin();

// Comprobar permisos
$puedeVerTodas = tienePermiso($conn, 'solicitudes.ver_todas');
$puedeVerPropias = tienePermiso($conn, 'solicitudes.ver_propias');
$puedeAprobar = tienePermiso($conn, 'solicitudes.aprobar');
$puedeRechazar = tienePermiso($conn, 'solicitudes.rechazar');   
$puedeCancelar = tienePermiso($conn, 'solicitudes.cancelar');
$tokenCSRF = generarTokenCSRF();

//Negar acceso si no tiene permisos para ver solicitudes
if (!$puedeVerTodas && !$puedeVerPropias) {
    http_response_code(403);
    exit('Acceso denegado.');
}


// Consulta principal
$sql = "
    SELECT
        s.id_solicitud,
        s.usuario_id,
        u.nom_usuario,
        s.fecha_inicio,
        s.fecha_fin,
        s.estado
    FROM solicitudes s
    INNER JOIN usuarios u
        ON s.usuario_id = u.id
";

// Si no puede ver todas, solamente mostrar las propias
if (!$puedeVerTodas) {
    $sql .= " WHERE s.usuario_id = :usuario_id";
}

$sql .= " ORDER BY s.id_solicitud DESC";

$stmt = $conn->prepare($sql);

if ($puedeVerTodas) {
    $stmt->execute();
} else {
    $stmt->execute([
        'usuario_id' => (int) $_SESSION['usuario_id']
    ]);
}

$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                    Solicitudes de Equipos
                </h1>
                <p class="text-muted mb-0">
                    Consulta y gestión de solicitudes de préstamo.
                </p>
            </div>

            <?php if (tienePermiso($conn, 'solicitudes.crear')): ?>
                <a
                    href="request_form.php"
                    class="btn btn-success"
                >
                    + Nueva solicitud
                </a>
            <?php endif; ?>
        </div>

        <!-- Mensajes -->
        <?php if (($_GET['success'] ?? '') === 'aprobada'): ?>
            <div class="alert alert-success">
                Solicitud aprobada correctamente.
            </div>
        <?php endif; ?>

        <?php if (($_GET['success'] ?? '') === 'rechazada'): ?>
            <div class="alert alert-success">
                Solicitud rechazada correctamente.
            </div>
        <?php endif; ?>
        <?php if (($_GET['success'] ?? '') === 'cancelada'): ?>
            <div class="alert alert-success">
                Solicitud cancelada correctamente.
            </div>
        <?php endif; ?>

        <!-- Tarjeta principal -->
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    Solicitudes registradas
                </h5>
            </div>

            <div class="card-body">

                <!-- Tabla -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Usuario</th>
                                <th>Fecha de inicio</th>
                                <th>Fecha de devolución</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($solicitudes as $solicitud): ?>
                                <tr>
                                    <!-- ID -->
                                    <td>
                                        <?= (int) $solicitud['id_solicitud'] ?>
                                    </td>

                                    <!-- Usuario -->
                                    <td>
                                        <?= htmlspecialchars(
                                            $solicitud['nom_usuario'],
                                            ENT_QUOTES | ENT_SUBSTITUTE,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <!-- Fecha de inicio -->
                                    <td>
                                        <?= htmlspecialchars(
                                            $solicitud['fecha_inicio'],
                                            ENT_QUOTES | ENT_SUBSTITUTE,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <!-- Fecha de devolución -->
                                    <td>
                                        <?= htmlspecialchars(
                                            $solicitud['fecha_fin'],
                                            ENT_QUOTES | ENT_SUBSTITUTE,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <!-- Estado -->
                                    <td>
                                        <?php
                                        $estado = $solicitud['estado'];
                                        $claseEstado = match ($estado) {
                                            'pendiente' => 'bg-warning text-dark',
                                            'aprobada' => 'bg-success',
                                            'rechazada' => 'bg-danger',
                                            'cancelada' => 'bg-secondary',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?= $claseEstado ?>">
                                            <?= htmlspecialchars(
                                                ucfirst($estado),
                                                ENT_QUOTES | ENT_SUBSTITUTE,
                                                'UTF-8'
                                            ) ?>
                                        </span>
                                    </td>

                                    <!-- Acciones -->
                                    <td>
                                        <?php if ($solicitud['estado'] === 'pendiente'): ?>

                                            <!-- Aprobar -->
                                            <?php if ($puedeAprobar): ?>
                                                <form
                                                    action="../actions/approve_request.php"
                                                    method="POST"
                                                    class="d-inline"
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
                                                    <input
                                                        type="hidden"
                                                        name="solicitud_id"
                                                        value="<?= (int) $solicitud['id_solicitud'] ?>"
                                                    >
                                                    <button
                                                        type="submit"
                                                        class="btn btn-success btn-sm"
                                                    >
                                                        Aprobar
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <!-- Rechazar -->
                                            <?php if ($puedeRechazar): ?>
                                                <form
                                                    action="../actions/reject_request.php"
                                                    method="POST"
                                                    class="d-inline"
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
                                                    <input
                                                        type="hidden"
                                                        name="solicitud_id"
                                                        value="<?= (int) $solicitud['id_solicitud'] ?>"
                                                    >
                                                    <input
                                                        type="text"
                                                        name="motivo_rechazo"
                                                        class="form-control form-control-sm d-inline-block"
                                                        style="width: 180px;"
                                                        maxlength="255"
                                                        placeholder="Motivo del rechazo"
                                                        aria-label="Motivo del rechazo"
                                                        required
                                                    >
                                                    <button
                                                        type="submit"
                                                        class="btn btn-danger btn-sm"
                                                    >
                                                        Rechazar
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <!-- Cancelar -->
                                            <?php if (
                                                $puedeCancelar &&
                                                (int) $solicitud['usuario_id'] === (int) $_SESSION['usuario_id']
                                            ): ?>
                                                <form
                                                    action="../actions/cancel_request.php"
                                                    method="POST"
                                                    class="d-inline"
                                                    onsubmit="return confirm('¿Seguro que deseas cancelar esta solicitud?');"
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
                                                    <input
                                                        type="hidden"
                                                        name="solicitud_id"
                                                        value="<?= (int) $solicitud['id_solicitud'] ?>"
                                                    >
                                                    <button
                                                        type="submit"
                                                        class="btn btn-warning btn-sm"
                                                    >
                                                        Cancelar
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">
                                                Sin acciones
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Sin solicitudes -->
                <?php if (empty($solicitudes)): ?>
                    <div class="alert alert-info">
                        No hay solicitudes registradas.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
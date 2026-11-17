<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/update_overdue_loans.php';

requerirPermiso($conn, 'prestamos.ver');
requerirPermiso($conn, 'prestamos.crear');
actualizarRetrasos($conn);
$puedeDevolver = tienePermiso($conn, 'prestamos.devolver');
$tokenCSRF = generarTokenCSRF();

$stmt = $conn->prepare("
    SELECT
        p.id_prestamo,
        u.nombre AS usuario,
        o.nombre AS operador,
        p.fecha_entrega,
        p.fecha_limite,
        p.fecha_devolucion,
        p.estado,
        GROUP_CONCAT(
            CONCAT(e.codigo, ' (', e.nombre_estado, ')')
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
    GROUP BY
        p.id_prestamo,
        u.nombre,
        o.nombre,
        p.fecha_entrega,
        p.fecha_limite,
        p.fecha_devolucion,
        p.estado
    ORDER BY p.id_prestamo DESC
");

$stmt->execute();
$prestamos = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                    Gestión de Préstamos
                </h1>
                <p class="text-muted mb-0">
                    Consulta y administración de préstamos de equipos.
                </p>
            </div>

            <?php if (tienePermiso($conn, 'prestamos.crear')): ?>
                <a
                    href="loan_form.php"
                    class="btn btn-success"
                >
                    + Nuevo préstamo
                </a>
            <?php endif; ?>
        </div>

        <!-- Tarjeta principal -->
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    Préstamos registrados
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
                                <th>Operador</th>
                                <th>Equipos</th>
                                <th>Fecha entrega</th>
                                <th>Fecha límite</th>
                                <th>Estado</th>
                                <th>Acciones</th>
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
                                            $prestamo['usuario'],
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

                                    <!-- Fecha entrega -->
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

                                    <!-- Estado -->
                                    <td>
                                        <?php
                                        $estado = $prestamo['estado'];
                                        $claseEstado = match ($estado) {
                                            'prestado' => 'bg-primary',
                                            'devuelto' => 'bg-success',
                                            'retrasado' => 'bg-danger',
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
                                        <?php if (
                                            $puedeDevolver &&
                                            $prestamo['estado'] !== 'devuelto' &&
                                            $prestamo['fecha_devolucion'] === null
                                        ): ?>
                                            <form
                                                action="../actions/return_loan.php"
                                                method="POST"
                                                class="d-inline"
                                                onsubmit="return confirm('¿Registrar la devolución de este préstamo?');"
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
                                                    name="prestamo_id"
                                                    value="<?= (int) $prestamo['id_prestamo'] ?>"
                                                >
                                                <button
                                                    type="submit"
                                                    class="btn btn-primary btn-sm"
                                                >
                                                    Registrar devolución
                                                </button>
                                            </form>
                                            
                                        <?php elseif ($prestamo['estado'] === 'devuelto'): ?>
                                            <span class="badge bg-success">
                                                Devuelto
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">
                                                Sin acciones disponibles
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Sin registros -->
                <?php if (empty($prestamos)): ?>
                    <div class="alert alert-info">
                        No hay préstamos registrados.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
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
<!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Préstamos</title>
    </head>
    <body>
        <h1>Listado de préstamos</h1>

    <p>
    Permiso para devolver:
        <?= $puedeDevolver ? 'SÍ' : 'NO' ?>
    </p>

        <table border="1">
            <thead>
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
                        <td><?= (int)$prestamo['id_prestamo'] ?></td>
                        <td><?= htmlspecialchars($prestamo['usuario'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($prestamo['operador'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($prestamo['equipos'] ?? 'Sin equipos', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($prestamo['fecha_entrega'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($prestamo['fecha_limite'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($prestamo['estado'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                        
                        <td>
                            <?php if (
                                $puedeDevolver &&
                                $prestamo['estado'] !== 'devuelto' &&
                                $prestamo['fecha_devolucion'] === null
                            ): ?>
                                <form
                                    action="../actions/return_loan.php"
                                    method="POST"
                                    onsubmit="return confirm('¿Registrar la devolución de este préstamo?');"
                                >
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= htmlspecialchars($tokenCSRF, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                                    >
                                    <input
                                        type="hidden"
                                        name="prestamo_id"
                                        value="<?= (int) $prestamo['id_prestamo'] ?>"
                                    >
                                    <button type="submit">
                                        Registrar devolución
                                    </button>
                                </form>
                            <?php elseif ($prestamo['estado'] === 'devuelto'): ?>
                                Devuelto
                            <?php else: ?>
                                Sin acciones disponibles
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p>
            <a href="dashboard.php">Volver al dashboard</a>
        </p>

        <?php if (tienePermiso($conn, 'prestamos.crear')): ?>
            <p>
                <a href="loan_form.php">Registrar nuevo préstamo</a>
            </p>
        <?php endif; ?>

    </body>
</html>
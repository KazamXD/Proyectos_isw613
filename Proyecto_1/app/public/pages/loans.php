<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';

requerirPermiso($conn, 'prestamos.ver');

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
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<p>
    <a href="dashboard.php">Volver al dashboard</a>
</p>


</body>
</html>
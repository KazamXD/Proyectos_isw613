<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';

requerirPermiso($conn, 'equipos.ver');


$stmt = $conn->prepare("
    SELECT
        e.id_equipo,
        e.codigo,
        c.categoria_nombre,
        e.nombre_estado
    FROM equipos e
    INNER JOIN categorias c
        ON e.id_categoria = c.categoria_id
    WHERE e.eliminado_en IS NULL
    ORDER BY e.id_equipo ASC
");

$stmt->execute();

$equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);


?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Consulta de Equipos</title>
</head>
<body>

    <h1>Equipos registrados</h1>

    <table border="1">
        <thead>
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
                    <td><?= (int)$equipo['id_equipo'] ?></td>
                    <td><?= htmlspecialchars($equipo['codigo']) ?></td>
                    <td><?= htmlspecialchars($equipo['categoria_nombre']) ?></td>
                    <td><?= htmlspecialchars($equipo['nombre_estado']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p>
        <a href="dashboard.php">Volver al dashboard</a>
    </p>

</body>
</html>

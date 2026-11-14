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

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitudes</title>
</head>
<body>

    <h1>Solicitudes de equipos</h1>

    <!-- Mostrar mensaje de éxito si la solicitud fue aprobada -->
    <?php if (($_GET['success'] ?? '') === 'aprobada'): ?>
        <p style="color: green;">
            Solicitud aprobada correctamente.
        </p>
    <?php endif; ?>

    <!-- Mostrar mensaje de éxito si la solicitud fue rechazada -->
     <?php if (($_GET['success'] ?? '') === 'rechazada'): ?>
        <p style="color: green;">
            Solicitud rechazada correctamente.
        </p>
    <?php endif; ?>

    <table border="1">
        <thead>
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
                    <!-- Muestra el ID de la solicitud -->
                    <td><?= (int) $solicitud['id_solicitud'] ?></td>

                    <!-- Muestra el nombre del usuario en la tabla -->
                    <td><?= htmlspecialchars(
                        $solicitud['nom_usuario'],
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        'UTF-8'
                    ) ?></td>

                    <!-- Muestra la fecha de inicio de la solicitud -->
                    <td><?= htmlspecialchars(
                        $solicitud['fecha_inicio'],
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        'UTF-8'
                    ) ?></td>

                    <!-- Muestra la fecha de devolución de la solicitud -->
                    <td><?= htmlspecialchars(
                        $solicitud['fecha_fin'],
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        'UTF-8'
                    ) ?></td>

                    <!-- Muestra el estado de la solicitud -->
                    <td><?= htmlspecialchars(
                        $solicitud['estado'],
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        'UTF-8'
                    ) ?></td>

                    <!-- Muestra los botones de acción aprobar/rechazar -->
                    <td>
                        <?php if ($solicitud['estado'] === 'pendiente'): ?>

                            <?php if ($puedeAprobar): ?>
                                <form
                                    action="../actions/approve_request.php"
                                    method="POST"
                                    style="display:inline;"
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
                                    <button type="submit">Aprobar</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($puedeRechazar): ?>
                                <form
                                    action="../actions/reject_request.php"
                                    method="POST"
                                    style="display:inline;"
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
                                    <label for="motivo_<?= (int)$solicitud['id_solicitud'] ?>"> Motivo: </label>
                                    <input
                                        type="text"
                                        id="motivo_<?= (int)$solicitud['id_solicitud'] ?>"
                                        name="motivo_rechazo"
                                        maxlength="255"
                                        placeholder="Motivo del rechazo"
                                        required
                                    >
                                    <button type="submit">Rechazar</button>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                            Sin acciones
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p>
        <a href="dashboard.php">Volver al dashboard</a>
    </p>
</body>
</html>
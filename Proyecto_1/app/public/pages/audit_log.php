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

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Bitácora del Sistema</title>
</head>

<body>

    <h1>Bitácora de Actividades</h1>

    <p>
        Consulta las operaciones realizadas por los usuarios.
    </p>

    <form method="GET" action="audit_log.php">
        <label for="accion">Filtrar por acción:</label>
        <select name="accion" id="accion">
            <option value="">Todas las acciones</option>
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
        <button type="submit">Filtrar</button>
        <a href="audit_log.php">Limpiar</a>

    </form>

    <hr>

    <table border="1" cellpadding="8">

        <thead>
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
                    <td>
                        <?= htmlspecialchars(
                            $registro['nom_usuario'] ?? 'Sistema',
                            ENT_QUOTES | ENT_SUBSTITUTE,
                            'UTF-8'
                        ) ?>
                    </td>
                    <td>
                        <?= htmlspecialchars(
                            $registro['accion'],
                            ENT_QUOTES | ENT_SUBSTITUTE,
                            'UTF-8'
                        ) ?>
                    </td>
                    <td>
                        <?= htmlspecialchars(
                            $registro['entidad'],
                            ENT_QUOTES | ENT_SUBSTITUTE,
                            'UTF-8'
                        ) ?>
                    </td>
                    <td>
                        <?= (int) ($registro['entidad_id'] ?? 0) ?>
                    </td>
                    <td>
                        <pre><?= htmlspecialchars(
                            $registro['valores_antes'] ?? '—',
                            ENT_QUOTES | ENT_SUBSTITUTE,
                            'UTF-8'
                        ) ?></pre>
                    </td>
                    <td>
                        <pre><?= htmlspecialchars(
                            $registro['valores_despues'] ?? '—',
                            ENT_QUOTES | ENT_SUBSTITUTE,
                            'UTF-8'
                        ) ?></pre>
                    </td>
                    <td>
                        <?= htmlspecialchars(
                            $registro['ip'] ?? '—',
                            ENT_QUOTES | ENT_SUBSTITUTE,
                            'UTF-8'
                        ) ?>
                    </td>
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
    <?php if (empty($registros)): ?>

        <p>No se encontraron registros.</p>

    <?php endif; ?>
    <p>
        <a href="reports.php">Volver a reportes</a>
    </p>
</body>
</html>
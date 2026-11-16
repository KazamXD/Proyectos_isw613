<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';

// Solo usuarios con permiso pueden consultar reportes
requerirPermiso($conn, 'reportes.ver');

// Obtener búsqueda
$busqueda = $_GET['buscar'] ?? '';

if (!is_string($busqueda)) {
    $busqueda = '';
}

$busqueda = trim($busqueda);

// Construir filtros
$filtro = "
    WHERE p.fecha_limite < NOW()
      AND p.fecha_devolucion IS NULL
";

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

    $parametros = [
        'nombre' => $valor,
        'apellido' => $valor,
        'usuario' => $valor
    ];
}

// Consultar préstamos retrasados
$stmt = $conn->prepare("
    SELECT
        p.id_prestamo,
        u.nombre,
        u.apellido,
        u.nom_usuario,
        p.fecha_entrega,
        p.fecha_limite,
        TIMESTAMPDIFF(
            HOUR,
            p.fecha_limite,
            NOW()
        ) AS horas_retraso,

        GROUP_CONCAT(
            e.codigo
            ORDER BY e.codigo
            SEPARATOR ', '
        ) AS equipos

    FROM prestamos p

    INNER JOIN usuarios u
        ON p.usuario_id = u.id

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
        p.fecha_entrega,
        p.fecha_limite

    ORDER BY p.fecha_limite ASC
");

$stmt->execute($parametros);

$prestamos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Total de préstamos retrasados
$totalRetrasados = count($prestamos);

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Reporte de Préstamos Retrasados</title>
</head>

<body>
    <h1>Reporte de Préstamos Retrasados</h1>
    <h2>Resumen</h2>
    <p>
        Total de préstamos retrasados:
        <strong><?= $totalRetrasados ?></strong>
    </p>
    <hr>

    <h2>Buscar préstamos</h2>

    <form method="GET" action="report_overdue.php">
        <label for="buscar">Buscar usuario:</label>
        <input
            type="text"
            id="buscar"
            name="buscar"
            placeholder="Nombre, apellido o usuario"
            value="<?= htmlspecialchars(
                $busqueda,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            ) ?>"
        >
        <button type="submit">Buscar</button>
        <a href="report_overdue.php">Limpiar búsqueda</a>

    </form>
    <hr>

    <p>
        <a href="../actions/export_loans.php?<?= http_build_query([
            'tipo' => 'overdue',
            'buscar' => $busqueda
        ]) ?>">
            Descargar CSV
        </a>
    </p>

    <h2>Detalle de préstamos retrasados</h2>

    <table border="1" cellpadding="8">
        <thead>
            <tr>
                <th>ID préstamo</th>
                <th>Usuario</th>
                <th>Nombre completo</th>
                <th>Equipos</th>
                <th>Fecha de préstamo</th>
                <th>Fecha límite</th>
                <th>Horas de retraso</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($prestamos as $prestamo): ?>
                <tr>
                    <td>
                        <?= (int) $prestamo['id_prestamo'] ?>
                    </td>
                    <td>
                        <?= htmlspecialchars(
                            $prestamo['nom_usuario'],
                            ENT_QUOTES | ENT_SUBSTITUTE,
                            'UTF-8'
                        ) ?>
                    </td>
                    <td>
                        <?= htmlspecialchars(
                            $prestamo['nombre'] . ' ' . $prestamo['apellido'],
                            ENT_QUOTES | ENT_SUBSTITUTE,
                            'UTF-8'
                        ) ?>
                    </td>
                    <td>
                        <?= htmlspecialchars(
                            $prestamo['equipos'] ?? 'Sin equipos',
                            ENT_QUOTES | ENT_SUBSTITUTE,
                            'UTF-8'
                        ) ?>
                    </td>
                    <td>
                        <?= htmlspecialchars(
                            $prestamo['fecha_entrega'],
                            ENT_QUOTES | ENT_SUBSTITUTE,
                            'UTF-8'
                        ) ?>
                    </td>
                    <td>
                        <?= htmlspecialchars(
                            $prestamo['fecha_limite'],
                            ENT_QUOTES | ENT_SUBSTITUTE,
                            'UTF-8'
                        ) ?>
                    </td>
                    <td>
                        <?= (int) $prestamo['horas_retraso'] ?> horas
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (empty($prestamos)): ?>
        <p>No se encontraron préstamos retrasados.</p>
    <?php endif; ?>
    <br>
    <a href="reports.php">Volver a reportes</a>
</body>
</html>
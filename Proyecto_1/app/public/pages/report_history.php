<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';

requerirPermiso($conn, 'reportes.ver');

// Obtener filtros
$busqueda = $_GET['buscar'] ?? '';
$estado = $_GET['estado'] ?? '';

if (!is_string($busqueda)) {
    $busqueda = '';
}

if (!is_string($estado)) {
    $estado = '';
}

$busqueda = trim($busqueda);

// Estados permitidos
$estadosPermitidos = [
    'prestado',
    'devuelto',
    'retrasado'
];

if (!in_array($estado, $estadosPermitidos, true)) {
    $estado = '';
}

// Construir filtros
$filtro = "WHERE 1=1";
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

    $parametros['nombre'] = $valor;
    $parametros['apellido'] = $valor;
    $parametros['usuario'] = $valor;
}

if ($estado !== '') {
    $filtro .= " AND p.estado = :estado";
    $parametros['estado'] = $estado;
}

// Consultar historial
$stmt = $conn->prepare("
    SELECT
        p.id_prestamo,
        u.nombre,
        u.apellido,
        u.nom_usuario,
        o.nombre AS operador,
        p.fecha_entrega,
        p.fecha_limite,
        p.fecha_devolucion,
        p.estado,

        GROUP_CONCAT(
            e.codigo
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

    $filtro

    GROUP BY
        p.id_prestamo,
        u.nombre,
        u.apellido,
        u.nom_usuario,
        o.nombre,
        p.fecha_entrega,
        p.fecha_limite,
        p.fecha_devolucion,
        p.estado

    ORDER BY p.fecha_entrega DESC
");

$stmt->execute($parametros);

$prestamos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalRegistros = count($prestamos);

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Historial de Préstamos</title>
</head>

<body>

    <h1>Historial de Préstamos</h1>

    <p>
        Total de registros encontrados:
        <strong><?= $totalRegistros ?></strong>
    </p>

    <hr>

    <h2>Filtros</h2>

    <form method="GET" action="report_history.php">
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
        <label for="estado">Estado:</label>
        <select name="estado" id="estado">
            <option value="">Todos los estados</option>
            <?php foreach ($estadosPermitidos as $opcion): ?>
                <option
                    value="<?= htmlspecialchars(
                        $opcion,
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        'UTF-8'
                    ) ?>"
                    <?= $estado === $opcion ? 'selected' : '' ?>
                >
                    <?= ucfirst($opcion) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Filtrar</button>
        <a href="report_history.php">Limpiar filtros</a>
    </form>

    <p>
        <a href="../actions/export_loans.php?<?= http_build_query([
            'tipo' => 'history',
            'buscar' => $busqueda,
            'estado' => $estado
        ]) ?>">
            Descargar CSV
        </a>
    </p>
    <hr>

    <h2>Detalle del historial</h2>

    <table border="1" cellpadding="8">

        <thead>
            <tr>
                <th>ID</th>
                <th>Usuario</th>
                <th>Nombre completo</th>
                <th>Operador</th>
                <th>Equipos</th>
                <th>Fecha préstamo</th>
                <th>Fecha límite</th>
                <th>Fecha devolución</th>
                <th>Estado</th>
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
                            $prestamo['operador'],
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
                        <?= htmlspecialchars(
                            $prestamo['fecha_devolucion'] ?? 'Pendiente',
                            ENT_QUOTES | ENT_SUBSTITUTE,
                            'UTF-8'
                        ) ?>
                    </td>
                    <td>
                        <?= htmlspecialchars(
                            $prestamo['estado'],
                            ENT_QUOTES | ENT_SUBSTITUTE,
                            'UTF-8'
                        ) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (empty($prestamos)): ?>

        <p>No se encontraron préstamos con esos criterios.</p>

    <?php endif; ?>
    <br>
    <a href="reports.php">Volver a reportes</a>
</body>
</html>
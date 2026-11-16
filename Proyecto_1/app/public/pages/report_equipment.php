<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';

// Solo usuarios autorizados pueden consultar reportes
requerirPermiso($conn, 'reportes.ver');
requerirPermiso($conn, 'equipos.ver');

// Obtener filtros
$categoriaId = filter_var(
    $_GET['categoria'] ?? 0,
    FILTER_VALIDATE_INT
);

$estado = $_GET['estado'] ?? '';

// Estados permitidos
$estadosPermitidos = [
    'Disponible',
    'Prestado',
    'Mantenimiento'
];

// Validar filtros
if ($categoriaId === false || $categoriaId < 0) {
    $categoriaId = 0;
}

if (!is_string($estado) || !in_array($estado, $estadosPermitidos, true)) {
    $estado = '';
}

// Consultar categorías
$stmt = $conn->prepare("
    SELECT categoria_id, categoria_nombre
    FROM categorias
    WHERE eliminado_en IS NULL
    ORDER BY categoria_nombre ASC
");

$stmt->execute();
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Construir filtros del inventario
$filtro = "WHERE e.eliminado_en IS NULL";
$parametros = [];

if ($categoriaId > 0) {
    $filtro .= " AND e.id_categoria = :categoria";
    $parametros['categoria'] = $categoriaId;
}

if ($estado !== '') {
    $filtro .= " AND e.nombre_estado = :estado";
    $parametros['estado'] = $estado;
}

// Consultar inventario
$stmt = $conn->prepare("
    SELECT
        e.id_equipo,
        e.codigo,
        c.categoria_nombre,
        e.nombre_estado
    FROM equipos e
    INNER JOIN categorias c
        ON e.id_categoria = c.categoria_id
    $filtro
    ORDER BY c.categoria_nombre ASC, e.codigo ASC
");

$stmt->execute($parametros);
$equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas del inventario filtrado
$totalEquipos = count($equipos);

$disponibles = 0;
$prestados = 0;
$mantenimiento = 0;

foreach ($equipos as $equipo) {
    switch ($equipo['nombre_estado']) {
        case 'Disponible':
            $disponibles++;
            break;

        case 'Prestado':
            $prestados++;
            break;

        case 'Mantenimiento':
            $mantenimiento++;
            break;
    }
}

?>

<!DOCTYPE html>
<html lang="es">

    <head>
        <meta charset="UTF-8">
        <title>Reporte de Inventario</title>
    </head>

    <body>
        <h1>Reporte de Inventario de Equipos</h1>

        <h2>Resumen del inventario</h2>

        <?php if (tienePermiso($conn, 'equipos.ver')): ?>
        <p>
            <a href="../actions/export_equipment.php">
                Exportar equipos a CSV
            </a>
        </p>
    <?php endif; ?>

        <ul>
            <li>Total de equipos: <?= $totalEquipos ?></li>
            <li>Disponibles: <?= $disponibles ?></li>
            <li>Prestados: <?= $prestados ?></li>
            <li>En mantenimiento: <?= $mantenimiento ?></li>
        </ul>

        <hr>

        <h2>Filtros</h2>

        <form method="GET" action="report_equipment.php">
            <label for="categoria">Categoría:</label>
            <select name="categoria" id="categoria">
                <option value="0">Todas las categorías</option>
                <?php foreach ($categorias as $categoria): ?>
                    <option
                        value="<?= (int) $categoria['categoria_id'] ?>"
                        <?= $categoriaId === (int) $categoria['categoria_id']
                            ? 'selected'
                            : '' ?>
                    >
                        <?= htmlspecialchars(
                            $categoria['categoria_nombre'],
                            ENT_QUOTES | ENT_SUBSTITUTE,
                            'UTF-8'
                        ) ?>
                    </option>
                <?php endforeach; ?>
            </select>
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
                        <?= htmlspecialchars(
                            $opcion,
                            ENT_QUOTES | ENT_SUBSTITUTE,
                            'UTF-8'
                        ) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Filtrar</button>
            <a href="report_equipment.php">Limpiar filtros</a>
        </form>
        <hr>

        <h2>Detalle de equipos</h2>

        <table border="1" cellpadding="8">
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
                        <td>
                            <?= (int) $equipo['id_equipo'] ?>
                        </td>
                        <td>
                            <?= htmlspecialchars(
                                $equipo['codigo'],
                                ENT_QUOTES | ENT_SUBSTITUTE,
                                'UTF-8'
                            ) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars(
                                $equipo['categoria_nombre'],
                                ENT_QUOTES | ENT_SUBSTITUTE,
                                'UTF-8'
                            ) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars(
                                $equipo['nombre_estado'],
                                ENT_QUOTES | ENT_SUBSTITUTE,
                                'UTF-8'
                            ) ?>
                        </td>

                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (empty($equipos)): ?>
            <p>No se encontraron equipos con los filtros seleccionados.</p>
        <?php endif; ?>
        <br>
        <a href="dashboard.php">Volver al dashboard</a>

    </body>
</html>
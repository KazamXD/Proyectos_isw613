<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';

requerirPermiso($conn, 'equipos.ver');
$tokenCSRF = generarTokenCSRF();
$puedeEliminar = tienePermiso($conn, 'equipos.eliminar');



//Ordenar datos de la tabla
// Obtener el orden seleccionado
$orden = $_GET['orden'] ?? 'categoria';

// Lista de opciones permitidas
$ordenesPermitidos = [
    'id_asc' => 'e.id_equipo ASC',
    'id_desc' => 'e.id_equipo DESC',
    'categoria' => 'c.categoria_nombre ASC, e.codigo ASC',
    'codigo_asc' => 'e.codigo ASC',
    'codigo_desc' => 'e.codigo DESC',
    'estado' => 'e.nombre_estado ASC, e.codigo ASC'
];

// Validar la opción recibida
if (!is_string($orden) || !isset($ordenesPermitidos[$orden])) {
    $orden = 'categoria';
}
$ordenSQL = $ordenesPermitidos[$orden];

// Obtener el texto de búsqueda
$busqueda = $_GET['buscar'] ?? '';

if (!is_string($busqueda)) {
    $busqueda = '';
}

$busqueda = trim($busqueda);

// Preparar los filtros
$filtro = "WHERE e.eliminado_en IS NULL";
$parametros = [];

if ($busqueda !== '') {

    $filtro .= "
        AND (
            e.codigo LIKE :codigo
            OR c.categoria_nombre LIKE :categoria
            OR e.nombre_estado LIKE :estado
        )
    ";

    $valorBusqueda = "%$busqueda%";

    $parametros = [
        'codigo' => $valorBusqueda,
        'categoria' => $valorBusqueda,
        'estado' => $valorBusqueda
    ];
}

//Consulta de equipos
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
    ORDER BY $ordenSQL
");
$stmt->execute($parametros);
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

    <?php if (($_GET['success'] ?? '') === 'creado'): ?>
        <p style="color: green;">
            Equipo registrado correctamente.
        </p>
    <?php endif; ?>

    <?php if (($_GET['success'] ?? '') === 'editado'): ?>
        <p>Equipo actualizado correctamente.</p>
    <?php elseif (($_GET['success'] ?? '') === 'sin_cambios'): ?>
        <p>No se realizaron cambios.</p>
    <?php endif; ?>

    <form method="GET" action="equipment.php">

        <label for="buscar">Buscar equipo:</label>
        <input
            type="text"
            id="buscar"
            name="buscar"
            placeholder="Código, categoría o estado"
            value="<?= htmlspecialchars(
                $busqueda,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            ) ?>"
        >

        <label for="orden">Ordenar equipos por:</label>
        <select name="orden" id="orden">
            <option value="categoria" <?= $orden === 'categoria' ? 'selected' : '' ?>>
                Categoría (A-Z)
            </option>
            <option value="id_asc" <?= $orden === 'id_asc' ? 'selected' : '' ?>>
                ID (ascendente)
            </option>
            <option value="id_desc" <?= $orden === 'id_desc' ? 'selected' : '' ?>>
                ID (descendente)
            </option>
            <option value="codigo_asc" <?= $orden === 'codigo_asc' ? 'selected' : '' ?>>
                Código (A-Z)
            </option>
            <option value="codigo_desc" <?= $orden === 'codigo_desc' ? 'selected' : '' ?>>
                Código (Z-A)
            </option>
            <option value="estado" <?= $orden === 'estado' ? 'selected' : '' ?>>
                Estado
            </option>
        </select>
        <button type="submit">Filtrar</button>
    </form>

    <table border="1">
        <thead>
            <tr>
                <th>ID</th>
                <th>Código</th>
                <th>Categoría</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($equipos as $equipo): ?>
                <tr>
                    <td><?= (int)$equipo['id_equipo'] ?></td>
                    <td><?= htmlspecialchars($equipo['codigo'],ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8') ?></td>
                    <td><?= htmlspecialchars($equipo['categoria_nombre'],ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8') ?></td>
                    <td><?= htmlspecialchars($equipo['nombre_estado'],ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8'   ) ?></td>

                    <td>
                        <?php if (tienePermiso($conn, 'equipos.editar')): ?>
                            <a href="edit_equipment.php?id=<?= (int)$equipo['id_equipo'] ?>">
                                Editar
                            </a>
                        <?php endif; ?>

                        <?php if ($puedeEliminar): ?>
                            <form
                                action="../actions/delete_equipment.php"
                                method="POST"
                                style="display: inline;"
                                onsubmit="return confirm('¿Seguro que deseas eliminar este equipo?');"
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
                                    name="equipo_id"
                                    value="<?= (int)$equipo['id_equipo'] ?>"
                                >
                                <button type="submit">Eliminar</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (empty($equipos)): ?>
        <p>No se encontraron equipos con esos criterios.</p>
    <?php endif; ?>

    <?php if (($_GET['success'] ?? '') === 'eliminado'): ?>
        <p style="color: green;">
            Equipo eliminado correctamente.
        </p>
    <?php endif; ?>

    <p>
        <a href="dashboard.php">Volver al dashboard</a>
    </p>    

    <?php if (tienePermiso($conn, 'equipos.crear')): ?>
        <p>
            <a href="equipment_form.php">Registrar nuevo equipo</a>
        </p>
    <?php endif; ?>

</body>
</html>

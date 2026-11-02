
<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';

// Solo usuarios con este permiso
requerirPermiso($conn, 'usuarios.ver');

// Obtener búsqueda y paginación
$busqueda = trim($_GET['buscar'] ?? '');
$porPagina = (int) ($_GET['cantidad'] ?? 10);
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));

// Cantidades permitidas
if (!in_array($porPagina, [5, 10, 20, 50], true)) {
    $porPagina = 10;
}

// Construir el filtro de búsqueda

// Construir el filtro de búsqueda
$filtro = "WHERE desactivado_en IS NULL";
$parametros = [];

if ($busqueda !== '') {
    $filtro .= " AND (
        nombre LIKE :nombre
        OR apellido LIKE :apellido
        OR nom_usuario LIKE :usuario
        OR email LIKE :email
    )";

    $valorBusqueda = "%$busqueda%";

    $parametros = [
        'nombre' => $valorBusqueda,
        'apellido' => $valorBusqueda,
        'usuario' => $valorBusqueda,
        'email' => $valorBusqueda
    ];
}

// Contar registros para calcular páginas
$stmt = $conn->prepare(
    "SELECT COUNT(*) FROM usuarios $filtro"
);
$stmt->execute($parametros);

$totalRegistros = (int) $stmt->fetchColumn();
$totalPaginas = max(1, (int) ceil($totalRegistros / $porPagina));

$pagina = min($pagina, $totalPaginas);
$offset = ($pagina - 1) * $porPagina;

// Obtener los usuarios de la página actual
$stmt = $conn->prepare("
    SELECT id, nombre, nom_usuario, rol_id
    FROM usuarios
    $filtro
    ORDER BY id ASC
    LIMIT $porPagina OFFSET $offset
");

$stmt->execute($parametros);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Usuarios</title>
</head>
<body>

<h1>Gestión de Usuarios</h1>

<?php if (($_GET['success'] ?? '') === 'creado'): ?>
    <p>Usuario registrado correctamente.</p>
<?php endif; ?>

<form method="GET">
    <input
        type="text"
        name="buscar"
        placeholder="Buscar usuario..."
        value="<?= htmlspecialchars($busqueda) ?>"
    >

    <select name="cantidad">
        <?php foreach ([5, 10, 20, 50] as $cantidad): ?>
            <option value="<?= $cantidad ?>"
                <?= $porPagina === $cantidad ? 'selected' : '' ?>>
                <?= $cantidad ?> registros
            </option>
        <?php endforeach; ?>
    </select>

    <button type="submit">Buscar</button>
</form>

<hr>

<table border="1" cellpadding="8">
    <thead>
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Usuario</th>
            <th>ID Rol</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($usuarios as $usuario): ?>
            <tr>
                <td><?= (int) $usuario['id'] ?></td>
                <td><?= htmlspecialchars($usuario['nombre']) ?></td>
                <td><?= htmlspecialchars($usuario['nom_usuario']) ?></td>
                <td><?= (int) $usuario['rol_id'] ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<p>
    Página <?= $pagina ?> de <?= $totalPaginas ?>
    (<?= $totalRegistros ?> registros)
</p>

<?php if ($pagina > 1): ?>
    <a href="?<?= http_build_query([
        'buscar' => $busqueda,
        'cantidad' => $porPagina,
        'pagina' => $pagina - 1
    ]) ?>">Anterior</a>
<?php endif; ?>

<?php if ($pagina < $totalPaginas): ?>
    <a href="?<?= http_build_query([
        'buscar' => $busqueda,
        'cantidad' => $porPagina,
        'pagina' => $pagina + 1
    ]) ?>">Siguiente</a>
<?php endif; ?>

<p><a href="dashboard.php">Volver al dashboard</a></p>

    <?php if (tienePermiso($conn, 'usuarios.crear')): ?>
        <p>
            <a href="user_form.php">Crear nuevo usuario</a>
        </p>
    <?php endif; ?>

</body>
</html>

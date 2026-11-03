
<?php
// dashboard.php

require_once __DIR__ . '/../../includes/auth.php';

// Exigir que el usuario haya iniciado sesión
requerirLogin();

// Conexión a la base de datos
require_once __DIR__ . '/../../config/connection.php';

// Consultar permisos del rol del usuario
$stmt = $conn->prepare("
    SELECT p.clave
    FROM rol_permiso rp
    JOIN permisos p ON rp.permiso_id = p.id_permiso
    WHERE rp.rol_id = ?
");

$stmt->execute([$_SESSION['rol_id']]);

$permisos = $stmt->fetchAll(PDO::FETCH_COLUMN);

//Consultar permisos
$puedeCrearUsuarios = tienePermiso($conn, 'usuarios.crear');
$puedeCrearEquipos = tienePermiso($conn, 'equipos.crear');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
</head>
<body>

    <h1>
        Bienvenido,
        <?php echo htmlspecialchars($_SESSION['nombre_usuario']); ?>!
    </h1>

    <p>Has iniciado sesión correctamente.</p>

    <h3>Tus permisos actuales:</h3>

    <ul>
        <?php foreach ($permisos as $permiso): ?>
            <li>
                <?php echo htmlspecialchars($permiso); ?>
            </li>
        <?php endforeach; ?>
    </ul>

    <h3>Prueba de permisos</h3>

        <?php if ($puedeCrearUsuarios): ?>
            <p>Puedes administrar usuarios.</p>
        <?php endif; ?>

        <?php if ($puedeCrearEquipos): ?>
            <p>Puedes registrar equipos.</p>
        <?php endif; ?>

    <a href="../actions/logout.php">Cerrar sesión</a>

    <!-- Ver usuarios -->
    <?php if (tienePermiso($conn, 'usuarios.ver')): ?>
        <a href="users.php">Gestionar usuarios</a>
    <?php endif; ?>

    <!-- Ver equipos -->
    <?php if (tienePermiso($conn, 'equipos.ver')): ?>
        <a href="equipment.php">Consultar equipos</a>
    <?php endif; ?>

    <!-- Ver préstamos -->
    <?php if (tienePermiso($conn, 'prestamos.ver')): ?>
        <a href="loans.php">Consultar préstamos</a>
    <?php endif; ?>

    <!-- Ver categorías -->
    <?php if (tienePermiso($conn, 'categorias.ver')): ?>
        <a href="categories.php">Gestionar categorías</a>
    <?php endif; ?>

</body>
</html>

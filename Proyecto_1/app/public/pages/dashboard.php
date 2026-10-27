<?php
// dashboard.php
session_start();

// Si no hay sesión, va para el login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

// Consultar los permisos del usuario
require __DIR__ . '/../../config/connection.php';
$stmt = $conn->prepare("
    SELECT p.clave 
    FROM rol_permiso rp 
    JOIN permisos p ON rp.permiso_id = p.id_permiso 
    WHERE rp.rol_id = ?
");
$stmt->execute([$_SESSION['rol_id']]);
$permisos = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
</head>
<body>
    <h1>Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre_usuario']); ?>!</h1>
    <p>Has iniciado sesión correctamente.</p>
    
    <h3>Tus permisos actuales:</h3>
    <ul>
        <?php foreach ($permisos as $permiso): ?>
            <li><?php echo htmlspecialchars($permiso); ?></li>
        <?php endforeach; ?>
    </ul>

    <br>
    <a href="logout.php">Cerrar Sesión</a>
</body>
</html>
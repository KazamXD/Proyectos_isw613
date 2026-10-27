
<?php
session_start();

// Si ya inició sesión, ir al dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: pages/dashboard.php');
    exit;
}

$error = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login - Sistema de Préstamos</title>
</head>
<body>
    <h2>Iniciar sesión</h2>

    <?php if ($error === 'credenciales'): ?>
        <p style="color: red;">Usuario o contraseña incorrectos.</p>
    <?php elseif ($error === 'campos'): ?>
        <p style="color: red;">Completa todos los campos.</p>
    <?php elseif ($error === 'sistema'): ?>
        <p style="color: red;">No se pudo iniciar sesión. Intenta nuevamente.</p>
    <?php endif; ?>

    <form method="POST" action="actions/login.php">
        <input
            type="text"
            name="nom_usuario"
            placeholder="Nombre de usuario"
            required
        >

        <input
            type="password"
            name="password"
            placeholder="Contraseña"
            required
        >

        <button type="submit">Entrar</button>
    </form>
</body>
</html>

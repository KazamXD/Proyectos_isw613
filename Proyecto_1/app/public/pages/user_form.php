
<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';

// Solo usuarios autorizados pueden crear usuarios
requerirPermiso($conn, 'usuarios.crear');

// Obtener los roles disponibles
$stmt = $conn->prepare("
    SELECT id_rol, nombre_rol
    FROM rol
    ORDER BY nombre_rol ASC
");

$stmt->execute();

$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Usuario</title>
</head>

<body>
<h1>Registrar nuevo usuario</h1>
    <?php
    $error = $_GET['error'] ?? '';
    $mensajes = [
        'campos' => 'Revisa los campos del formulario.',
        'longitud' => 'El nombre o usuario es demasiado largo.',
        'duplicado' => 'Ese nombre de usuario ya existe.',
        'rol' => 'El rol seleccionado no es válido.',
        'sistema' => 'Ocurrió un error al guardar el usuario.'
    ];
    if (isset($mensajes[$error])) {
        echo '<p>' . htmlspecialchars($mensajes[$error]) . '</p>';
    }
?>
<form action="../actions/create_user.php" method="POST">
    <div>
        <label for="nombre">Nombre:</label>
        <input
            type="text"
            id="nombre"
            name="nombre"
            maxlength="100"
            required
        >
    </div>
    <br>
    <div>
    <label for="apellido">Apellido:</label>
    <input
        type="text"
        id="apellido"
        name="apellido"
        maxlength="50"
        required
    >
    </div>
    <br>
    <div>
        <label for="email">Correo electrónico:</label>
        <input
            type="email"
            id="email"
            name="email"
            maxlength="100"
            required
        >
    </div>
    <br>
    <div>
        <label for="nom_usuario">Nombre de usuario:</label>
        <input
            type="text"
            id="nom_usuario"
            name="nom_usuario"
            maxlength="50"
            required
        >
    </div>
    <br>
    <div>
        <label for="password">Contraseña:</label>
        <input
            type="password"
            id="password"
            name="password"
            minlength="8"
            required
        >
    </div>
    <br>
    <div>
        <label for="rol_id">Rol:</label>
        <select id="rol_id" name="rol_id" required>
            <option value="">Seleccione un rol</option>
            <?php foreach ($roles as $rol): ?>
                <option value="<?= (int) $rol['id_rol'] ?>">
                    <?= htmlspecialchars($rol['nombre_rol']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <br>
    <button type="submit">Guardar usuario</button>
    <a href="users.php">Cancelar</a>
</form>
</body>
</html>

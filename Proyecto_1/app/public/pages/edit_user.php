<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';

requerirPermiso($conn, 'usuarios.editar');

// Obtener el ID del usuario
$usuarioId = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$usuarioId || $usuarioId <= 0) {
    http_response_code(400);
    exit('Usuario no válido.');
}

// Consultar los datos del usuario
$stmt = $conn->prepare("
    SELECT
        id,
        nombre,
        apellido,
        email,
        nom_usuario,
        rol_id
    FROM usuarios
    WHERE id = :id
      AND desactivado_en IS NULL
");

$stmt->execute(['id' => $usuarioId]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    http_response_code(404);
    exit('Usuario no encontrado.');
}

// Obtener los roles disponibles
$stmt = $conn->prepare("
    SELECT id_rol, nombre_rol
    FROM rol
    ORDER BY nombre_rol ASC
");

$stmt->execute();
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tokenCSRF = generarTokenCSRF();

$errores = [
    'campos' => 'Revisa los datos ingresados.',
    'duplicado' => 'El correo o nombre de usuario ya está registrado.',
    'rol' => 'El rol seleccionado no es válido.',
    'sistema' => 'No se pudo actualizar el usuario.',
    'propio_rol' => 'No puedes cambiar tu propio rol.'
];

$error = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar usuario</title>
</head>
<body>

<h1>Editar usuario</h1>

<?php if (is_string($error) && isset($errores[$error])): ?>
    <p style="color: red;">
        <?= htmlspecialchars(
            $errores[$error],
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ) ?>
    </p>
<?php endif; ?>

<form action="../actions/update_user.php" method="POST">

    <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars($tokenCSRF, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
    >

    <input
        type="hidden"
        name="usuario_id"
        value="<?= (int)$usuario['id'] ?>"
    >

    <label for="nombre">Nombre:</label>
    <input
        type="text"
        id="nombre"
        name="nombre"
        maxlength="50"
        value="<?= htmlspecialchars($usuario['nombre'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
        required
    >

    <br><br>

    <label for="apellido">Apellido:</label>
    <input
        type="text"
        id="apellido"
        name="apellido"
        maxlength="50"
        value="<?= htmlspecialchars($usuario['apellido'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
        required
    >

    <br><br>

    <label for="email">Correo electrónico:</label>
    <input
        type="email"
        id="email"
        name="email"
        maxlength="100"
        value="<?= htmlspecialchars($usuario['email'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
        required
    >

    <br><br>

    <label for="nom_usuario">Nombre de usuario:</label>
    <input
        type="text"
        id="nom_usuario"
        name="nom_usuario"
        maxlength="50"
        value="<?= htmlspecialchars($usuario['nom_usuario'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
        required
    >

    <br><br>

    <label for="rol_id">Rol:</label>
    <select name="rol_id" id="rol_id" required>
        <?php foreach ($roles as $rol): ?>
            <option
                value="<?= (int)$rol['id_rol'] ?>"
                <?= (int)$rol['id_rol'] === (int)$usuario['rol_id'] ? 'selected' : '' ?>
            >
                <?= htmlspecialchars(
                    $rol['nombre_rol'],
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                ) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <br><br>

    <button type="submit">Guardar cambios</button>
</form>

<p><a href="users.php">Volver al listado</a></p>

</body>
</html>
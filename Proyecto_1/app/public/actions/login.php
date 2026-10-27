
<?php
session_start();

require_once __DIR__ . '/../../config/connection.php';

// Solo aceptar solicitudes del formulario
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

// Obtener los datos enviados
$nom_usuario = trim($_POST['nom_usuario'] ?? '');
$password = $_POST['password'] ?? '';

// Validar campos
if ($nom_usuario === '' || $password === '') {
    header('Location: ../index.php?error=campos');
    exit;
}

try {
    // Buscar al usuario activo
    $stmt = $conn->prepare(
        'SELECT id, nombre, rol_id, password_hash
         FROM usuarios
         WHERE nom_usuario = :nom_usuario
           AND activo = 1'
    );

    $stmt->execute([
        'nom_usuario' => $nom_usuario
    ]);

    $user = $stmt->fetch();

    // Verificar usuario y contraseña
    if (!$user || !password_verify($password, $user['password_hash'])) {
        header('Location: ../index.php?error=credenciales');
        exit;
    }

    // Renovar el identificador de sesión
    session_regenerate_id(true);

    // Guardar datos en la sesión
    $_SESSION['usuario_id'] = $user['id'];
    $_SESSION['nombre_usuario'] = $user['nombre'];
    $_SESSION['rol_id'] = $user['rol_id'];

    // Registrar inicio de sesión en la bitácora
    $stmt_log = $conn->prepare(
        "INSERT INTO bitacora (usuario_id, accion, entidad, ip)
         VALUES (?, 'LOGIN', 'usuarios', ?)"
    );

    $stmt_log->execute([
        $user['id'],
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);

    // Ir al dashboard
    header('Location: ../pages/dashboard.php');
    exit;

} catch (PDOException $e) {
    error_log('Error de login: ' . $e->getMessage());

    // Limpiar los datos de sesión si ocurre un error
    $_SESSION = [];

    header('Location: ../index.php?error=sistema');
    exit;
}

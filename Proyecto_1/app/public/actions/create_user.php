
<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';

// Verificar que el usuario tenga permiso
requerirPermiso($conn, 'usuarios.crear');

// Solo aceptar solicitudes POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/user_form.php');
    exit;
}

// Obtener los datos del formulario
$nombre = trim($_POST['nombre'] ?? '');
$apellido = trim($_POST['apellido'] ?? '');
$email = trim($_POST['email'] ?? '');
$nom_usuario = trim($_POST['nom_usuario'] ?? '');
$password = $_POST['password'] ?? '';
$rol_id = filter_var(
    $_POST['rol_id'] ?? null,
    FILTER_VALIDATE_INT
);

// Validar campos
if (
    $nombre === '' ||
    $apellido === '' ||
    $email === '' ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    $nom_usuario === '' ||
    strlen($password) < 8 ||
    $rol_id === false ||
    $rol_id === null ||
    $rol_id < 1
) {
    header('Location: ../pages/user_form.php?error=campos');
    exit;
}

// Validar longitudes
if (
    mb_strlen($nombre) > 100 ||
    mb_strlen($apellido) > 50 ||
    mb_strlen($email) > 100 ||
    mb_strlen($nom_usuario) > 50
) {
    header('Location: ../pages/user_form.php?error=longitud');
    exit;
}

try {
    // Verificar si el nombre de usuario ya existe
    
    
    // Verificar si el usuario o correo ya existen
    $stmt = $conn->prepare("
        SELECT nom_usuario, email
        FROM usuarios
        WHERE nom_usuario = :nom_usuario
           OR email = :email
        LIMIT 1
    ");

    $stmt->execute([
        'nom_usuario' => $nom_usuario,
        'email' => $email
    ]);

    $duplicado = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($duplicado) {
        header('Location: ../pages/user_form.php?error=duplicado');
        exit;
    }

    if ($stmt->fetchColumn() > 0) {
        header('Location: ../pages/user_form.php?error=duplicado');
        exit;
    }

    // Verificar que el rol exista
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM rol
        WHERE id_rol = ?
    ");

    $stmt->execute([$rol_id]);

    if ($stmt->fetchColumn() == 0) {
        header('Location: ../pages/user_form.php?error=rol');
        exit;
    }

    // Generar hash seguro de la contraseña
    $passwordHash = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    // Insertar usuario
    $stmt = $conn->prepare("
        INSERT INTO usuarios
            (nombre, apellido, email, nom_usuario, password_hash, rol_id)
        VALUES
            (:nombre, :apellido, :email, :nom_usuario, :password_hash, :rol_id)
    ");

    $stmt->execute([
        'nombre' => $nombre,
        'apellido' => $apellido,
        'email' => $email,
        'nom_usuario' => $nom_usuario,
        'password_hash' => $passwordHash,
        'rol_id' => $rol_id
    ]);

    // Volver al listado
    header('Location: ../pages/users.php?success=creado');
    exit;

} catch (PDOException $e) {
    error_log('Error al crear usuario: ' . $e->getMessage());

    header('Location: ../pages/user_form.php?error=sistema');
    exit;
}

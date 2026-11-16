
<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/remember.php';

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

    // Iniciar transacción
    $conn->beginTransaction();

    // Buscar usuario activo
    $stmt = $conn->prepare("
        SELECT
            id,
            nombre,
            rol_id,
            password_hash,
            intento_ingreso,
            bloqueado_hasta,
            (bloqueado_hasta > NOW()) AS bloqueo_activo
        FROM usuarios
        WHERE nom_usuario = :nom_usuario
          AND activo = 1
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        'nom_usuario' => $nom_usuario
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Comprobar si el usuario existe
    if (!$user) {

        $conn->commit();

        header('Location: ../index.php?error=credenciales');
        exit;
    }

    // Comprobar bloqueo temporal
    if ((int) $user['bloqueo_activo'] === 1) {

        $conn->commit();

        header('Location: ../index.php?error=credenciales');
        exit;
    }

    // Obtener intentos disponibles
    $intentos = (int) $user['intento_ingreso'];

    // Si el bloqueo anterior terminó, restaurar intentos
    if (
        $user['bloqueado_hasta'] !== null &&
        (int) $user['bloqueo_activo'] === 0
    ) {
        $intentos = 3;
    }

    // Verificar contraseña
    if (!password_verify($password, $user['password_hash'])) {

        // Descontar intento
        $intentos = max(0, $intentos - 1);
        if ($intentos === 0) {

            // Bloquear durante cinco minutos
            $stmt = $conn->prepare("
                UPDATE usuarios
                SET intento_ingreso = 0,
                    bloqueado_hasta = DATE_ADD(
                        NOW(),
                        INTERVAL 5 MINUTE
                    )
                WHERE id = :id
            ");

            $stmt->execute([
                'id' => $user['id']
            ]);

        } else {

            // Actualizar intentos restantes
            $stmt = $conn->prepare("
                UPDATE usuarios
                SET intento_ingreso = :intentos,
                    bloqueado_hasta = NULL
                WHERE id = :id
            ");

            $stmt->execute([
                'intentos' => $intentos,
                'id' => $user['id']
            ]);
        }

        $conn->commit();

        header('Location: ../index.php?error=credenciales');
        exit;
    }

    // Contraseña correcta: restaurar intentos
    $stmt = $conn->prepare("
        UPDATE usuarios
        SET intento_ingreso = 3,
            bloqueado_hasta = NULL
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $user['id']
    ]);

    $conn->commit();

    // Renovar identificador de sesión
    session_regenerate_id(true);

    // Guardar datos de sesión
    $_SESSION['usuario_id'] = $user['id'];
    $_SESSION['nombre_usuario'] = $user['nombre'];
    $_SESSION['rol_id'] = $user['rol_id'];
    $_SESSION['ultima_actividad'] = time();

    // Revocar token anterior
    revocarRemember($conn);

    // Opción Recordarme
    $recordarme = ($_POST['recordarme'] ?? '') === '1';
    if ($recordarme) {
        crearRemember($conn, (int) $user['id']);
    }
    // Registrar inicio de sesión
    $stmt_log = $conn->prepare("
        INSERT INTO bitacora (
            usuario_id,
            accion,
            entidad,
            ip
        )
        VALUES (
            :usuario_id,
            'LOGIN',
            'usuarios',
            :ip
        )
    ");

    $stmt_log->execute([
        'usuario_id' => $user['id'],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null
    ]);

    // Ir al dashboard
    header('Location: ../pages/dashboard.php');
    exit;

} catch (PDOException $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Error de login: ' . $e->getMessage());
    $_SESSION = [];
    header('Location: ../index.php?error=sistema');
    exit;
}

<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/bitacora.php';

requerirPermiso($conn, 'usuarios.editar');

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}

// Validar CSRF
$token = $_POST['csrf_token'] ?? '';

if (!is_string($token) || !validarTokenCSRF($token)) {
    http_response_code(403);
    exit('Solicitud no autorizada.');
}

// Obtener datos del formulario
$usuarioId = filter_var(
    $_POST['usuario_id'] ?? null,
    FILTER_VALIDATE_INT
);

$rolId = filter_var(
    $_POST['rol_id'] ?? null,
    FILTER_VALIDATE_INT
);

$nombre = $_POST['nombre'] ?? '';
$apellido = $_POST['apellido'] ?? '';
$email = $_POST['email'] ?? '';
$nomUsuario = $_POST['nom_usuario'] ?? '';

// Validar tipos e identificadores
if (
    !$usuarioId ||
    !$rolId ||
    !is_string($nombre) ||
    !is_string($apellido) ||
    !is_string($email) ||
    !is_string($nomUsuario)
) {
    http_response_code(400);
    exit('Datos no válidos.');
}

// Eliminar espacios innecesarios
$nombre = trim($nombre);
$apellido = trim($apellido);
$email = trim($email);
$nomUsuario = trim($nomUsuario);

// Validar campos y longitudes
if (
    $nombre === '' ||
    $apellido === '' ||
    $email === '' ||
    $nomUsuario === '' ||
    mb_strlen($nombre) > 50 ||
    mb_strlen($apellido) > 50 ||
    mb_strlen($email) > 100 ||
    mb_strlen($nomUsuario) > 50 ||
    !filter_var($email, FILTER_VALIDATE_EMAIL)
) {
    header(
        'Location: ../pages/edit_user.php?id=' .
        $usuarioId .
        '&error=campos'
    );
    exit;
}

try {
    $conn->beginTransaction();

    // Obtener los datos anteriores y bloquear el usuario
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
        FOR UPDATE
    ");

    $stmt->execute(['id' => $usuarioId]);

    $usuarioAnterior = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuarioAnterior) {
        throw new RuntimeException('Usuario no encontrado.');
    }

    // Impedir cambiar el propio rol
    if (
        $usuarioId === (int) $_SESSION['usuario_id'] &&
        $rolId !== (int) $usuarioAnterior['rol_id']
    ) {
        $conn->rollBack();

        header(
            'Location: ../pages/edit_user.php?id=' .
            $usuarioId .
            '&error=propio_rol'
        );
        exit;
    }

    // Comprobar que el rol exista
    $stmt = $conn->prepare("
        SELECT 1
        FROM rol
        WHERE id_rol = :rol_id
        LIMIT 1
    ");

    $stmt->execute(['rol_id' => $rolId]);

    if ($stmt->fetchColumn() === false) {
        $conn->rollBack();

        header(
            'Location: ../pages/edit_user.php?id=' .
            $usuarioId .
            '&error=rol'
        );
        exit;
    }

    // Comprobar usuario o correo duplicados
    $stmt = $conn->prepare("
        SELECT id
        FROM usuarios
        WHERE (nom_usuario = :usuario OR email = :email)
          AND id <> :id
        LIMIT 1
    ");

    $stmt->execute([
        'usuario' => $nomUsuario,
        'email' => $email,
        'id' => $usuarioId
    ]);

    if ($stmt->fetchColumn() !== false) {
        $conn->rollBack();

        header(
            'Location: ../pages/edit_user.php?id=' .
            $usuarioId .
            '&error=duplicado'
        );
        exit;
    }

    // Valores anteriores
    $valoresAntes = [
        'nombre' => $usuarioAnterior['nombre'],
        'apellido' => $usuarioAnterior['apellido'],
        'email' => $usuarioAnterior['email'],
        'nom_usuario' => $usuarioAnterior['nom_usuario'],
        'rol_id' => (int) $usuarioAnterior['rol_id']
    ];

    // Valores nuevos
    $valoresDespues = [
        'nombre' => $nombre,
        'apellido' => $apellido,
        'email' => $email,
        'nom_usuario' => $nomUsuario,
        'rol_id' => $rolId
    ];

    // Evitar registrar cambios que no ocurrieron
    if ($valoresAntes === $valoresDespues) {
        $conn->commit();

        header('Location: ../pages/users.php?success=sin_cambios');
        exit;
    }

    // Actualizar usuario
    $stmt = $conn->prepare("
        UPDATE usuarios
        SET
            nombre = :nombre,
            apellido = :apellido,
            email = :email,
            nom_usuario = :nom_usuario,
            rol_id = :rol_id
        WHERE id = :id
          AND desactivado_en IS NULL
    ");

    $stmt->execute([
        'nombre' => $nombre,
        'apellido' => $apellido,
        'email' => $email,
        'nom_usuario' => $nomUsuario,
        'rol_id' => $rolId,
        'id' => $usuarioId
    ]);

    // Registrar modificación en bitácora
    registrarBitacora(
        $conn,
        (int) $_SESSION['usuario_id'],
        'UPDATE',
        'usuarios',
        $usuarioId,
        $valoresAntes,
        $valoresDespues
    );

    // Confirmar ambas operaciones
    $conn->commit();

    // Actualizar el nombre de la sesión si se editó a sí mismo
    if ($usuarioId === (int) $_SESSION['usuario_id']) {
        $_SESSION['nombre'] = $nombre;
    }

    header('Location: ../pages/users.php?success=editado');
    exit;

} catch (PDOException $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log('Error al editar usuario: ' . $e->getMessage());

    // Código duplicado en una columna UNIQUE
    if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
        header(
            'Location: ../pages/edit_user.php?id=' .
            $usuarioId .
            '&error=duplicado'
        );
        exit;
    }

    header(
        'Location: ../pages/edit_user.php?id=' .
        $usuarioId .
        '&error=sistema'
    );
    exit;

    // Si no se encuentra el usuario
} catch (RuntimeException $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    http_response_code(404);
    exit('Usuario no disponible.');

    // Si hubo error al editar el usuario
} catch (Throwable $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log('Error al editar usuario: ' . $e->getMessage());

    header(
        'Location: ../pages/edit_user.php?id=' .
        $usuarioId .
        '&error=sistema'
    );
    exit;
}
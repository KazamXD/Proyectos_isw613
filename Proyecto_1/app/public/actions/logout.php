
<?php

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/remember.php';

// Revocar la credencial persistente del navegador
try {
    revocarRemember($conn);
} catch (Throwable $e) {
    error_log('Error al revocar Recordarme: ' . $e->getMessage());

    // No confirmar un cierre de sesión completo
    // si el token persistente no pudo ser revocado.
    http_response_code(503);
    exit('No se pudo completar el cierre de sesión. Inténtalo nuevamente.');
}

// Revocar la credencial persistente del navegador
try {
    revocarRemember($conn);
} catch (Throwable $e) {
    error_log('Error al revocar Recordarme: ' . $e->getMessage());

    // No confirmar un cierre de sesión completo
    // si el token persistente no pudo ser revocado.
    http_response_code(503);
    exit('No se pudo completar el cierre de sesión. Inténtalo nuevamente.');
}

// Vaciar todas las variables de sesión
$_SESSION = [];

// Eliminar la cookie de sesión del navegador
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax'
    ]);
}

// Destruir la sesión del servidor
session_destroy();

// Regresar al login
header('Location: ../index.php');
exit;

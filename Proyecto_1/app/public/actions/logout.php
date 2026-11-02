
<?php

require_once __DIR__ . '/../../includes/session.php';

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

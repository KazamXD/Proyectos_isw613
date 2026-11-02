
<?php

// Tiempo máximo de inactividad: 15 minutos
const TIEMPO_INACTIVIDAD = 900;

if (session_status() === PHP_SESSION_NONE) {

    // Detectar si la conexión utiliza HTTPS
    $https = !empty($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== 'off';

    // Configurar la cookie antes de iniciar la sesión
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    ini_set('session.use_strict_mode', '1');

    session_start();
}

// Comprobar el tiempo de inactividad
if (isset($_SESSION['ultima_actividad'])) {

    $tiempoTranscurrido = time() - $_SESSION['ultima_actividad'];

    if ($tiempoTranscurrido > TIEMPO_INACTIVIDAD) {

        $_SESSION = [];

        // Eliminar la cookie
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

        session_destroy();

        // Evitar seguir utilizando la sesión expirada
        $_SESSION = [];
    }
}

// Actualizar la última actividad de una sesión autenticada
if (isset($_SESSION['usuario_id'])) {
    $_SESSION['ultima_actividad'] = time();
}

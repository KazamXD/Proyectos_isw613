<?php

const REMEMBER_COOKIE = 'sigel_remember';
const REMEMBER_DURACION = 60 * 60 * 24 * 14;

// Detectar HTTPS
function rememberEsHttps(): bool
{
    return !empty($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== 'off';
}

// Crear o eliminar la cookie
function establecerCookieRemember(string $valor, int $expiracion): void
{
    setcookie(REMEMBER_COOKIE, $valor, [
        'expires' => $expiracion,
        'path' => '/',
        'secure' => rememberEsHttps(),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

// Revocar el token presentado por el navegador
function revocarRemember(PDO $conn): void
{
    $cookie = $_COOKIE[REMEMBER_COOKIE] ?? null;

    if (is_string($cookie)) {
        $partes = explode(':', $cookie);

        if (
            count($partes) === 2 &&
            preg_match('/^[a-f0-9]{18}$/D', $partes[0])
        ) {
            $stmt = $conn->prepare("
                DELETE FROM auth_tokens
                WHERE selector = :selector
            ");

            $stmt->execute([
                'selector' => $partes[0]
            ]);
        }
    }

    establecerCookieRemember('', time() - 3600);
    unset($_COOKIE[REMEMBER_COOKIE]);
}

// Generar una credencial persistente nueva
function crearRemember(PDO $conn, int $usuarioId): void
{
    $selector = bin2hex(random_bytes(9));
    $validator = bin2hex(random_bytes(32));

    $validatorHash = hash('sha256', $validator);

    $stmt = $conn->prepare("
        INSERT INTO auth_tokens (
            usuario_id,
            selector,
            validator_hash,
            expira_en
        )
        VALUES (
            :usuario_id,
            :selector,
            :validator_hash,
            DATE_ADD(CURRENT_TIMESTAMP(), INTERVAL 14 DAY)
        )
    ");

    $stmt->execute([
        'usuario_id' => $usuarioId,
        'selector' => $selector,
        'validator_hash' => $validatorHash
    ]);

    establecerCookieRemember(
        $selector . ':' . $validator,
        time() + REMEMBER_DURACION
    );
}

// Recuperar una sesión utilizando la cookie "Recordarme"
function recuperarRemember(PDO $conn): bool
{
    // Si ya existe una sesión autenticada, no es necesario recuperarla
    if (!empty($_SESSION['usuario_id'])) {
        return true;
    }

    $cookie = $_COOKIE[REMEMBER_COOKIE] ?? null;

    if (!is_string($cookie)) {
        return false;
    }

    // Validar el formato selector:validador
    if (!preg_match('/^([a-f0-9]{18}):([a-f0-9]{64})$/D', $cookie, $partes)) {
        establecerCookieRemember('', time() - 3600);
        unset($_COOKIE[REMEMBER_COOKIE]);
        return false;
    }

    $selector = $partes[1];
    $validator = $partes[2];

    try {
        $conn->beginTransaction();

        // Buscar el token y comprobar que el usuario siga activo
        $stmt = $conn->prepare("
            SELECT
                t.id,
                t.usuario_id,
                t.validator_hash,
                u.nombre,
                u.rol_id
            FROM auth_tokens t
            INNER JOIN usuarios u
                ON t.usuario_id = u.id
            WHERE t.selector = :selector
              AND t.expira_en > CURRENT_TIMESTAMP()
              AND u.activo = 1
            FOR UPDATE
        ");

        $stmt->execute([
            'selector' => $selector
        ]);

        $token = $stmt->fetch(PDO::FETCH_ASSOC);

        // Comprobar que exista y que el validador coincida
        if (
            !$token ||
            !hash_equals(
                $token['validator_hash'],
                hash('sha256', $validator)
            )
        ) {
            $conn->rollBack();

            establecerCookieRemember('', time() - 3600);
            unset($_COOKIE[REMEMBER_COOKIE]);

            return false;
        }

        // Generar un selector y validador nuevos
        $nuevoSelector = bin2hex(random_bytes(9));
        $nuevoValidator = bin2hex(random_bytes(32));
        $nuevoHash = hash('sha256', $nuevoValidator);

        // Rotar el token en MariaDB
        $stmt = $conn->prepare("
            UPDATE auth_tokens
            SET
                selector = :selector,
                validator_hash = :validator_hash,
                expira_en = DATE_ADD(
                    CURRENT_TIMESTAMP(),
                    INTERVAL 14 DAY
                )
            WHERE id = :id
        ");

        $stmt->execute([
            'selector' => $nuevoSelector,
            'validator_hash' => $nuevoHash,
            'id' => (int) $token['id']
        ]);

        $conn->commit();

        // Asegurar una sesión PHP activa
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Renovar el identificador de sesión
        session_regenerate_id(true);

        // Restaurar los datos de autenticación
        $_SESSION['usuario_id'] = (int) $token['usuario_id'];
        $_SESSION['nombre_usuario'] = $token['nombre'];
        $_SESSION['rol_id'] = (int) $token['rol_id'];
        $_SESSION['ultima_actividad'] = time();

        // Sustituir la cookie por el token nuevo
        establecerCookieRemember(
            $nuevoSelector . ':' . $nuevoValidator,
            time() + REMEMBER_DURACION
        );

        $_COOKIE[REMEMBER_COOKIE] =
            $nuevoSelector . ':' . $nuevoValidator;

        return true;

    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        error_log(
            'Error al recuperar sesión persistente: ' .
            $e->getMessage()
        );

        return false;
    }
}
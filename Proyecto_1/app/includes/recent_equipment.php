<?php

const COOKIE_EQUIPOS_RECIENTES = 'sigel_recent_equipment';
const MAX_EQUIPOS_RECIENTES = 5;

// Obtener identificadores guardados
function obtenerEquiposRecientes(): array
{
    $cookie = $_COOKIE[COOKIE_EQUIPOS_RECIENTES] ?? '';

    if (!is_string($cookie) || $cookie === '') {
        return [];
    }
    $datos = json_decode($cookie, true);

    if (!is_array($datos) || !array_is_list($datos)) {
        return [];
    }
    $equipos = [];

    foreach ($datos as $id) {

        // Solo aceptar identificadores enteros positivos
        if (!is_int($id) || $id <= 0) {
            continue;
        }
        if (!in_array($id, $equipos, true)) {
            $equipos[] = $id;
        }
        if (count($equipos) >= MAX_EQUIPOS_RECIENTES) {
            break;
        }
    }
    return $equipos;
}

// Registrar un equipo consultado
function registrarEquipoReciente(int $equipoId): void
{
    if ($equipoId <= 0) {
        return;
    }

    // Leer los equipos anteriores
    $equipos = obtenerEquiposRecientes();

    // Quitar el equipo si ya estaba registrado
    $equipos = array_values(
        array_filter(
            $equipos,
            fn($id) => $id !== $equipoId
        )
    );

    // Colocar el equipo al principio
    array_unshift($equipos, $equipoId);

    // Conservar únicamente cinco
    $equipos = array_slice(
        $equipos,
        0,
        MAX_EQUIPOS_RECIENTES
    );

    // Detectar HTTPS
    $esHttps = !empty($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== 'off';

    // Guardar cookie
    setcookie(
        COOKIE_EQUIPOS_RECIENTES,
        json_encode($equipos),
        [
            'expires' => time() + (60 * 60 * 24 * 30),
            'path' => '/Proyecto_1/app/public',
            'secure' => $esHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );

    // Actualizar el valor durante la petición actual
    $_COOKIE[COOKIE_EQUIPOS_RECIENTES] = json_encode($equipos);
}
<?php


// Función para verificar si un usuario tiene préstamos retrasados
// Se conecta con la bd y utiliza un identificador de usuario para determinar si hay préstamos que no han sido devueltos y cuya fecha límite ya ha pasado.
function tienePrestamosRetrasados(PDO $conn, int $usuarioId): bool
{
    $stmt = $conn->prepare("
        SELECT 1
        FROM prestamos
        WHERE usuario_id = :usuario_id
          AND fecha_devolucion IS NULL
          AND fecha_limite < NOW()
        LIMIT 1
    ");

    $stmt->execute([
        'usuario_id' => $usuarioId
    ]);

    return $stmt->fetchColumn() !== false;
}   
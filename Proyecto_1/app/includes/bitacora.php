<?php

function registrarBitacora(
    PDO $conn,
    ?int $usuarioId,
    string $accion,
    string $entidad,
    ?int $entidadId,
    ?array $valoresAntes = null,
    ?array $valoresDespues = null
): void {
    $stmt = $conn->prepare("
        INSERT INTO bitacora (
            usuario_id,
            accion,
            entidad,
            entidad_id,
            valores_antes,
            valores_despues,
            ip
        )
        VALUES (
            :usuario_id,
            :accion,
            :entidad,
            :entidad_id,
            :valores_antes,
            :valores_despues,
            :ip
        )
    ");

    $stmt->execute([
        'usuario_id' => $usuarioId,
        'accion' => $accion,
        'entidad' => $entidad,
        'entidad_id' => $entidadId,
        'valores_antes' => $valoresAntes === null
            ? null
            : json_encode($valoresAntes, JSON_THROW_ON_ERROR),
        'valores_despues' => $valoresDespues === null
            ? null
            : json_encode($valoresDespues, JSON_THROW_ON_ERROR),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null
    ]);
}
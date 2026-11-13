<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/csv.php';

requerirPermiso($conn, 'usuarios.ver');

// Consultar usuarios activos
$stmt = $conn->prepare("
    SELECT
        u.id,
        u.nombre,
        u.apellido,
        u.email,
        u.nom_usuario,
        r.nombre_rol
    FROM usuarios u
    INNER JOIN rol r ON u.rol_id = r.id_rol
    WHERE u.desactivado_en IS NULL
    ORDER BY u.nombre, u.apellido
");

$stmt->execute();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="usuarios.csv"');
header('X-Content-Type-Options: nosniff');

$archivo = fopen('php://output', 'w');

fputcsv($archivo, [
    'ID',
    'Nombre',
    'Apellido',
    'Correo',
    'Usuario',
    'Rol'
]);

while ($usuario = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($archivo, [
        (int) $usuario['id'],
        neutralizarFormulaCSV($usuario['nombre']),
        neutralizarFormulaCSV($usuario['apellido']),
        neutralizarFormulaCSV($usuario['email']),
        neutralizarFormulaCSV($usuario['nom_usuario']),
        neutralizarFormulaCSV($usuario['nombre_rol'])
    ]);
}

fclose($archivo);
exit;
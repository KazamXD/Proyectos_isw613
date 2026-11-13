<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/csv.php';

// Verificar permisos
requerirPermiso($conn, 'equipos.ver');

// Consultar equipos activos
$stmt = $conn->prepare("
    SELECT
        e.id_equipo,
        e.codigo,
        c.categoria_nombre,
        e.nombre_estado
    FROM equipos e
    INNER JOIN categorias c
        ON e.id_categoria = c.categoria_id
    WHERE e.eliminado_en IS NULL
    ORDER BY c.categoria_nombre, e.codigo
");

$stmt->execute();
$equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Indicar que la respuesta será un CSV
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="equipos.csv"');
header('X-Content-Type-Options: nosniff');

// Crear flujo de salida
$archivo = fopen('php://output', 'w');

// Escribir encabezados
fputcsv($archivo, [
    'ID',
    'Código',
    'Categoría',
    'Estado'
]);

// Escribir registros
foreach ($equipos as $equipo) {

    fputcsv($archivo, [
        (int) $equipo['id_equipo'],
        neutralizarFormulaCSV($equipo['codigo']),
        neutralizarFormulaCSV($equipo['categoria_nombre']),
        neutralizarFormulaCSV($equipo['nombre_estado'])
    ]);
}

fclose($archivo);
exit;
<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/csv.php';

requerirPermiso($conn, 'categorias.ver');

$stmt = $conn->prepare("
    SELECT
        categoria_id,
        categoria_nombre,
        dias_max_prestamo
    FROM categorias
    WHERE eliminado_en IS NULL
    ORDER BY categoria_nombre
");

$stmt->execute();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="categorias.csv"');
header('X-Content-Type-Options: nosniff');

$archivo = fopen('php://output', 'w');

fputcsv($archivo, [
    'ID',
    'Categoría',
    'Días máximos de préstamo'
]);

while ($categoria = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($archivo, [
        (int) $categoria['categoria_id'],
        neutralizarFormulaCSV($categoria['categoria_nombre']),
        (int) $categoria['dias_max_prestamo']
    ]);
}

fclose($archivo);
exit;
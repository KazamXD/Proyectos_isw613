<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/csv.php';

requerirPermiso($conn, 'prestamos.ver');

$stmt = $conn->prepare("
    SELECT
        p.id_prestamo,
        CONCAT(u.nombre, ' ', u.apellido) AS usuario,
        CONCAT(o.nombre, ' ', o.apellido) AS operador,
        GROUP_CONCAT(
            e.codigo
            ORDER BY e.codigo
            SEPARATOR ', '
        ) AS equipos,
        p.fecha_entrega,
        p.fecha_limite,
        p.fecha_devolucion,
        p.estado
    FROM prestamos p
    INNER JOIN usuarios u ON p.usuario_id = u.id
    INNER JOIN usuarios o ON p.operador_id = o.id
    LEFT JOIN prestamo_detalle pd
        ON p.id_prestamo = pd.prestamo_id
    LEFT JOIN equipos e
        ON pd.equipo_id = e.id_equipo
    GROUP BY
        p.id_prestamo,
        u.nombre,
        u.apellido,
        o.nombre,
        o.apellido,
        p.fecha_entrega,
        p.fecha_limite,
        p.fecha_devolucion,
        p.estado
    ORDER BY p.id_prestamo DESC
");

$stmt->execute();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="prestamos.csv"');
header('X-Content-Type-Options: nosniff');

$archivo = fopen('php://output', 'w');

fputcsv($archivo, [
    'ID',
    'Usuario',
    'Operador',
    'Equipos',
    'Fecha de entrega',
    'Fecha límite',
    'Fecha de devolución',
    'Estado'
]);

while ($prestamo = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($archivo, [
        (int) $prestamo['id_prestamo'],
        neutralizarFormulaCSV($prestamo['usuario']),
        neutralizarFormulaCSV($prestamo['operador']),
        neutralizarFormulaCSV($prestamo['equipos'] ?? ''),
        $prestamo['fecha_entrega'],
        $prestamo['fecha_limite'],
        $prestamo['fecha_devolucion'] ?? '',
        neutralizarFormulaCSV($prestamo['estado'])
    ]);
}

fclose($archivo);
exit;
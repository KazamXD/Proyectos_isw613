<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/csv.php';

//Permisos
requerirPermiso($conn, 'reportes.ver');

// Identificar reporte solicitado
$tipo = $_GET['tipo'] ?? 'history';

if (!is_string($tipo) || !in_array($tipo, ['history', 'overdue'], true)) {
    http_response_code(400);
    exit('Reporte no válido.');
}

// Obtener filtros
$busqueda = $_GET['buscar'] ?? '';
$estado = $_GET['estado'] ?? '';

if (!is_string($busqueda)) {
    $busqueda = '';
}

if (!is_string($estado)) {
    $estado = '';
}

$busqueda = trim($busqueda);

$estadosPermitidos = [
    'prestado',
    'devuelto',
    'retrasado'
];

if (!in_array($estado, $estadosPermitidos, true)) {
    $estado = '';
}

// Construir filtros
$filtro = "WHERE 1=1";
$parametros = [];

// Filtrar préstamos retrasados
if ($tipo === 'overdue') {
    $filtro .= "
        AND p.fecha_limite < NOW()
        AND p.fecha_devolucion IS NULL
    ";
}

// Buscar por usuario
if ($busqueda !== '') {
    $filtro .= "
        AND (
            u.nombre LIKE :nombre
            OR u.apellido LIKE :apellido
            OR u.nom_usuario LIKE :usuario
        )
    ";

    $valor = "%$busqueda%";

    $parametros = [
        'nombre' => $valor,
        'apellido' => $valor,
        'usuario' => $valor
    ];
}

// Filtrar por estado
if ($tipo === 'history' && $estado !== '') {
    $filtro .= " AND p.estado = :estado";
    $parametros['estado'] = $estado;
}

// Consultar préstamos
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
        p.estado,

        TIMESTAMPDIFF(
            HOUR,
            p.fecha_limite,
            NOW()
        ) AS horas_retraso

    FROM prestamos p

    INNER JOIN usuarios u
        ON p.usuario_id = u.id

    INNER JOIN usuarios o
        ON p.operador_id = o.id

    LEFT JOIN prestamo_detalle pd
        ON p.id_prestamo = pd.prestamo_id

    LEFT JOIN equipos e
        ON pd.equipo_id = e.id_equipo

    $filtro

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

$stmt->execute($parametros);

// Nombre del archivo según el reporte
$nombreArchivo = $tipo === 'overdue'
    ? 'prestamos_retrasados.csv'
    : 'historial_prestamos.csv';

// Cabeceras HTTP
header('Content-Type: text/csv; charset=UTF-8');

header(
    'Content-Disposition: attachment; filename="' .
    $nombreArchivo .
    '"'
);

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

// Abrir archivo CSV
$archivo = fopen('php://output', 'w');

if ($archivo === false) {
    error_log('No se pudo abrir la salida CSV.');
    exit;
}

// Encabezados según el tipo de reporte
if ($tipo === 'overdue') {

    fputcsv($archivo, [
        'ID',
        'Usuario',
        'Equipos',
        'Fecha de entrega',
        'Fecha límite',
        'Horas de retraso'
    ]);

} else {

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
}

// Recorrer registros
while ($prestamo = $stmt->fetch(PDO::FETCH_ASSOC)) {

    if ($tipo === 'overdue') {

        fputcsv($archivo, [
            (int) $prestamo['id_prestamo'],
            neutralizarFormulaCSV($prestamo['usuario']),
            neutralizarFormulaCSV($prestamo['equipos'] ?? ''),
            $prestamo['fecha_entrega'],
            $prestamo['fecha_limite'],
            (int) $prestamo['horas_retraso']
        ]);

    } else {

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
}

fclose($archivo);
exit;
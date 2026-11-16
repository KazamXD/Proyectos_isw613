<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';

// Verificar que el usuario tenga permiso
requerirPermiso($conn, 'reportes.ver');

?>

<!DOCTYPE html>
<html lang="es">

    <head>
        <meta charset="UTF-8">
        <title>Reportes del Sistema</title>
    </head>

    <body>

        <h1>Reportes del Sistema</h1>

        <p>
            Selecciona el reporte que deseas consultar.
        </p>

        <hr>

        <h2>Inventario de Equipos</h2>

        <p>
            Consulta los equipos registrados, sus categorías
            y estados actuales.
        </p>

        <a href="report_equipment.php">
            Ver reporte de inventario
        </a>

        <hr>

        <h2>Préstamos Retrasados</h2>

        <p>
            Consulta los préstamos que superaron su fecha
            límite y todavía no han sido devueltos.
        </p>

        <a href="report_overdue.php">
            Ver préstamos retrasados
        </a>

        <hr>

        <h2>Historial de Préstamos</h2>

        <p>
            Consulta el historial de préstamos realizados,
            incluyendo préstamos activos y devueltos.
        </p>

        <p>
            <a href="report_history.php">
                Ver historial de préstamos
            </a>
        </p>

        <hr>

        <h2>Bitácora del Sistema</h2>

        <p>
            Consulta las operaciones realizadas por los usuarios,
            como creación, edición y eliminación de registros.
        </p>

        <p>
            <a href="audit_log.php">
                Consultar bitácora
            </a>
        </p>

        <hr>

        <p>
            <a href="dashboard.php">
                Volver al dashboard
            </a>
        </p>

    </body>
</html>
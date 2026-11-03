<?php

require_once __DIR__ . '/bitacora.php';

function actualizarRetrasos(PDO $conn): void
{
    // Buscar préstamos que ya superaron su fecha límite
    $stmt = $conn->prepare("
        SELECT id_prestamo, estado, fecha_limite
        FROM prestamos
        WHERE estado = 'prestado'
          AND fecha_devolucion IS NULL
          AND fecha_limite < NOW()
        ORDER BY id_prestamo
    ");

    $stmt->execute();

    $prestamosVencidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($prestamosVencidos as $prestamo) {

        try {
            $conn->beginTransaction();

            // Actualizar solamente si sigue pendiente de devolución
            $stmtUpdate = $conn->prepare("
                UPDATE prestamos
                SET estado = 'retrasado'
                WHERE id_prestamo = :id
                  AND estado = 'prestado'
                  AND fecha_devolucion IS NULL
                  AND fecha_limite < NOW()
            ");

            $stmtUpdate->execute([
                'id' => $prestamo['id_prestamo']
            ]);

            // Evitar registrar un cambio que no ocurrió
            if ($stmtUpdate->rowCount() === 1) {

                registrarBitacora(
                    $conn,
                    null,
                    'UPDATE',
                    'prestamos',
                    (int) $prestamo['id_prestamo'],
                    [
                        'estado' => 'prestado',
                        'fecha_limite' => $prestamo['fecha_limite']
                    ],
                    [
                        'estado' => 'retrasado',
                        'fecha_limite' => $prestamo['fecha_limite'],
                        'motivo' => 'Fecha límite superada'
                    ]
                );
            }

            $conn->commit();

        } catch (Throwable $e) {

            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            error_log(
                'Error al actualizar retraso: ' . $e->getMessage()
            );
        }
    }
}
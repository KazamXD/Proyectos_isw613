<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';

requerirPermiso($conn, 'prestamos.crear');

// Obtener usuarios activos
$stmt = $conn->prepare("
    SELECT id, nombre, apellido
    FROM usuarios
    WHERE desactivado_en IS NULL
    ORDER BY nombre ASC
");
$stmt->execute();

$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener equipos disponibles y sin préstamos activos
$stmt = $conn->prepare("
    SELECT e.id_equipo, e.codigo
    FROM equipos e
    WHERE e.eliminado_en IS NULL
      AND e.nombre_estado = 'Disponible'
      AND NOT EXISTS (
          SELECT 1
          FROM prestamo_detalle pd
          INNER JOIN prestamos p
              ON pd.prestamo_id = p.id_prestamo
          WHERE pd.equipo_id = e.id_equipo
            AND p.estado IN ('prestado', 'retrasado')
            AND p.fecha_devolucion IS NULL
      )
    ORDER BY e.codigo ASC
");
$stmt->execute();

$equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tokenCSRF = generarTokenCSRF();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar préstamo</title>
</head>
<body>

<h1>Registrar préstamo</h1>

<form action="../actions/create_loan.php" method="POST">
    <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars($tokenCSRF, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
    >
    <label for="usuario_id">Usuario:</label>
    <select name="usuario_id" id="usuario_id" required>
        <option value="">Seleccione un usuario</option>
        <?php foreach ($usuarios as $usuario): ?>
            <option value="<?= (int)$usuario['id'] ?>">
                <?= htmlspecialchars(
                    $usuario['nombre'] . ' ' . $usuario['apellido'],
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                ) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <br><br>
    <label for="equipos">Equipos disponibles:</label>
    <select name="equipos[]" id="equipos" multiple required>
        <?php foreach ($equipos as $equipo): ?>
            <option value="<?= (int)$equipo['id_equipo'] ?>">
                <?= htmlspecialchars(
                    $equipo['codigo'],
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                ) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <p>Utiliza Ctrl + clic para seleccionar varios equipos.</p>
    <label for="fecha_limite">Fecha límite:</label>
    <input
        type="datetime-local"
        name="fecha_limite"
        id="fecha_limite"
        min="<?= date('Y-m-d\ŢH:i') ?>"
        required
    >
    <br><br>
    <button type="submit">Registrar préstamo</button>
</form>
</body>
</html>
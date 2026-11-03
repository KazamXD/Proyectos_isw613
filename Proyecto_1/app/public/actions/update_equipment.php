<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/bitacora.php';
require_once __DIR__ . '/../../includes/equipment_image.php';

requerirPermiso($conn, 'equipos.editar');

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}

// Validar CSRF
$token = $_POST['csrf_token'] ?? '';

if (!is_string($token) || !validarTokenCSRF($token)) {
    http_response_code(403);
    exit('Solicitud no autorizada.');
}

// Recibir datos
$equipoId = filter_var(
    $_POST['equipo_id'] ?? null,
    FILTER_VALIDATE_INT
);

$categoriaId = filter_var(
    $_POST['id_categoria'] ?? null,
    FILTER_VALIDATE_INT
);

$codigo = $_POST['codigo'] ?? '';
$estado = $_POST['nombre_estado'] ?? '';

// Validaciones
if (
    !$equipoId ||
    !$categoriaId ||
    !is_string($codigo) ||
    !is_string($estado)
) {
    http_response_code(400);
    exit('Datos no válidos.');
}

$codigo = trim($codigo);

if (
    $codigo === '' ||
    mb_strlen($codigo) > 30 ||
    !in_array($estado, ['Disponible', 'Mantenimiento'], true)
) {
    http_response_code(400);
    exit('Los datos ingresados no son válidos.');
}

$nombreImagenNueva = null;
try {
    $conn->beginTransaction();

    // Obtener los datos anteriores y bloquear el equipo
    $stmt = $conn->prepare("
        SELECT
            id_equipo,
            codigo,
            id_categoria,
            nombre_estado,
            ruta_imagen
        FROM equipos
        WHERE id_equipo = :id
          AND eliminado_en IS NULL
        FOR UPDATE
    ");

    $stmt->execute(['id' => $equipoId]);
    $equipoAnterior = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$equipoAnterior) {
        throw new RuntimeException('Equipo no encontrado.');
    }

    // No permitir modificaciones mientras esté prestado
    $stmt = $conn->prepare("
        SELECT 1
        FROM prestamo_detalle pd
        INNER JOIN prestamos p
            ON pd.prestamo_id = p.id_prestamo
        WHERE pd.equipo_id = :id
          AND p.estado IN ('prestado', 'retrasado')
          AND p.fecha_devolucion IS NULL
        LIMIT 1
    ");

    $stmt->execute(['id' => $equipoId]);

    if ($stmt->fetchColumn() !== false) {
        throw new RuntimeException(
            'No se puede editar un equipo con un préstamo activo.'
        );
    }

    // Verificar que la categoría exista y esté activa
    $stmt = $conn->prepare("
        SELECT 1
        FROM categorias
        WHERE categoria_id = :id
          AND eliminado_en IS NULL
        LIMIT 1
    ");
    
    // Por defecto, conservar la imagen anterior
    $nombreImagen = $equipoAnterior['ruta_imagen'];

    // Comprobar si se seleccionó una imagen nueva
    if (
        isset($_FILES['imagen']) &&
        ($_FILES['imagen']['error'] ?? null) !== UPLOAD_ERR_NO_FILE
    ) {
        $resultadoImagen = guardarImagenEquipo($_FILES['imagen']);

       /* if (isset($resultadoImagen['error'])) {
            throw new RuntimeException('La imagen seleccionada no es válida.');
        } */
       if (isset($resultadoImagen['error'])) {
            error_log('Error al subir imagen: ' . $resultadoImagen['error']);

            throw new RuntimeException($resultadoImagen['error']);
        }

        $nombreImagenNueva = $resultadoImagen['filename'];
        $nombreImagen = $nombreImagenNueva;
    }

    $stmt->execute(['id' => $categoriaId]);

    if ($stmt->fetchColumn() === false) {
        throw new RuntimeException('Categoría no válida.');
    }

    // Preparar los valores nuevos
    $valoresAntes = [
        'codigo' => $equipoAnterior['codigo'],
        'id_categoria' => (int)$equipoAnterior['id_categoria'],
        'nombre_estado' => $equipoAnterior['nombre_estado'],
        'ruta_imagen' => $equipoAnterior['ruta_imagen']
    ];

    $valoresDespues = [
        'codigo' => $codigo,
        'id_categoria' => $categoriaId,
        'nombre_estado' => $estado,
        'ruta_imagen' => $nombreImagen
    ];

    // No generar una modificación ficticia
    if ($valoresAntes === $valoresDespues) {
        $conn->commit();

        header('Location: ../pages/equipment.php?success=sin_cambios');
        exit;
    }

    // Actualizar el equipo
    $stmt = $conn->prepare("
        UPDATE equipos
        SET codigo = :codigo,
            id_categoria = :categoria,
            nombre_estado = :estado,
            ruta_imagen = :ruta_imagen
        WHERE id_equipo = :id
    ");

    $stmt->execute([
        'codigo' => $codigo,
        'categoria' => $categoriaId,
        'estado' => $estado,
        'ruta_imagen' => $nombreImagen,
        'id' => $equipoId
    ]);

    // Registrar UPDATE en la bitácora
    registrarBitacora(
        $conn,
        (int)$_SESSION['usuario_id'],
        'UPDATE',
        'equipos',
        $equipoId,
        $valoresAntes,
        $valoresDespues
    );

    $conn->commit();

    header('Location: ../pages/equipment.php?success=editado');
    exit;

} catch (PDOException $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();

        if ($nombreImagenNueva !== null) {
            eliminarImagenEquipo($nombreImagenNueva);
        }
    }

    error_log('Error al editar equipo: ' . $e->getMessage());

    // Error de código duplicado
    if ((int)($e->errorInfo[1] ?? 0) === 1062) {

        header(
            'Location: ../pages/edit_equipment.php?id=' .
            $equipoId .
            '&error=duplicado'
        );
        exit;
    }

    header(
        'Location: ../pages/edit_equipment.php?id=' .
        $equipoId .
        '&error=sistema'
    );
    exit;

} catch (RuntimeException $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();

        if ($nombreImagenNueva !== null) {
            eliminarImagenEquipo($nombreImagenNueva);
        }
    }

    http_response_code(400);
    exit(htmlspecialchars(
        $e->getMessage(),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    ));

} catch (Throwable $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();

        if ($nombreImagenNueva !== null) {
            eliminarImagenEquipo($nombreImagenNueva);
        }
    }

    error_log('Error al editar equipo: ' . $e->getMessage());

    http_response_code(500);
    exit('No se pudo actualizar el equipo.');
}
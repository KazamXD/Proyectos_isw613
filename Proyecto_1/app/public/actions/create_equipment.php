<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/bitacora.php';
require_once __DIR__ . '/../../includes/equipment_image.php';

// Comprobar que el usuario tenga permiso
requerirPermiso($conn, 'equipos.crear');

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}

// Validar token CSRF
$token = $_POST['csrf_token'] ?? '';

if (!is_string($token) || !validarTokenCSRF($token)) {
    http_response_code(403);
    exit('Solicitud no autorizada.');
}

// Obtener los datos del formulario
$codigoRecibido = $_POST['codigo'] ?? '';
$estadoRecibido = $_POST['nombre_estado'] ?? '';

$categoriaId = filter_var(
    $_POST['id_categoria'] ?? null,
    FILTER_VALIDATE_INT
);

// Comprobar que los valores sean cadenas de texto
if (!is_string($codigoRecibido) || !is_string($estadoRecibido)) {
    header('Location: ../pages/equipment_form.php?error=campos');
    exit;
}

$codigo = trim($codigoRecibido);
$estado = trim($estadoRecibido);

// Validar código y categoría
if (
    $codigo === '' ||
    mb_strlen($codigo) > 30 ||
    $categoriaId === false ||
    $categoriaId === null ||
    $categoriaId <= 0
) {
    header('Location: ../pages/equipment_form.php?error=campos');
    exit;
}

// Estados permitidos al crear un equipo
$estadosPermitidos = [
    'Disponible',
    'Mantenimiento'
];

if (!in_array($estado, $estadosPermitidos, true)) {
    header('Location: ../pages/equipment_form.php?error=estado');
    exit;
}

try {
    // Comprobar que la categoría exista y esté activa
    $stmt = $conn->prepare("
        SELECT categoria_id
        FROM categorias
        WHERE categoria_id = :categoria_id
          AND eliminado_en IS NULL
        LIMIT 1
    ");

    $stmt->execute([
        'categoria_id' => $categoriaId
    ]);

    if ($stmt->fetchColumn() === false) {
        header('Location: ../pages/equipment_form.php?error=categoria');
        exit;
    }

    // Comprobar que el código no esté registrado
    $stmt = $conn->prepare("
        SELECT id_equipo
        FROM equipos
        WHERE codigo = :codigo
        LIMIT 1
    ");

    $stmt->execute([
        'codigo' => $codigo
    ]);

    if ($stmt->fetchColumn() !== false) {
        header('Location: ../pages/equipment_form.php?error=duplicado');
        exit;
    }

    //
    // Nombre del archivo que guardaremos en MariaDB
    $nombreImagen = null;

    // Comprobar si el administrador seleccionó una imagen
    if (
        isset($_FILES['imagen']) &&
        $_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE
    ) {
        $resultadoImagen = guardarImagenEquipo($_FILES['imagen']);
        if (isset($resultadoImagen['error'])) {
            header('Location: ../pages/equipment_form.php?error=imagen');
            exit;
        }

        $nombreImagen = $resultadoImagen['filename'];
    }

    // Iniciar transacción
    $conn->beginTransaction();

    // Insertar equipo junto con la ruta de la imagen, si no, es null
    $stmt = $conn->prepare("
        INSERT INTO equipos (
            id_categoria,
            codigo,
            nombre_estado,
            ruta_imagen
        )
        VALUES (
            :categoria_id,
            :codigo,
            :estado,
            :ruta_imagen
        )
    ");

    $stmt->execute([
        'categoria_id' => $categoriaId,
        'codigo' => $codigo,
        'estado' => $estado,
        'ruta_imagen' => $nombreImagen
    ]);

    // Obtener el ID generado
    $nuevoEquipoId = (int) $conn->lastInsertId();

    // Registrar la creación en la bitácora
    registrarBitacora(
        $conn,
        (int) $_SESSION['usuario_id'],
        'CREATE',
        'equipos',
        $nuevoEquipoId,
        null,
        [
            'codigo' => $codigo,
            'id_categoria' => $categoriaId,
            'nombre_estado' => $estado,
            'ruta_imagen' => $nombreImagen
        ]
    );

    // Confirmar creación y bitácora
    $conn->commit();

    // Redirigir al listado
    header('Location: ../pages/equipment.php?success=creado');
    exit;

} catch (PDOException $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
        if ($nombreImagen !== null) {
            eliminarImagenEquipo($nombreImagen);
        }
    }

    error_log('Error al crear equipo: ' . $e->getMessage());

    // El índice UNIQUE protege incluso ante solicitudes simultáneas
    if ($e->getCode() === '23000') {
        header('Location: ../pages/equipment_form.php?error=duplicado');
        exit;
    }

    header('Location: ../pages/equipment_form.php?error=sistema');
    exit;

} catch (Throwable $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
        if ($nombreImagen !== null) {
            eliminarImagenEquipo($nombreImagen);
        }
    }

    error_log('Error al crear equipo: ' . $e->getMessage());

    header('Location: ../pages/equipment_form.php?error=sistema');
    exit;
}
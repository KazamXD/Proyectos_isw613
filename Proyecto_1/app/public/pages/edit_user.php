<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';

requerirPermiso($conn, 'usuarios.editar');

// Obtener el ID del usuario
$usuarioId = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$usuarioId || $usuarioId <= 0) {
    http_response_code(400);
    exit('Usuario no válido.');
}

// Consultar los datos del usuario
$stmt = $conn->prepare("
    SELECT
        id,
        nombre,
        apellido,
        email,
        nom_usuario,
        rol_id
    FROM usuarios
    WHERE id = :id
      AND desactivado_en IS NULL
");

$stmt->execute(['id' => $usuarioId]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    http_response_code(404);
    exit('Usuario no encontrado.');
}

// Obtener los roles disponibles
$stmt = $conn->prepare("
    SELECT id_rol, nombre_rol
    FROM rol
    ORDER BY nombre_rol ASC
");

$stmt->execute();
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tokenCSRF = generarTokenCSRF();

$errores = [
    'campos' => 'Revisa los datos ingresados.',
    'duplicado' => 'El correo o nombre de usuario ya está registrado.',
    'rol' => 'El rol seleccionado no es válido.',
    'sistema' => 'No se pudo actualizar el usuario.',
    'propio_rol' => 'No puedes cambiar tu propio rol.'
];

$error = $_GET['error'] ?? '';
?>

<?php require_once __DIR__ . '/../../includes/header.php'; ?>

<div class="d-flex">

    <!-- Menú lateral -->
    <aside
        class="bg-dark text-white"
        style="width: 250px; min-height: 100vh; flex-shrink: 0;"
    >
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    </aside>

    <!-- Contenido principal -->
    <main class="flex-grow-1 p-4">

        <!-- Encabezado -->
        <div class="mb-4">
            <h1 class="fw-bold mb-1">
                Editar usuario
            </h1>
            <p class="text-muted">
                Modifica la información del usuario seleccionado.
            </p>
        </div>

        <!-- Tarjeta principal -->
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    Información del usuario
                </h5>
            </div>
            <div class="card-body">

                <!-- Mensajes de error -->
                <?php if (is_string($error) && isset($errores[$error])): ?>
                    <div class="alert alert-danger">
                        <?= htmlspecialchars(
                            $errores[$error],
                            ENT_QUOTES | ENT_SUBSTITUTE,
                            'UTF-8'
                        ) ?>
                    </div>
                <?php endif; ?>

                <!-- Formulario -->
                <form
                    action="../actions/update_user.php"
                    method="POST"
                >

                    <!-- Token CSRF -->
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                            $tokenCSRF,
                            ENT_QUOTES | ENT_SUBSTITUTE,
                            'UTF-8'
                        ) ?>"
                    >

                    <!-- ID del usuario -->
                    <input
                        type="hidden"
                        name="usuario_id"
                        value="<?= (int) $usuario['id'] ?>"
                    >
                    <div class="row g-3">

                        <!-- Nombre -->
                        <div class="col-md-6">
                            <label for="nombre" class="form-label">
                                Nombre
                            </label>
                            <input
                                type="text"
                                id="nombre"
                                name="nombre"
                                class="form-control"
                                maxlength="50"
                                value="<?= htmlspecialchars(
                                    $usuario['nombre'],
                                    ENT_QUOTES | ENT_SUBSTITUTE,
                                    'UTF-8'
                                ) ?>"
                                required
                            >
                        </div>

                        <!-- Apellido -->
                        <div class="col-md-6">
                            <label for="apellido" class="form-label">
                                Apellido
                            </label>
                            <input
                                type="text"
                                id="apellido"
                                name="apellido"
                                class="form-control"
                                maxlength="50"
                                value="<?= htmlspecialchars(
                                    $usuario['apellido'],
                                    ENT_QUOTES | ENT_SUBSTITUTE,
                                    'UTF-8'
                                ) ?>"
                                required
                            >
                        </div>

                        <!-- Correo -->
                        <div class="col-md-6">
                            <label for="email" class="form-label">
                                Correo electrónico
                            </label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                class="form-control"
                                maxlength="100"
                                value="<?= htmlspecialchars(
                                    $usuario['email'],
                                    ENT_QUOTES | ENT_SUBSTITUTE,
                                    'UTF-8'
                                ) ?>"
                                required
                            >
                        </div>

                        <!-- Nombre de usuario -->
                        <div class="col-md-6">
                            <label for="nom_usuario" class="form-label">
                                Nombre de usuario
                            </label>
                            <input
                                type="text"
                                id="nom_usuario"
                                name="nom_usuario"
                                class="form-control"
                                maxlength="50"
                                value="<?= htmlspecialchars(
                                    $usuario['nom_usuario'],
                                    ENT_QUOTES | ENT_SUBSTITUTE,
                                    'UTF-8'
                                ) ?>"
                                required
                            >
                        </div>

                        <!-- Rol -->
                        <div class="col-md-6">
                            <label for="rol_id" class="form-label">
                                Rol del usuario
                            </label>
                            <select
                                name="rol_id"
                                id="rol_id"
                                class="form-select"
                                required
                            >
                                <?php foreach ($roles as $rol): ?>
                                    <option
                                        value="<?= (int) $rol['id_rol'] ?>"
                                        <?= (int) $rol['id_rol'] === (int) $usuario['rol_id']
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= htmlspecialchars(
                                            $rol['nombre_rol'],
                                            ENT_QUOTES | ENT_SUBSTITUTE,
                                            'UTF-8'
                                        ) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="d-flex gap-2 mt-4">
                        <button
                            type="submit"
                            class="btn btn-success"
                        >
                            Guardar cambios
                        </button>
                        <a
                            href="users.php"
                            class="btn btn-secondary"
                        >
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
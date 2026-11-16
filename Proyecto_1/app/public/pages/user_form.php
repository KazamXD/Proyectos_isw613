
<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';

// Solo usuarios autorizados pueden crear usuarios
requerirPermiso($conn, 'usuarios.crear');
$tokenCSRF = generarTokenCSRF();
// Obtener los roles disponibles
$stmt = $conn->prepare("
    SELECT id_rol, nombre_rol
    FROM rol
    ORDER BY nombre_rol ASC
");

$stmt->execute();

$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
            <h1 class="fw-bold">
                Registrar nuevo usuario
            </h1>
            <p class="text-muted">
                Completa el formulario para agregar un usuario a SIGEL.
            </p>
        </div>

        <!-- Formulario -->
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    Información del usuario
                </h5>
            </div>
            
            <div class="card-body">

                <!-- Mensajes de error -->
                <?php
                $error = $_GET['error'] ?? '';
                $mensajes = [
                    'campos' => 'Revisa los campos del formulario.',
                    'longitud' => 'El nombre o usuario es demasiado largo.',
                    'duplicado' => 'El usuario o correo electrónico ya existe.',
                    'rol' => 'El rol seleccionado no es válido.',
                    'sistema' => 'Ocurrió un error al guardar el usuario.'
                ];
                ?>

                <?php if (is_string($error) && isset($mensajes[$error])): ?>
                    <div class="alert alert-danger">
                        <?= htmlspecialchars(
                            $mensajes[$error],
                            ENT_QUOTES | ENT_SUBSTITUTE,
                            'UTF-8'
                        ) ?>
                    </div>
                <?php endif; ?>

                <form
                    action="../actions/create_user.php"
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
                                maxlength="100"
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
                                required
                            >
                        </div>

                        <!-- Correo electrónico -->
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
                                required
                            >
                        </div>

                        <!-- Contraseña -->
                        <div class="col-md-6">
                            <label for="password" class="form-label">
                                Contraseña
                            </label>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-control"
                                minlength="8"
                                autocomplete="new-password"
                                required
                            >
                            <div class="form-text">
                                La contraseña debe tener al menos 8 caracteres.
                            </div>
                        </div>

                        <!-- Rol -->
                        <div class="col-md-6">
                            <label for="rol_id" class="form-label">
                                Rol del usuario
                            </label>
                            <select
                                id="rol_id"
                                name="rol_id"
                                class="form-select"
                                required
                            >
                                <option value="">
                                    Seleccione un rol
                                </option>
                                <?php foreach ($roles as $rol): ?>
                                    <option
                                        value="<?= (int) $rol['id_rol'] ?>"
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
                            class="btn btn-primary"
                        >
                            Guardar usuario
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
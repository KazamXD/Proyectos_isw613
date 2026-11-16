<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';

// Verificar autenticación
requerirLogin();

// Verificar que el usuario siga activo
verificarUsuarioActivo($conn);

// Consultar permisos del usuario
$stmt = $conn->prepare("
    SELECT p.clave
    FROM rol_permiso rp
    INNER JOIN permisos p
        ON rp.permiso_id = p.id_permiso
    WHERE rp.rol_id = :rol_id
    ORDER BY p.clave ASC
");

$stmt->execute([
    'rol_id' => $_SESSION['rol_id']
]);

$permisos = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Permisos para accesos rápidos
$puedeCrearUsuarios = tienePermiso($conn, 'usuarios.crear');
$puedeCrearEquipos = tienePermiso($conn, 'equipos.crear');
$puedeCrearPrestamos = tienePermiso($conn, 'prestamos.crear');
$puedeCrearSolicitudes = tienePermiso($conn, 'solicitudes.crear');

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
                Dashboard
            </h1>
            <p class="text-muted">
                Panel de administración de SIGEL
            </p>
        </div>

        <!-- Bienvenida -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h3 class="card-title">
                    Bienvenido,
                    <?= htmlspecialchars(
                        $_SESSION['nombre_usuario'],
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        'UTF-8'
                    ) ?>!
                </h3>
                <p class="card-text text-muted">
                    Has iniciado sesión correctamente.
                    Selecciona una opción para comenzar.
                </p>
            </div>
        </div>

        <!-- Accesos rápidos -->
        <h4 class="mb-3">
            Accesos rápidos
        </h4>

        <div class="row g-3 mb-4">
            <?php if ($puedeCrearUsuarios): ?>
                <div class="col-md-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="card-title">
                                Usuarios
                            </h5>
                            <p class="card-text">
                                Registrar nuevos usuarios en el sistema.
                            </p>
                            <a
                                href="user_form.php"
                                class="btn btn-primary"
                            >
                                Crear usuario
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($puedeCrearEquipos): ?>
                <div class="col-md-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="card-title">
                                Equipos
                            </h5>
                            <p class="card-text">
                                Registrar nuevos equipos de cómputo.
                            </p>
                            <a
                                href="equipment_form.php"
                                class="btn btn-success"
                            >
                                Crear equipo
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($puedeCrearPrestamos): ?>
                <div class="col-md-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="card-title">
                                Préstamos
                            </h5>
                            <p class="card-text">
                                Registrar un nuevo préstamo.
                            </p>
                            <a
                                href="loan_form.php"
                                class="btn btn-warning"
                            >
                                Nuevo préstamo
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($puedeCrearSolicitudes): ?>
                <div class="col-md-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="card-title">
                                Solicitudes
                            </h5>
                            <p class="card-text">
                                Solicitar equipos para una fecha determinada.
                            </p>
                            <a
                                href="request_form.php"
                                class="btn btn-info"
                            >
                                Nueva solicitud
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Permisos del usuario -->
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    Permisos de tu cuenta
                </h5>
            </div>
            <div class="card-body">
                <?php foreach ($permisos as $permiso): ?>
                    <span class="badge bg-secondary me-1 mb-2">
                        <?= htmlspecialchars(
                            $permiso,
                            ENT_QUOTES | ENT_SUBSTITUTE,
                            'UTF-8'
                        ) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
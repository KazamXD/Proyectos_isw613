
<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';

// Solo usuarios con este permiso
requerirPermiso($conn, 'usuarios.ver');

$tokenCSRF = generarTokenCSRF();
$puedeEliminar = tienePermiso($conn, 'usuarios.eliminar');

// Obtener búsqueda y paginación
$busqueda = $_GET['buscar'] ?? '';
if (!is_string($busqueda)) {
    $busqueda = '';
}

$busqueda = trim($busqueda);
$porPagina = (int) ($_GET['cantidad'] ?? 10);
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));

// Cantidades permitidas
if (!in_array($porPagina, [5, 10, 20, 50], true)) {
    $porPagina = 10;
}

// Construir el filtro de búsqueda

// Construir el filtro de búsqueda
$filtro = "WHERE desactivado_en IS NULL";
$parametros = [];

if ($busqueda !== '') {
    $filtro .= " AND (
        nombre LIKE :nombre
        OR apellido LIKE :apellido
        OR nom_usuario LIKE :usuario
        OR email LIKE :email
    )";

    $valorBusqueda = "%$busqueda%";

    $parametros = [
        'nombre' => $valorBusqueda,
        'apellido' => $valorBusqueda,
        'usuario' => $valorBusqueda,
        'email' => $valorBusqueda
    ];
}

// Contar registros para calcular páginas
$stmt = $conn->prepare(
    "SELECT COUNT(*) FROM usuarios $filtro"
);
$stmt->execute($parametros);

$totalRegistros = (int) $stmt->fetchColumn();
$totalPaginas = max(1, (int) ceil($totalRegistros / $porPagina));

$pagina = min($pagina, $totalPaginas);
$offset = ($pagina - 1) * $porPagina;

// Obtener los usuarios de la página actual
$stmt = $conn->prepare("
    SELECT id, nombre, nom_usuario, rol_id
    FROM usuarios
    $filtro
    ORDER BY id ASC
    LIMIT $porPagina OFFSET $offset
");

$stmt->execute($parametros);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="fw-bold mb-1">
                    Gestión de Usuarios
                </h1>
                <p class="text-muted mb-0">
                    Administración de usuarios registrados en SIGEL.
                </p>
            </div>

            <?php if (tienePermiso($conn, 'usuarios.crear')): ?>
                <a
                    href="user_form.php"
                    class="btn btn-primary"
                >
                    + Nuevo usuario
                </a>
            <?php endif; ?>
        </div>

        <!-- Mensajes -->
        <?php if (($_GET['success'] ?? '') === 'creado'): ?>

            <div class="alert alert-success">
                Usuario registrado correctamente.
            </div>

        <?php endif; ?>

        <?php if (($_GET['success'] ?? '') === 'editado'): ?>

            <div class="alert alert-success">
                Usuario actualizado correctamente.
            </div>

        <?php elseif (($_GET['success'] ?? '') === 'sin_cambios'): ?>

            <div class="alert alert-info">
                No se realizaron cambios.
            </div>

        <?php endif; ?>

        <?php
        $mensajes = [
            'eliminado' => 'Usuario desactivado correctamente.',
            'propio' => 'No puedes desactivar tu propia cuenta.',
            'prestamos' => 'El usuario tiene préstamos pendientes de devolución.',
            'no_disponible' => 'El usuario no existe o ya fue desactivado.',
            'sistema' => 'No se pudo desactivar el usuario.'
        ];
        $success = $_GET['success'] ?? '';
        $error = $_GET['error'] ?? '';
        ?>

        <?php if ($success === 'eliminado'): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars(
                    $mensajes['eliminado'],
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                ) ?>
            </div>
        <?php elseif (is_string($error) && isset($mensajes[$error])): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars(
                    $mensajes[$error],
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                ) ?>
            </div>
        <?php endif; ?>

        <!-- Tarjeta principal -->
        <div class="card shadow-sm">
            <div class="card-header bg-white">

                <h5 class="mb-0">
                    Usuarios registrados
                </h5>

            </div>

            <div class="card-body">

                <!-- Filtros -->
                <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="buscar" class="form-label">
                            Buscar usuario
                        </label>
                        <input
                            type="text"
                            id="buscar"
                            name="buscar"
                            class="form-control"
                            placeholder="Nombre, apellido, usuario o correo"
                            value="<?= htmlspecialchars(
                                $busqueda,
                                ENT_QUOTES | ENT_SUBSTITUTE,
                                'UTF-8'
                            ) ?>"
                        >
                    </div>
                    <div class="col-md-3">
                        <label for="cantidad" class="form-label">
                            Registros por página
                        </label>
                        <select
                            name="cantidad"
                            id="cantidad"
                            class="form-select"
                        >
                            <?php foreach ([5, 10, 20, 50] as $cantidad): ?>
                                <option
                                    value="<?= $cantidad ?>"
                                    <?= $porPagina === $cantidad ? 'selected' : '' ?>
                                >
                                    <?= $cantidad ?> registros
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">

                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                        >
                            Buscar
                        </button>
                    </div>
                </form>

                <!-- Tabla -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Usuario</th>
                                <th>ID Rol</th>
                                <th>Acciones</th>
                            </tr>

                        </thead>

                        <tbody>
                            <?php foreach ($usuarios as $usuario): ?>
                                <tr>
                                    <td>
                                        <?= (int) $usuario['id'] ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars(
                                            $usuario['nombre'],
                                            ENT_QUOTES | ENT_SUBSTITUTE,
                                            'UTF-8'
                                        ) ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars(
                                            $usuario['nom_usuario'],
                                            ENT_QUOTES | ENT_SUBSTITUTE,
                                            'UTF-8'
                                        ) ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?= (int) $usuario['rol_id'] ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php if (tienePermiso($conn, 'usuarios.editar')): ?>
                                            <a
                                                href="edit_user.php?id=<?= (int) $usuario['id'] ?>"
                                                class="btn btn-warning btn-sm"
                                            >
                                                Editar
                                            </a>
                                        <?php endif; ?>
                                        <?php if (
                                            $puedeEliminar &&
                                            (int) $usuario['id'] !== (int) $_SESSION['usuario_id']
                                        ): ?>
                                            <form
                                                action="../actions/delete_user.php"
                                                method="POST"
                                                class="d-inline"
                                                onsubmit="return confirm('¿Seguro que deseas desactivar este usuario?');"
                                            >
                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= htmlspecialchars(
                                                        $tokenCSRF,
                                                        ENT_QUOTES | ENT_SUBSTITUTE,
                                                        'UTF-8'
                                                    ) ?>"
                                                >
                                                <input
                                                    type="hidden"
                                                    name="usuario_id"
                                                    value="<?= (int) $usuario['id'] ?>"
                                                >
                                                <button
                                                    type="submit"
                                                    class="btn btn-danger btn-sm"
                                                >
                                                    Eliminar
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (empty($usuarios)): ?>
                    <div class="alert alert-info">
                        No se encontraron usuarios.
                    </div>
                <?php endif; ?>

                <!-- Paginación -->
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <p class="text-muted mb-0">
                        Página <?= $pagina ?> de <?= $totalPaginas ?>
                        (<?= $totalRegistros ?> registros)
                    </p>

                    <div>
                        <?php if ($pagina > 1): ?>
                            <a
                                class="btn btn-outline-primary btn-sm"
                                href="?<?= http_build_query([
                                    'buscar' => $busqueda,
                                    'cantidad' => $porPagina,
                                    'pagina' => $pagina - 1
                                ]) ?>"
                            >
                                Anterior
                            </a>
                        <?php endif; ?>

                        <?php if ($pagina < $totalPaginas): ?>
                            <a
                                class="btn btn-outline-primary btn-sm"
                                href="?<?= http_build_query([
                                    'buscar' => $busqueda,
                                    'cantidad' => $porPagina,
                                    'pagina' => $pagina + 1
                                ]) ?>"
                            >
                                Siguiente
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

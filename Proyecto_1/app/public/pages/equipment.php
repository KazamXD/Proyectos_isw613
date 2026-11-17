<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/recent_equipment.php';

// Solo usuarios autorizados pueden consultar reportes
requerirPermiso($conn, 'equipos.ver');
$tokenCSRF = generarTokenCSRF();
$puedeEliminar = tienePermiso($conn, 'equipos.eliminar');

// Obtener el equipo que el usuario desea consultar
$equipoConsultadoId = filter_var(
    $_GET['ver'] ?? null,
    FILTER_VALIDATE_INT
);

$equipoConsultado = null;

if ($equipoConsultadoId !== false && $equipoConsultadoId !== null && $equipoConsultadoId > 0) {

    // Comprobar que el equipo existe y no está eliminado
    $stmt = $conn->prepare("
        SELECT
            e.id_equipo,
            e.codigo,
            e.nombre_estado,
            c.categoria_nombre
        FROM equipos e
        INNER JOIN categorias c
            ON e.id_categoria = c.categoria_id
        WHERE e.id_equipo = :id
          AND e.eliminado_en IS NULL
        LIMIT 1
    ");

    $stmt->execute([
        'id' => $equipoConsultadoId
    ]);

    $equipoConsultado = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($equipoConsultado) {
        registrarEquipoReciente((int) $equipoConsultado['id_equipo']);
    }
}

// Obtener los identificadores recientes
$idsRecientes = obtenerEquiposRecientes();

$equiposRecientes = [];

if (!empty($idsRecientes)) {

    // Crear un marcador por cada ID
    $marcadores = implode(
        ',',
        array_fill(0, count($idsRecientes), '?')
    );

    $stmt = $conn->prepare("
        SELECT
            id_equipo,
            codigo,
            nombre_estado
        FROM equipos
        WHERE id_equipo IN ($marcadores)
          AND eliminado_en IS NULL
    ");

    $stmt->execute($idsRecientes);

    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organizar por ID para facilitar la búsqueda
    $equiposPorId = [];

    foreach ($resultados as $equipo) {
        $equiposPorId[(int) $equipo['id_equipo']] = $equipo;
    }

    // Conservar el orden de la cookie: más reciente primero
    foreach ($idsRecientes as $id) {
        if (isset($equiposPorId[$id])) {
            $equiposRecientes[] = $equiposPorId[$id];
        }
    }
}

//Ordenar datos de la tabla
// Obtener el orden seleccionado
$orden = $_GET['orden'] ?? 'categoria';

// Lista de opciones permitidas
$ordenesPermitidos = [
    'id_asc' => 'e.id_equipo ASC',
    'id_desc' => 'e.id_equipo DESC',
    'categoria' => 'c.categoria_nombre ASC, e.codigo ASC',
    'codigo_asc' => 'e.codigo ASC',
    'codigo_desc' => 'e.codigo DESC',
    'estado' => 'e.nombre_estado ASC, e.codigo ASC'
];

// Validar la opción recibida
if (!is_string($orden) || !isset($ordenesPermitidos[$orden])) {
    $orden = 'categoria';
}
$ordenSQL = $ordenesPermitidos[$orden];

// Obtener el texto de búsqueda
$busqueda = $_GET['buscar'] ?? '';

if (!is_string($busqueda)) {
    $busqueda = '';
}

$busqueda = trim($busqueda);

// Preparar los filtros
$filtro = "WHERE e.eliminado_en IS NULL";
$parametros = [];

if ($busqueda !== '') {

    $filtro .= "
        AND (
            e.codigo LIKE :codigo
            OR c.categoria_nombre LIKE :categoria
            OR e.nombre_estado LIKE :estado
        )
    ";

    $valorBusqueda = "%$busqueda%";

    $parametros = [
        'codigo' => $valorBusqueda,
        'categoria' => $valorBusqueda,
        'estado' => $valorBusqueda
    ];
}

//Consulta de equipos
$stmt = $conn->prepare("
    SELECT
        e.id_equipo,
        e.codigo,
        c.categoria_nombre,
        e.nombre_estado,
        e.ruta_imagen
    FROM equipos e
    INNER JOIN categorias c
        ON e.id_categoria = c.categoria_id
    $filtro
    ORDER BY $ordenSQL
");
$stmt->execute($parametros);
$equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                    Gestión de Equipos
                </h1>
                <p class="text-muted mb-0">
                    Consulta y administración de los equipos registrados en SIGEL.
                </p>
            </div>

            <?php if (tienePermiso($conn, 'equipos.crear')): ?>
                <a
                    href="equipment_form.php"
                    class="btn btn-success"
                >
                    + Nuevo equipo
                </a>
            <?php endif; ?>
        </div>

        <!-- Equipos recientes -->
        <?php if (!empty($equiposRecientes)): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        Equipos consultados recientemente
                    </h5>
                </div>

                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($equiposRecientes as $reciente): ?>

                            <a
                                href="equipment.php?<?= http_build_query([
                                    'ver' => (int) $reciente['id_equipo']
                                ]) ?>"
                                class="btn btn-outline-primary"
                            >
                                <?= htmlspecialchars(
                                    $reciente['codigo'],
                                    ENT_QUOTES | ENT_SUBSTITUTE,
                                    'UTF-8'
                                ) ?>
                            </a>

                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Mensajes -->
        <?php if (($_GET['success'] ?? '') === 'creado'): ?>
            <div class="alert alert-success">
                Equipo registrado correctamente.
            </div>
        <?php endif; ?>

        <?php if (($_GET['success'] ?? '') === 'editado'): ?>
            <div class="alert alert-success">
                Equipo actualizado correctamente.
            </div>
        <?php elseif (($_GET['success'] ?? '') === 'sin_cambios'): ?>
            <div class="alert alert-info">
                No se realizaron cambios.
            </div>
        <?php endif; ?>

        <?php if (($_GET['success'] ?? '') === 'eliminado'): ?>
            <div class="alert alert-success">
                Equipo eliminado correctamente.
            </div>
        <?php endif; ?>

        <?php if ($equipoConsultado): ?>

            <div class="card shadow-sm border-info mb-4">

                <div class="card-header bg-info-subtle">
                    <h5 class="mb-0">
                        Equipo consultado
                    </h5>
                </div>

                <div class="card-body">

                    <h5>
                        <?= htmlspecialchars(
                            $equipoConsultado['codigo'],
                            ENT_QUOTES | ENT_SUBSTITUTE,
                            'UTF-8'
                        ) ?>
                    </h5>

                    <p class="mb-1">
                        Categoría:
                        <?= htmlspecialchars(
                            $equipoConsultado['categoria_nombre'],
                            ENT_QUOTES | ENT_SUBSTITUTE,
                            'UTF-8'
                        ) ?>
                    </p>

                    <p>
                        Estado:
                        <?= htmlspecialchars(
                            $equipoConsultado['nombre_estado'],
                            ENT_QUOTES | ENT_SUBSTITUTE,
                            'UTF-8'
                        ) ?>
                    </p>

                    <a href="equipment.php" class="btn btn-secondary btn-sm">
                        Cerrar detalle
                    </a>

                </div>

            </div>

        <?php endif; ?>

        <!-- Tarjeta principal -->
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    Equipos registrados
                </h5>
            </div>
            <div class="card-body">

                <!-- Filtros -->
                <form
                    method="GET"
                    action="equipment.php"
                    class="row g-3 mb-4"
                >
                    <!-- Búsqueda -->
                    <div class="col-md-6">
                        <label for="buscar" class="form-label">
                            Buscar equipo
                        </label>
                        <input
                            type="text"
                            id="buscar"
                            name="buscar"
                            class="form-control"
                            placeholder="Código, categoría o estado"
                            value="<?= htmlspecialchars(
                                $busqueda,
                                ENT_QUOTES | ENT_SUBSTITUTE,
                                'UTF-8'
                            ) ?>"
                        >
                    </div>

                    <!-- Ordenamiento -->
                    <div class="col-md-4">
                        <label for="orden" class="form-label">
                            Ordenar equipos por
                        </label>
                        <select
                            name="orden"
                            id="orden"
                            class="form-select"
                        >
                            <option value="categoria"
                                <?= $orden === 'categoria' ? 'selected' : '' ?>>
                                Categoría (A-Z)
                            </option>
                            <option value="id_asc"
                                <?= $orden === 'id_asc' ? 'selected' : '' ?>>
                                ID (ascendente)
                            </option>
                            <option value="id_desc"
                                <?= $orden === 'id_desc' ? 'selected' : '' ?>>
                                ID (descendente)
                            </option>
                            <option value="codigo_asc"
                                <?= $orden === 'codigo_asc' ? 'selected' : '' ?>>
                                Código (A-Z)
                            </option>
                            <option value="codigo_desc"
                                <?= $orden === 'codigo_desc' ? 'selected' : '' ?>>
                                Código (Z-A)
                            </option>
                            <option value="estado"
                                <?= $orden === 'estado' ? 'selected' : '' ?>>
                                Estado
                            </option>
                        </select>
                    </div>

                    <!-- Botón filtrar -->
                    <div class="col-md-2 d-flex align-items-end">
                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                        >
                            Filtrar
                        </button>
                    </div>
                </form>

                <!-- Tabla -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Código</th>
                                <th>Categoría</th>
                                <th>Estado</th>
                                <th>Imagen</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($equipos as $equipo): ?>
                                <tr>
                                    <td>
                                        <?= (int) $equipo['id_equipo'] ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars(
                                            $equipo['codigo'],
                                            ENT_QUOTES | ENT_SUBSTITUTE,
                                            'UTF-8'
                                        ) ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars(
                                            $equipo['categoria_nombre'],
                                            ENT_QUOTES | ENT_SUBSTITUTE,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <!-- Estado -->
                                    <td>
                                        <?php
                                        $estado = $equipo['nombre_estado'];
                                        $claseEstado = match ($estado) {
                                            'Disponible' => 'bg-success',
                                            'Prestado' => 'bg-warning text-dark',
                                            'Mantenimiento' => 'bg-danger',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?= $claseEstado ?>">
                                            <?= htmlspecialchars(
                                                $estado,
                                                ENT_QUOTES | ENT_SUBSTITUTE,
                                                'UTF-8'
                                            ) ?>
                                        </span>
                                    </td>

                                    <!-- Imagen -->
                                    <td>
                                        <?php if (!empty($equipo['ruta_imagen'])): ?>
                                            <img
                                                src="../uploads/equipment/<?= rawurlencode($equipo['ruta_imagen']) ?>"
                                                alt="Imagen del equipo"
                                                class="img-thumbnail"
                                                style="width: 90px; height: 70px; object-fit: cover;"
                                            >
                                        <?php else: ?>
                                            <span class="text-muted">
                                                Sin imagen
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Acciones -->
                                    <td>
                                        <a
                                            href="equipment.php?<?= http_build_query([
                                                'ver' => (int) $equipo['id_equipo']
                                            ]) ?>"
                                            class="btn btn-info btn-sm"
                                        >
                                            Ver
                                        </a>
                                        <?php if (tienePermiso($conn, 'equipos.editar')): ?>

                                            <a
                                                href="edit_equipment.php?id=<?= (int)$equipo['id_equipo'] ?>"
                                                class="btn btn-warning btn-sm"
                                            >
                                                Editar
                                            </a>

                                        <?php endif; ?>

                                        <?php if ($puedeEliminar): ?>
                                            <form
                                                action="../actions/delete_equipment.php"
                                                method="POST"
                                                class="d-inline"
                                                onsubmit="return confirm('¿Seguro que deseas eliminar este equipo?');"
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
                                                    name="equipo_id"
                                                    value="<?= (int)$equipo['id_equipo'] ?>"
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
                <?php if (empty($equipos)): ?>
                    <div class="alert alert-info">
                        No se encontraron equipos con esos criterios.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

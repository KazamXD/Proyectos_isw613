<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/connection.php';

// Verificar que el usuario tenga permiso
requerirPermiso($conn, 'reportes.ver');

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
                Reportes del Sistema
            </h1>
            <p class="text-muted">
                Consulta información y estadísticas de SIGEL.
            </p>
        </div>

        <!-- Tarjetas de reportes -->
        <div class="row g-4">
            <!-- Inventario -->
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">
                            Inventario de Equipos
                        </h5>
                        <p class="card-text text-muted">
                            Consulta los equipos registrados,
                            sus categorías y estados actuales.
                        </p>
                        <div class="mt-auto">
                            <a
                                href="report_equipment.php"
                                class="btn btn-primary"
                            >
                                Ver inventario
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Préstamos retrasados -->
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">
                            Préstamos Retrasados
                        </h5>
                        <p class="card-text text-muted">
                            Consulta los préstamos que superaron
                            su fecha límite y todavía no han sido devueltos.
                        </p>
                        <div class="mt-auto">
                            <a
                                href="report_overdue.php"
                                class="btn btn-danger"
                            >
                                Ver préstamos retrasados
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Historial de préstamos -->
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">
                            Historial de Préstamos
                        </h5>
                        <p class="card-text text-muted">
                            Consulta el historial de préstamos realizados,
                            incluyendo préstamos activos, devueltos
                            y retrasados.
                        </p>
                        <div class="mt-auto">
                            <a
                                href="report_history.php"
                                class="btn btn-success"
                            >
                                Ver historial
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bitácora -->
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">
                            Bitácora del Sistema
                        </h5>
                        <p class="card-text text-muted">
                            Consulta las operaciones realizadas por
                            los usuarios, incluyendo creación,
                            edición y eliminación de registros.
                        </p>
                        <div class="mt-auto">
                            <a
                                href="audit_log.php"
                                class="btn btn-secondary"
                            >
                                Consultar bitácora
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
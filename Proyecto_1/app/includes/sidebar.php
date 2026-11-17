
<?php require_once __DIR__ . '/../config/paths.php'; ?>
<?php

// Se espera que la página haya cargado auth.php y connection.php
// y que $conn sea una conexión PDO válida.

?>

<nav class="bg-dark text-white p-3">

    <h4 class="text-center mb-4">
        SIGEL
    </h4>

    <ul class="nav flex-column">

        <li class="nav-item">
            <a
                class="nav-link text-white"
                href="<?= BASE_URL ?>/pages/dashboard.php">
                Dashboard
            </a>
        </li>

        <?php if (tienePermiso($conn, 'usuarios.ver')): ?>

            <li class="nav-item">
                <a
                    class="nav-link text-white"
                    href="<?= BASE_URL ?>/pages/users.php"
                >
                    Usuarios
                </a>
            </li>

        <?php endif; ?>

        <?php if (tienePermiso($conn, 'categorias.ver')): ?>

            <li class="nav-item">
                <a
                    class="nav-link text-white"
                    href="<?= BASE_URL ?>/pages/categories.php"
                >
                    Categorías
                </a>
            </li>

        <?php endif; ?>

        <?php if (tienePermiso($conn, 'equipos.ver')): ?>

            <li class="nav-item">
                <a
                    class="nav-link text-white"
                    href="<?= BASE_URL ?>/pages/equipment.php"
                >
                    Equipos
                </a>
            </li>

        <?php endif; ?>

        <?php if (tienePermiso($conn, 'prestamos.ver')): ?>

            <li class="nav-item">
                <a
                   class="nav-link text-white"
                   href="<?= BASE_URL ?>/pages/loans.php"
                >
                    Préstamos
                </a>
            </li>

        <?php endif; ?>

        <?php if (
            tienePermiso($conn, 'solicitudes.ver_propias') ||
            tienePermiso($conn, 'solicitudes.ver_todas')
        ): ?>

            <li class="nav-item">
                <a
                    class="nav-link text-white"
                    href="<?= BASE_URL ?>/pages/requests.php"
                >
                    Solicitudes
                </a>
            </li>

        <?php endif; ?>

        <?php if (tienePermiso($conn, 'reportes.ver')): ?>

            <li class="nav-item">
                <a
                    class="nav-link text-white"
                    href="<?= BASE_URL ?>/pages/reports.php"
                >
                    Reportes
                </a>
            </li>

        <?php endif; ?>

        <hr>

        <li class="nav-item">
            <a
                class="nav-link text-white"
                href="<?= BASE_URL ?>/actions/logout.php"
            >
                Cerrar sesión
            </a>
        </li>

    </ul>

</nav>
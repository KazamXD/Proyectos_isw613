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
                href="/Proyecto_1/app/public/pages/dashboard.php"
            >
                Dashboard
            </a>
        </li>

        <?php if (tienePermiso($conn, 'usuarios.ver')): ?>

            <li class="nav-item">
                <a
                    class="nav-link text-white"
                    href="/Proyecto_1/app/public/pages/users.php"
                >
                    Usuarios
                </a>
            </li>

        <?php endif; ?>

        <?php if (tienePermiso($conn, 'categorias.ver')): ?>

            <li class="nav-item">
                <a
                    class="nav-link text-white"
                    href="/Proyecto_1/app/public/pages/categories.php"
                >
                    Categorías
                </a>
            </li>

        <?php endif; ?>

        <?php if (tienePermiso($conn, 'equipos.ver')): ?>

            <li class="nav-item">
                <a
                    class="nav-link text-white"
                    href="/Proyecto_1/app/public/pages/equipment.php"
                >
                    Equipos
                </a>
            </li>

        <?php endif; ?>

        <?php if (tienePermiso($conn, 'prestamos.ver')): ?>

            <li class="nav-item">
                <a
                    class="nav-link text-white"
                    href="/Proyecto_1/app/public/pages/loans.php"
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
                    href="/Proyecto_1/app/public/pages/requests.php"
                >
                    Solicitudes
                </a>
            </li>

        <?php endif; ?>

        <?php if (tienePermiso($conn, 'reportes.ver')): ?>

            <li class="nav-item">
                <a
                    class="nav-link text-white"
                    href="/Proyecto_1/app/public/pages/reports.php"
                >
                    Reportes
                </a>
            </li>

        <?php endif; ?>

        <hr>

        <li class="nav-item">
            <a
                class="nav-link text-danger"
                href="/Proyecto_1/app/public/actions/logout.php"
            >
                Cerrar sesión
            </a>
        </li>

    </ul>

</nav>
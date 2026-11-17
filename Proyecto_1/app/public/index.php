
<?php
session_start();

// Si ya inició sesión, ir al dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: pages/dashboard.php');
    exit;
}

$error = $_GET['error'] ?? '';
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-md-6 col-lg-5 col-xl-4">

            <!-- Tarjeta de login -->
            <div class="card shadow-lg border-0 rounded-4">

                <div class="card-body p-4 p-md-5">

                    <!-- Encabezado -->
                    <div class="text-center mb-4">
                        <h1 class="fw-bold text-primary mb-2">
                            SIGEL
                        </h1>
                        <p class="text-muted small">
                            Sistema de Gestión de Préstamo
                            de Equipos de Laboratorio
                        </p>
                        <h4 class="fw-semibold mt-4">
                            Iniciar sesión
                        </h4>
                    </div>

                    <!-- Mensajes de error -->
                    <?php if ($error === 'credenciales'): ?>
                        <div class="alert alert-danger">
                            Usuario o contraseña incorrectos.
                        </div>
                    <?php elseif ($error === 'campos'): ?>
                        <div class="alert alert-warning">
                            Completa todos los campos.
                        </div>
                    <?php elseif ($error === 'sistema'): ?>
                        <div class="alert alert-danger">
                            No se pudo iniciar sesión.
                            Intenta nuevamente.
                        </div>
                    <?php endif; ?>

                    <!-- Formulario -->
                    <form
                        method="POST"
                        action="actions/login.php"
                    >

                        <!-- Usuario -->
                        <div class="mb-3">
                            <label
                                for="nom_usuario"
                                class="form-label"
                            >
                                Nombre de usuario
                            </label>
                            <input
                                type="text"
                                id="nom_usuario"
                                name="nom_usuario"
                                class="form-control"
                                placeholder="Ingresa tu usuario"
                                autocomplete="username"
                                required
                            >
                        </div>

                        <!-- Contraseña -->
                        <div class="mb-3">
                            <label
                                for="password"
                                class="form-label"
                            >
                                Contraseña
                            </label>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-control"
                                placeholder="Ingresa tu contraseña"
                                autocomplete="current-password"
                                required
                            >
                        </div>

                        <!-- Recordarme -->
                        <div class="form-check mb-4">
                            <input
                                type="checkbox"
                                id="recordarme"
                                name="recordarme"
                                value="1"
                                class="form-check-input"
                            >
                            <label
                                for="recordarme"
                                class="form-check-label"
                            >
                                Recordarme durante 14 días
                            </label>
                        </div>

                        <!-- Botón -->
                        <div class="d-grid">
                            <button
                                type="submit"
                                class="btn btn-success btn-lg"
                            >
                                Iniciar sesión
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Pie de página -->
            <p class="text-center text-muted small mt-4">
                SIGEL - Gestión de Equipos de Laboratorio
            </p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
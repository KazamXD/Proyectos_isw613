<?php

session_start();

// Vaciar variables de sesión
session_unset();

// Destruir la sesión
session_destroy();

header("Location: ../index.php");
exit;
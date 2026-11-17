<!-- Rutas para que no se rompa con windows -->
<?php
$rutaAssets = str_contains($_SERVER['SCRIPT_NAME'], '/pages/')
    ? '../assets'
    : 'assets';
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>SIGEL - Gestión de Equipos</title>

    <!-- Bootstrap -->
    <link
        rel="stylesheet"
        href="<?= $rutaAssets ?>/css/bootstrap.min.css"
    >

    <!-- Estilos personalizados -->
    <link
        rel="stylesheet"
        href="<?= $rutaAssets ?>/css/custom.css"
    >

</head>

<body class="bg-light">
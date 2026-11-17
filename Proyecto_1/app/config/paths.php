<?php

$documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
$publicDir = realpath(__DIR__ . '/../public');

if ($documentRoot === false || $publicDir === false) {
    throw new RuntimeException('No se pudo determinar la ruta pública.');
}

$documentRoot = str_replace('\\', '/', $documentRoot);
$publicDir = str_replace('\\', '/', $publicDir);

$prefijo = rtrim($documentRoot, '/') . '/';

if (!str_starts_with($publicDir . '/', $prefijo)) {
    throw new RuntimeException('La carpeta pública no está dentro del DocumentRoot.');
}

$ruta = substr($publicDir, strlen(rtrim($documentRoot, '/')));

define('BASE_URL', rtrim($ruta, '/'));
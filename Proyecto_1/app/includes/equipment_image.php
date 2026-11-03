<?php

// Carpeta donde guardaremos las imágenes
const EQUIPMENT_IMAGES_DIR = __DIR__ . '/../public/uploads/equipment';

// Tamaño máximo permitido: 2 MB
const EQUIPMENT_IMAGE_MAX_BYTES = 2 * 1024 * 1024;

// Tipos de imagen permitidos
const EQUIPMENT_IMAGE_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp'
];

function guardarImagenEquipo(array $archivo): array
{
    // Comprobar si PHP recibió el archivo correctamente
    if (
        !isset($archivo['error']) ||
        is_array($archivo['error'])
    ) {
        return ['error' => 'Archivo no válido.'];
    }

    // Comprobar errores de tamaño
    if (
        $archivo['error'] === UPLOAD_ERR_INI_SIZE ||
        $archivo['error'] === UPLOAD_ERR_FORM_SIZE
    ) {
        return ['error' => 'La imagen supera el tamaño permitido.'];
    }

    // Verificar que la subida haya sido correcta
    if (
        $archivo['error'] !== UPLOAD_ERR_OK ||
        !isset($archivo['tmp_name']) ||
        !is_uploaded_file($archivo['tmp_name'])
    ) {
        return ['error' => 'No se pudo subir la imagen.'];
    }

    // Comprobar que no supere los 2 MB
    if (
        !isset($archivo['size']) ||
        $archivo['size'] > EQUIPMENT_IMAGE_MAX_BYTES
    ) {
        return ['error' => 'La imagen supera los 2 MB.'];
    }

    // Detectar el tipo real del archivo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $tipoMime = $finfo->file($archivo['tmp_name']);

    // Aceptar solamente imágenes permitidas
    if (
        !isset(EQUIPMENT_IMAGE_TYPES[$tipoMime]) ||
        getimagesize($archivo['tmp_name']) === false
    ) {
        return ['error' => 'El archivo no es una imagen válida.'];
    }

    // Generar un nombre único
    $nombreArchivo = bin2hex(random_bytes(16))
        . '.'
        . EQUIPMENT_IMAGE_TYPES[$tipoMime];

    // Guardar la imagen en la carpeta correspondiente
    $rutaDestino = EQUIPMENT_IMAGES_DIR . '/' . $nombreArchivo;

    if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
        error_log('No se pudo guardar la imagen del equipo.');
        return ['error' => 'No se pudo guardar la imagen.'];
    }

    // Devolver el nombre del archivo guardado
    return ['filename' => $nombreArchivo];
}

function eliminarImagenEquipo(string $nombreArchivo): void
{
    // Aceptar únicamente nombres generados por nuestro sistema
    if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $nombreArchivo)) {
        return;
    }

    $ruta = EQUIPMENT_IMAGES_DIR . '/' . $nombreArchivo;

    if (is_file($ruta)) {
        unlink($ruta);
    }
}
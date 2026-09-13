<?php

use Illuminate\Support\Str;

if (! function_exists('mayusculas')) {
    /**
     * Convierte un texto a MAYÚSCULAS de forma segura (multibyte UTF-8).
     *
     * Helper global para los displays de la página pública: los nombres de
     * producto se muestran en mayúsculas (cards, detalle, destacados...).
     * Usa Str::upper (multibyte) y tolera valores nulos devolviendo ''.
     */
    function mayusculas(?string $texto): string
    {
        return Str::upper($texto ?? '');
    }
}

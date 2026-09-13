<?php

/*
|--------------------------------------------------------------------------
| Configuración del negocio — sitio público
|--------------------------------------------------------------------------
|
| Datos de identidad y contacto del negocio usados en la página pública
| (cabecera, pie, WhatsApp, redes sociales). Valores por defecto de
| Carwash El Chinito.
|
| FUTURO: estas mismas claves serán editables desde el panel de
| contenidos/personalización (se persistirán en BD y se inyectarán
| aquí o directamente a las vistas). Mientras tanto, centralizar
| aquí evita valores dispersos en las plantillas.
*/

return [

    'nombre' => 'Carwash El Chinito',

    'telefono' => env('CARWASH_TELEFONO', '955 555 555'),

    'email' => env('CARWASH_EMAIL', 'contacto@carwashelchinito.com'),

    'direccion' => env('CARWASH_DIRECCION', 'Av. Ejemplo 123, Lima, Perú'),

    'horario' => env('CARWASH_HORARIO', 'Lun – Sáb · 8:00 a.m. – 6:00 p.m.'),

    // Redes sociales (url) — futuras desde panel
    'redes' => [
        'facebook' => 'https://facebook.com/carwashelchinito',
        'instagram' => 'https://instagram.com/carwashelchinito',
        'youtube' => 'https://youtube.com/@carwashelchinito',
        'tiktok' => 'https://tiktok.com/@carwashelchinito',
    ],

    // Enlaces útiles del pie de página
    'enlaces' => [
        'preguntas-frecuentes' => '#',
        'servicios-empresas' => '#',
        'terminos' => '#',
        'politica-cookies' => '#',
        'libro-reclamaciones' => '#',
    ],

];

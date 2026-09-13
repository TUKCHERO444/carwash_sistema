<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Futuro: título y descripción dinámicos (SEO) desde el panel de contenidos. --}}
    <title>@yield('titulo', 'Carwash El Chinito — Cuidado automotriz de confianza')</title>
    <meta name="description" content="@yield('descripcion', 'Lavado especializado, detailing, cambio de aceite y productos para tu auto. Carwash El Chinito, cuidado automotriz de confianza.')">
    <meta name="theme-color" content="#0B2638">

    {{-- Tipografía de marca: Barlow (docs/design.md §11). El tema referencia la
         self-hoste; nosotros la cargamos vía Google Fonts con fallback a sistema. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:ital,wght@0,500;0,600;0,700;1,500;1,600;1,700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/css/publica/publica.css', 'resources/js/publica/app.js'])
</head>
<body class="cw-body">

    {{-- Cabecera global del sitio público --}}
    @include('publica.partials.header')

    {{-- Contenido de cada página pública --}}
    <main id="main" role="main" class="min-h-screen">
        @yield('content')
    </main>

    {{-- Pie global del sitio público --}}
    @include('publica.partials.footer')

</body>
</html>
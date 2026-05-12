# Plan de Implementación: Responsive Layout

## Descripción general

Implementar el layout principal de la aplicación Laravel con Tailwind CSS v4 integrado via Vite (sin CDN), un Sidebar de navegación lateral para desktop (≥ 1024 px) y una Bottom Nav fija para móvil (< 1024 px), con un Content Area que expone `@yield('content')` para las vistas hijas.

## Tareas

- [x] 1. Verificar y completar la configuración de Tailwind CSS v4 via Vite
  - Confirmar que `vite.config.js` registra `@tailwindcss/vite` junto con `laravel-vite-plugin`
  - Confirmar que `resources/css/app.css` contiene `@import 'tailwindcss'` y las directivas `@source` necesarias para escanear las vistas Blade y archivos JS
  - Asegurarse de que `resources/js/app.js` importa `./bootstrap` y que el entry point está declarado en `vite.config.js`
  - No añadir ninguna referencia a CDN de Tailwind en ningún archivo
  - _Requirements: 1.1, 1.2, 1.3, 1.4_

- [x] 2. Crear el layout principal `resources/views/layouts/app.blade.php`
  - [x] 2.1 Crear el archivo con la estructura HTML base
    - Declarar `<!DOCTYPE html>` y `<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">`
    - Incluir `<meta charset="utf-8">`, `<meta name="viewport" content="width=device-width, initial-scale=1">` y `<title>{{ config('app.name', 'Laravel') }}</title>`
    - Cargar assets con `@vite(['resources/css/app.css', 'resources/js/app.js'])` en el `<head>`, sin ningún CDN
    - Definir `<body class="flex h-screen bg-gray-50">` como contenedor flex raíz
    - _Requirements: 1.1, 4.5, 5.3, 5.4_

  - [x] 2.2 Implementar el Sidebar para desktop
    - Crear `<aside class="hidden lg:flex flex-col w-64 bg-white border-r border-gray-200">` como columna izquierda del flex container
    - Añadir `<nav aria-label="Navegación principal">` dentro del `<aside>` con los enlaces de navegación principales (Dashboard y cualquier sección relevante del proyecto)
    - Aplicar detección de enlace activo con `request()->routeIs('nombre.ruta') ? 'bg-gray-200 font-semibold' : 'hover:bg-gray-100'` en cada enlace
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 5.1, 5.5_

  - [x] 2.3 Implementar el Content Area
    - Crear `<main class="flex-1 overflow-y-auto pb-16 lg:pb-0 p-6">` como columna derecha del flex container
    - Incluir `@yield('content')` dentro del `<main>`
    - _Requirements: 4.1, 4.2, 4.3, 3.4_

  - [x] 2.4 Implementar la Bottom Nav para móvil
    - Crear `<nav aria-label="Navegación móvil" class="lg:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 flex justify-around items-center h-16">` al final del `<body>`
    - Añadir los mismos enlaces de navegación que el Sidebar, garantizando paridad funcional (mismos `href`)
    - Aplicar la misma lógica de enlace activo que en el Sidebar
    - _Requirements: 3.1, 3.2, 3.3, 3.5, 5.2, 5.5_

- [x] 3. Crear una vista de prueba para verificar la integración del layout
  - Crear `resources/views/dashboard.blade.php` que extienda el layout con `@extends('layouts.app')` y defina `@section('content')` con contenido de prueba
  - Añadir una ruta `GET /dashboard` en `routes/web.php` que retorne esta vista
  - _Requirements: 4.4_

- [x] 4. Escribir los Feature Tests en `tests/Feature/LayoutTest.php`
  - [x] 4.1 Implementar test: el layout renderiza sin errores (HTTP 200)
    - Hacer GET a `/dashboard` y verificar que la respuesta es 200
    - _Requirements: 4.4_

  - [ ]* 4.2 Implementar test: `@yield('content')` inyecta el contenido correcto
    - Verificar que el HTML de respuesta contiene el texto definido en `@section('content')`
    - _Requirements: 4.1, 4.4_

  - [ ]* 4.3 Implementar test: atributo `lang` presente en `<html>`
    - Verificar que el HTML contiene `<html lang="`
    - _Requirements: 5.3_

  - [ ]* 4.4 Implementar test: meta viewport presente
    - Verificar que el HTML contiene `name="viewport"`
    - _Requirements: 5.4_

  - [ ]* 4.5 Implementar test: atributos ARIA presentes en ambos navs
    - Verificar que el HTML contiene `aria-label="Navegación principal"` y `aria-label="Navegación móvil"`
    - _Requirements: 5.1, 5.2_

  - [ ]* 4.6 Implementar test: no hay referencias a CDN de Tailwind
    - Verificar que el HTML no contiene `cdn.tailwindcss.com` ni `unpkg.com/tailwindcss`
    - _Requirements: 1.1_

  - [ ]* 4.7 Implementar test: enlace activo tiene clases diferenciadas
    - Para la ruta `/dashboard`, verificar que el enlace activo contiene las clases de estilo activo (p.ej. `bg-gray-200`)
    - _Requirements: 5.5_

- [x] 5. Checkpoint — Verificar que todos los tests pasan
  - Ejecutar `php artisan test --filter=LayoutTest` y confirmar que todos los tests pasan
  - Asegurarse de que no hay errores de compilación en Blade
  - Preguntar al usuario si tiene dudas antes de continuar

## Notas

- Las tareas marcadas con `*` son opcionales y pueden omitirse para un MVP más rápido
- Cada tarea referencia los requisitos específicos para trazabilidad
- No se aplica Property-Based Testing (PBT): el renderizado Blade es declarativo y los tests de ejemplo con PHPUnit cubren todos los criterios de aceptación de forma completa
- Los tests de integración de build (`npm run build`) deben ejecutarse manualmente en la terminal del usuario

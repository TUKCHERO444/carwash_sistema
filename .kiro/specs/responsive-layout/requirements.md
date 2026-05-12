# Requirements Document

## Introduction

Esta funcionalidad establece el layout base de la aplicación Laravel. Consiste en instalar Tailwind CSS correctamente mediante npm/Vite (sin CDN), y crear un layout principal con sidebar de navegación que se adapta al dispositivo: en desktop el sidebar aparece a la izquierda, y en móvil se convierte en una barra de navegación inferior. Todas las vistas de la aplicación se renderizarán sobre este patrón de diseño, ocupando el espacio restante del layout.

## Glossary

- **Layout**: Plantilla Blade principal (`app.blade.php`) que envuelve todas las vistas de la aplicación.
- **Sidebar**: Componente de navegación lateral que se muestra a la izquierda en pantallas de escritorio.
- **Bottom_Nav**: Barra de navegación inferior que reemplaza al Sidebar en pantallas móviles.
- **Content_Area**: Zona de contenido principal donde se renderizan las vistas hijas mediante `@yield('content')`.
- **Vite**: Bundler de assets configurado en `vite.config.js` con el plugin `laravel-vite-plugin`.
- **Tailwind**: Framework de utilidades CSS v4, integrado via `@tailwindcss/vite` sin CDN.
- **Blade**: Motor de plantillas de Laravel utilizado para construir las vistas.
- **Breakpoint_LG**: Punto de quiebre de 1024px (`lg:` en Tailwind) que separa el comportamiento móvil del desktop.

## Requirements

### Requirement 1: Instalación de Tailwind CSS via npm/Vite

**User Story:** Como desarrollador, quiero que Tailwind CSS esté integrado mediante npm y Vite, para que los estilos se compilen en el proceso de build sin depender de un CDN externo.

#### Acceptance Criteria

1. THE Layout SHALL cargar los estilos de Tailwind CSS únicamente a través de la directiva `@vite` de Laravel, sin ninguna etiqueta `<link>` o `<script>` apuntando a un CDN.
2. WHEN se ejecuta `npm run build`, THE Vite SHALL compilar el archivo `resources/css/app.css` que contiene `@import 'tailwindcss'` y generar los assets en `public/build/`.
3. THE archivo `resources/css/app.css` SHALL importar Tailwind CSS mediante la directiva `@import 'tailwindcss'` y declarar las fuentes de escaneo de clases con `@source`.
4. THE `vite.config.js` SHALL registrar el plugin `@tailwindcss/vite` junto con `laravel-vite-plugin` para procesar los estilos.

### Requirement 2: Layout principal con Sidebar en desktop

**User Story:** Como usuario de escritorio, quiero ver un sidebar de navegación fijo a la izquierda, para acceder rápidamente a las secciones de la aplicación sin perder el contexto de la página actual.

#### Acceptance Criteria

1. THE Layout SHALL renderizar un elemento `<aside>` con el Sidebar visible en el lado izquierdo de la pantalla cuando el ancho de la ventana es mayor o igual a `Breakpoint_LG` (1024px).
2. WHILE el ancho de la ventana es mayor o igual a `Breakpoint_LG`, THE Sidebar SHALL ocupar un ancho fijo y permanecer visible de forma permanente sin superponerse al Content_Area.
3. THE Layout SHALL estructurar la página con un contenedor `flex` de dirección `row` en desktop, donde el Sidebar y el Content_Area se ubican en columnas adyacentes.
4. THE Sidebar SHALL contener los enlaces de navegación principales de la aplicación con etiquetas `<a>` o componentes Blade equivalentes.
5. WHILE el ancho de la ventana es mayor o igual a `Breakpoint_LG`, THE Bottom_Nav SHALL estar oculto mediante la clase utilitaria `hidden` de Tailwind.

### Requirement 3: Navegación inferior en móvil

**User Story:** Como usuario móvil, quiero una barra de navegación en la parte inferior de la pantalla, para acceder a las secciones principales con el pulgar sin necesidad de desplazarme.

#### Acceptance Criteria

1. WHEN el ancho de la ventana es menor a `Breakpoint_LG`, THE Layout SHALL ocultar el Sidebar lateral y mostrar el Bottom_Nav fijo en la parte inferior de la pantalla.
2. THE Bottom_Nav SHALL estar posicionado de forma fija (`fixed bottom-0`) y ocupar el ancho completo de la pantalla en dispositivos móviles.
3. THE Bottom_Nav SHALL contener los mismos enlaces de navegación que el Sidebar, garantizando paridad funcional entre ambos modos.
4. WHILE el ancho de la ventana es menor a `Breakpoint_LG`, THE Content_Area SHALL incluir un padding inferior suficiente para que el contenido no quede oculto detrás del Bottom_Nav.
5. WHEN el ancho de la ventana es menor a `Breakpoint_LG`, THE Sidebar SHALL estar oculto mediante la clase utilitaria `hidden` de Tailwind.

### Requirement 4: Content Area y sistema de slots Blade

**User Story:** Como desarrollador, quiero que todas las vistas se acoplen automáticamente al espacio restante del layout, para no tener que reescribir la estructura HTML en cada vista.

#### Acceptance Criteria

1. THE Layout SHALL exponer una sección Blade llamada `content` mediante `@yield('content')` dentro del Content_Area.
2. THE Content_Area SHALL ocupar el espacio restante disponible después del Sidebar en desktop, utilizando `flex-1` o `grow` de Tailwind.
3. THE Content_Area SHALL tener `overflow-y-auto` para permitir el desplazamiento vertical independiente del Sidebar.
4. WHEN una vista Blade extiende el Layout con `@extends('layouts.app')` y define `@section('content')`, THE Layout SHALL renderizar dicho contenido dentro del Content_Area sin modificar la estructura del layout.
5. THE Layout SHALL incluir la directiva `@vite(['resources/css/app.css', 'resources/js/app.js'])` en el `<head>` para cargar los assets compilados.

### Requirement 5: Consistencia visual y accesibilidad del layout

**User Story:** Como usuario, quiero que el layout sea visualmente consistente y accesible, para tener una experiencia de uso coherente en todos los dispositivos.

#### Acceptance Criteria

1. THE Sidebar SHALL incluir el atributo `aria-label="Navegación principal"` en el elemento `<nav>` para identificarlo como región de navegación principal.
2. THE Bottom_Nav SHALL incluir el atributo `aria-label="Navegación móvil"` en el elemento `<nav>` para diferenciarlo del Sidebar en lectores de pantalla.
3. THE Layout SHALL definir `<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">` para declarar el idioma del documento.
4. THE Layout SHALL incluir `<meta name="viewport" content="width=device-width, initial-scale=1">` para garantizar el escalado correcto en dispositivos móviles.
5. WHEN un enlace de navegación corresponde a la ruta activa, THE Layout SHALL aplicar un estilo visual diferenciado al enlace activo en ambos, Sidebar y Bottom_Nav.

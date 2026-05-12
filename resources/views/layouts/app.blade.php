<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        // On page load or when changing themes, best to add inline in `head` to avoid FOUC
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        document.addEventListener('DOMContentLoaded', function() {
            var themeToggleDarkIcon = document.getElementById('theme-toggle-dark-icon');
            var themeToggleLightIcon = document.getElementById('theme-toggle-light-icon');
            var themeToggleBtn = document.getElementById('theme-toggle');

            // Change the icons based on current theme
            if (document.documentElement.classList.contains('dark')) {
                themeToggleLightIcon.classList.remove('hidden');
            } else {
                themeToggleDarkIcon.classList.remove('hidden');
            }

            themeToggleBtn.addEventListener('click', function() {
                // toggle icons inside button
                themeToggleDarkIcon.classList.toggle('hidden');
                themeToggleLightIcon.classList.toggle('hidden');

                // if set via local storage previously
                if (localStorage.getItem('theme')) {
                    if (localStorage.getItem('theme') === 'light') {
                        document.documentElement.classList.add('dark');
                        localStorage.setItem('theme', 'dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                        localStorage.setItem('theme', 'light');
                    }
                // if NOT set via local storage previously
                } else {
                    if (document.documentElement.classList.contains('dark')) {
                        document.documentElement.classList.remove('dark');
                        localStorage.setItem('theme', 'light');
                    } else {
                        document.documentElement.classList.add('dark');
                        localStorage.setItem('theme', 'dark');
                    }
                }
            });
        });
    </script>
</head>
<body class="flex h-screen bg-gray-50 dark:bg-background-dark text-gray-900 dark:text-text-primary-dark overflow-hidden transition-colors duration-300">

@php
    $userManagementActive    = request()->routeIs('users.*', 'roles.*', 'trabajadores.*');
    $productManagementActive = request()->routeIs('productos.*', 'categorias.*');
    $categoriasActive        = request()->routeIs('categorias.*');
    $ventasActive            = request()->routeIs('ventas.*');
    $ingresosActive          = request()->routeIs('ingresos.*');
    $cambioAceiteActive      = request()->routeIs('cambio-aceite.*');
    $gestionVentasActive     = $ventasActive || $cambioAceiteActive || $ingresosActive;
    $cajaActive              = request()->routeIs('caja.*');
    $gestionAdministrativaActive = request()->routeIs('vehiculos.*', 'servicios.*', 'clientes.*');
@endphp

    {{-- Sidebar: visible en desktop (≥1024px), oculto en móvil --}}
    <aside class="hidden lg:flex flex-col w-64 bg-gray-900 border-r border-gray-800 shrink-0">
        <div class="flex items-center justify-between h-16 px-6 border-b border-gray-800">
            <span class="text-lg font-semibold text-white">{{ config('app.name', 'Laravel') }}</span>
            <button id="theme-toggle" class="text-gray-400 hover:text-white transition-colors p-2 rounded-lg" aria-label="Cambiar tema">
                <svg id="theme-toggle-dark-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path>
                </svg>
                <svg id="theme-toggle-light-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z"></path>
                </svg>
            </button>
        </div>
        <nav aria-label="Navegación principal" class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
            <a href="{{ route('dashboard') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors
                      {{ request()->routeIs('dashboard') ? 'bg-gray-800 text-white font-semibold' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Dashboard
            </a>

            {{-- Caja --}}
            <a href="{{ route('caja.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors
                      {{ $cajaActive ? 'bg-gray-800 text-white font-semibold' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M9 14h6m2 0a2 2 0 002-2V9a2 2 0 00-2-2h-2m-6 0H7a2 2 0 00-2 2v3a2 2 0 002 2m0 0v2a2 2 0 002 2h6a2 2 0 002-2v-2m-8 0h6"/>
                </svg>
                Caja
            </a>

            {{-- Gestión de Ventas — available to all authenticated users --}}
            <div data-dropdown="gestion-ventas">
                <button data-dropdown-toggle="gestion-ventas"
                        class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors
                               {{ $gestionVentasActive ? 'bg-gray-800 text-white font-semibold' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <span class="flex-1 text-left">Gestión de Ventas</span>
                    <svg data-chevron
                         class="w-4 h-4 transition-transform {{ $gestionVentasActive ? 'rotate-180' : '' }}"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div data-dropdown-menu="gestion-ventas"
                     class="{{ $gestionVentasActive ? '' : 'hidden' }} ml-4 mt-1 space-y-1">
                    <a href="{{ route('ventas.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors
                              {{ $ventasActive ? 'bg-gray-800 text-white font-semibold' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                        Ventas
                    </a>
                    <a href="{{ route('ingresos.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors
                              {{ $ingresosActive ? 'bg-gray-800 text-white font-semibold' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                        Ingresos
                    </a>
                    <a href="{{ route('cambio-aceite.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors
                              {{ $cambioAceiteActive ? 'bg-gray-800 text-white font-semibold' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                        Cambio de Aceite
                    </a>
                </div>
            </div>

            @if(auth()->user()?->hasRole('Administrador'))
            <div data-dropdown="user-management">
                <button data-dropdown-toggle="user-management"
                        class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors
                               {{ $userManagementActive ? 'bg-gray-800 text-white font-semibold' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <span class="flex-1 text-left">Gestión de usuarios</span>
                    <svg data-chevron
                         class="w-4 h-4 transition-transform {{ $userManagementActive ? 'rotate-180' : '' }}"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div data-dropdown-menu="user-management"
                     class="{{ $userManagementActive ? '' : 'hidden' }} ml-4 mt-1 space-y-1">
                    <a href="{{ route('users.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors
                              {{ request()->routeIs('users.*') ? 'bg-gray-800 text-white font-semibold' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                        Usuarios
                    </a>
                    <a href="{{ route('roles.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors
                              {{ request()->routeIs('roles.*') ? 'bg-gray-800 text-white font-semibold' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                        Roles
                    </a>
                     <a href="{{ route('trabajadores.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors
                              {{ request()->routeIs('trabajadores.*') ? 'bg-gray-800 text-white font-semibold' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                        Trabajadores
                    </a>
                </div>
            </div>

            <div data-dropdown="product-management">
                <button data-dropdown-toggle="product-management"
                        class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors
                               {{ $productManagementActive ? 'bg-gray-800 text-white font-semibold' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    <span class="flex-1 text-left">Gestión de productos</span>
                    <svg data-chevron
                         class="w-4 h-4 transition-transform {{ $productManagementActive ? 'rotate-180' : '' }}"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div data-dropdown-menu="product-management"
                     class="{{ $productManagementActive ? '' : 'hidden' }} ml-4 mt-1 space-y-1">
                    <a href="{{ route('productos.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors
                              {{ request()->routeIs('productos.*') ? 'bg-gray-800 text-white font-semibold' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                        Productos
                    </a>
                    <a href="{{ route('categorias.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors
                              {{ $categoriasActive ? 'bg-gray-800 text-white font-semibold' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                        Categorías
                    </a>
                </div>
            </div>

            <div data-dropdown="gestion-administrativa">
                <button data-dropdown-toggle="gestion-administrativa"
                        class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors
                               {{ $gestionAdministrativaActive ? 'bg-gray-800 text-white font-semibold' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2 2h8l2-2z"/>
                    </svg>
                    <span class="flex-1 text-left">Gestión Administrativa</span>
                    <svg data-chevron
                         class="w-4 h-4 transition-transform {{ $gestionAdministrativaActive ? 'rotate-180' : '' }}"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div data-dropdown-menu="gestion-administrativa"
                     class="{{ $gestionAdministrativaActive ? '' : 'hidden' }} ml-4 mt-1 space-y-1">
                    <a href="{{ route('vehiculos.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors
                              {{ request()->routeIs('vehiculos.*') ? 'bg-gray-800 text-white font-semibold' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                        Vehículos
                    </a>
                    <a href="{{ route('servicios.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors
                              {{ request()->routeIs('servicios.*') ? 'bg-gray-800 text-white font-semibold' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                        Servicios
                    </a>
                    <a href="{{ route('clientes.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors
                              {{ request()->routeIs('clientes.*') ? 'bg-gray-800 text-white font-semibold' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                        Clientes
                    </a>
                </div>
            </div>
            @endif
        </nav>

        {{-- Logout button at the bottom of the sidebar --}}
        <div class="px-4 py-4 border-t border-gray-800">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-400 hover:bg-red-900/20 hover:text-red-400 transition-colors">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Cerrar sesión
                </button>
            </form>
        </div>
    </aside>

    {{-- Content Area: ocupa el espacio restante --}}
    <main class="flex-1 overflow-y-auto pb-[77px] lg:pb-0">
        @yield('content')
    </main>

    {{-- Bottom Nav: visible en móvil (<1024px), oculto en desktop --}}
    <nav aria-label="Navegación móvil"
         class="lg:hidden fixed bottom-0 left-0 right-0 bg-gray-900 border-t border-gray-800 flex justify-around items-center h-[77px] z-50">
        <a href="{{ route('dashboard') }}"
           class="flex-1 flex flex-col items-center gap-1 py-2 text-[10px] font-medium transition-colors
                  {{ request()->routeIs('dashboard') ? 'text-blue-400' : 'text-gray-400 hover:text-white' }}">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            Dashboard
        </a>

        {{-- Caja --}}
        <a href="{{ route('caja.index') }}"
           class="flex-1 flex flex-col items-center gap-1 py-2 text-[10px] font-medium transition-colors
                  {{ $cajaActive ? 'text-blue-400' : 'text-gray-400 hover:text-white' }}">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M9 14h6m2 0a2 2 0 002-2V9a2 2 0 00-2-2h-2m-6 0H7a2 2 0 00-2 2v3a2 2 0 002 2m0 0v2a2 2 0 002 2h6a2 2 0 002-2v-2m-8 0h6"/>
            </svg>
            Caja
        </a>

        {{-- Gestión de Ventas — available to all authenticated users --}}
        <div data-dropdown="gestion-ventas-mobile" class="flex-1 relative">
            <button data-dropdown-toggle="gestion-ventas-mobile"
                    class="w-full flex flex-col items-center gap-1 py-2 text-[10px] font-medium transition-colors
                           {{ $gestionVentasActive ? 'text-blue-400' : 'text-gray-400 hover:text-white' }}">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                Ventas
                <svg data-chevron
                     class="w-3 h-3 transition-transform {{ $gestionVentasActive ? 'rotate-180' : '' }}"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div data-dropdown-menu="gestion-ventas-mobile"
                 {{ $gestionVentasActive ? 'data-persistent' : '' }}
                 class="absolute bottom-[77px] left-1/2 -translate-x-1/2 bg-gray-800 border border-gray-700 rounded-lg shadow-2xl min-w-max
                        {{ $gestionVentasActive ? '' : 'hidden' }}">
                <a href="{{ route('ventas.index') }}"
                   class="flex items-center gap-2 px-4 py-2 text-sm font-medium transition-colors
                          {{ $ventasActive ? 'text-blue-400 bg-gray-700' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    Ventas
                </a>
                <a href="{{ route('ingresos.index') }}"
                   class="flex items-center gap-2 px-4 py-2 text-sm font-medium transition-colors
                          {{ $ingresosActive ? 'text-blue-400 bg-gray-700' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    Ingresos
                </a>
                <a href="{{ route('cambio-aceite.index') }}"
                   class="flex items-center gap-2 px-4 py-2 text-sm font-medium transition-colors
                          {{ $cambioAceiteActive ? 'text-blue-400 bg-gray-700' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    Cambio de Aceite
                </a>
            </div>
        </div>

        @if(auth()->user()?->hasRole('Administrador'))
        <div data-dropdown="user-management-mobile" class="flex-1 relative">
            <button data-dropdown-toggle="user-management-mobile"
                    class="w-full flex flex-col items-center gap-1 py-2 text-[10px] font-medium transition-colors
                           {{ $userManagementActive ? 'text-blue-400' : 'text-gray-400 hover:text-white' }}">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                Gestión de usuarios
                <svg data-chevron
                     class="w-3 h-3 transition-transform {{ $userManagementActive ? 'rotate-180' : '' }}"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div data-dropdown-menu="user-management-mobile"
                 {{ $userManagementActive ? 'data-persistent' : '' }}
                 class="absolute bottom-[77px] left-1/2 -translate-x-1/2 bg-gray-800 border border-gray-700 rounded-lg shadow-2xl min-w-max
                        {{ $userManagementActive ? '' : 'hidden' }}">
                <a href="{{ route('users.index') }}"
                   class="flex items-center gap-2 px-4 py-2 text-sm font-medium transition-colors
                          {{ request()->routeIs('users.*') ? 'text-blue-400 bg-gray-700' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    Usuarios
                </a>
                <a href="{{ route('roles.index') }}"
                   class="flex items-center gap-2 px-4 py-2 text-sm font-medium transition-colors
                          {{ request()->routeIs('roles.*') ? 'text-blue-400 bg-gray-700' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    Roles
                </a>
                <a href="{{ route('trabajadores.index') }}"
                   class="flex items-center gap-2 px-4 py-2 text-sm font-medium transition-colors
                          {{ request()->routeIs('trabajadores.*') ? 'text-blue-400 bg-gray-700' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    Trabajadores
                </a>
            </div>
        </div>

        <div data-dropdown="product-management-mobile" class="flex-1 relative">
            <button data-dropdown-toggle="product-management-mobile"
                    class="w-full flex flex-col items-center gap-1 py-2 text-[10px] font-medium transition-colors
                           {{ $productManagementActive ? 'text-blue-400' : 'text-gray-400 hover:text-white' }}">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
                Gestión de productos
                <svg data-chevron
                     class="w-3 h-3 transition-transform {{ $productManagementActive ? 'rotate-180' : '' }}"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div data-dropdown-menu="product-management-mobile"
                 {{ $productManagementActive ? 'data-persistent' : '' }}
                 class="absolute bottom-[77px] left-1/2 -translate-x-1/2 bg-gray-800 border border-gray-700 rounded-lg shadow-2xl min-w-max
                        {{ $productManagementActive ? '' : 'hidden' }}">
                <a href="{{ route('productos.index') }}"
                   class="flex items-center gap-2 px-4 py-2 text-sm font-medium transition-colors
                          {{ request()->routeIs('productos.*') ? 'text-blue-400 bg-gray-700' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    Productos
                </a>
                <a href="{{ route('categorias.index') }}"
                   class="flex items-center gap-2 px-4 py-2 text-sm font-medium transition-colors
                          {{ $categoriasActive ? 'text-blue-400 bg-gray-700' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    Categorías
                </a>
            </div>
        </div>

        <div data-dropdown="gestion-administrativa-mobile" class="flex-1 relative">
            <button data-dropdown-toggle="gestion-administrativa-mobile"
                    class="w-full flex flex-col items-center gap-1 py-2 text-[10px] font-medium transition-colors
                           {{ $gestionAdministrativaActive ? 'text-blue-400' : 'text-gray-400 hover:text-white' }}">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2 2h8l2-2z"/>
                </svg>
                Gestión Adm.
                <svg data-chevron
                     class="w-3 h-3 transition-transform {{ $gestionAdministrativaActive ? 'rotate-180' : '' }}"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div data-dropdown-menu="gestion-administrativa-mobile"
                 {{ $gestionAdministrativaActive ? 'data-persistent' : '' }}
                 class="absolute bottom-[77px] left-1/2 -translate-x-1/2 bg-gray-800 border border-gray-700 rounded-lg shadow-2xl min-w-max
                        {{ $gestionAdministrativaActive ? '' : 'hidden' }}">
                <a href="{{ route('vehiculos.index') }}"
                   class="flex items-center gap-2 px-4 py-2 text-sm font-medium transition-colors
                          {{ request()->routeIs('vehiculos.*') ? 'text-blue-400 bg-gray-700' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    Vehículos
                </a>
                <a href="{{ route('servicios.index') }}"
                   class="flex items-center gap-2 px-4 py-2 text-sm font-medium transition-colors
                          {{ request()->routeIs('servicios.*') ? 'text-blue-400 bg-gray-700' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    Servicios
                </a>
                <a href="{{ route('clientes.index') }}"
                   class="flex items-center gap-2 px-4 py-2 text-sm font-medium transition-colors
                          {{ request()->routeIs('clientes.*') ? 'text-blue-400 bg-gray-700' : 'text-gray-300 hover:bg-gray-700 hover:text-white' }}">
                    Clientes
                </a>
            </div>
        </div>
        @endif
    </nav>

</body>
</html>

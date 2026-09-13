@php
    // Futuro: los datos del negocio (teléfono, horario, redes) llegarán desde la BD/panel.
    // Aquí se dejan valores por defecto como espacio preparado.
    $telefono = config('carwash.telefono', '955 555 555');
    $horario  = config('carwash.horario', 'Lun – Sáb · 8:00 a.m. – 6:00 p.m.');
@endphp

{{-- Barra de anuncios --}}
<div class="cw-announce text-xs sm:text-sm">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 py-2 flex items-center justify-center gap-2 text-center">
        <svg aria-hidden="true" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span>{{ $horario }}</span>
        <span class="hidden sm:inline opacity-40">|</span>
        <span class="hidden sm:inline">Atención al cliente: {{ $telefono }}</span>
    </div>
</div>

{{-- Cabecera: fila superior (logo + búsqueda + carrito) y navegación debajo --}}
<header class="sticky top-0 z-50 bg-navy-900">
    <div class="mx-auto max-w-7xl px-4 sm:px-6">

        {{-- Fila superior --}}
        <div class="flex items-center justify-between gap-4 sm:gap-8 h-32 sm:h-40">

            {{-- Botón menú móvil --}}
            <button type="button" data-mobile-menu-toggle aria-expanded="false" aria-controls="menu-movil" aria-label="Abrir menú de navegación"
                    class="lg:hidden inline-flex items-center justify-center rounded-xl p-3.5 -ml-3.5 text-white-cold hover:text-brand-cyan-400 hover:bg-navy-800 transition-colors">
                <svg data-icon="open" aria-hidden="true" class="h-9 w-9" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
                <svg data-icon="close" aria-hidden="true" class="hidden h-9 w-9" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>

            {{-- Logo --}}
            <a href="{{ route('inicio') }}" class="flex items-center gap-3 sm:gap-4 shrink-0" aria-label="Carwash El Chinito — Inicio">
                <span class="flex h-14 w-14 sm:h-16 sm:w-16 items-center justify-center rounded-2xl bg-brand-cyan-500 text-navy-900 shadow-sm">
                    <svg aria-hidden="true" class="h-9 w-9 sm:h-10 sm:w-10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </span>
                <span class="leading-tight">
                    <span class="block text-xl sm:text-3xl font-bold text-white-cold tracking-wide">Carwash El Chinito</span>
                    <span class="hidden sm:block text-sm text-steel-400 tracking-widest uppercase">Cuidado automotriz</span>
                </span>
            </a>

            {{-- Barra de búsqueda (escritorio) --}}
            {{-- Futuro: búsqueda real de productos/servicios. --}}
            <form class="hidden md:flex flex-1 justify-center" action="#" method="get" role="search">
                <div class="flex w-full max-w-2xl items-center overflow-hidden rounded-xl border border-navy-600 bg-navy-800 focus-within:border-brand-cyan-500 focus-within:ring-1 focus-within:ring-brand-cyan-500 transition-colors">
                    <input type="search" name="q" autocomplete="off" placeholder="Buscar productos y servicios..." aria-label="Buscar"
                           class="w-full bg-transparent px-5 py-4 text-lg text-white-cold placeholder:text-steel-400 focus:outline-none">
                    <button type="submit" class="flex items-center justify-center bg-brand-cyan-500 px-5 py-4 text-navy-900 hover:bg-brand-cyan-400 transition-colors" aria-label="Buscar">
                        <svg aria-hidden="true" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 21 21">
                            <g fill="none" fill-rule="evenodd">
                                <path d="M19 19l-5-5" stroke-linecap="square"/>
                                <circle cx="8.5" cy="8.5" r="7.5"/>
                            </g>
                        </svg>
                    </button>
                </div>
            </form>

            {{-- Acciones: ingresar + carrito --}}
            <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                <a href="{{ route('login') }}" class="hidden xl:inline-flex items-center gap-2.5 rounded-xl px-5 py-3.5 text-lg font-semibold text-white-cold hover:text-brand-cyan-400 transition-colors">
                    <svg aria-hidden="true" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    INGRESAR
                </a>
                {{-- Futuro: ruta del carrito de compra (aún sin implementar). --}}
                <a href="#" aria-label="Carrito de compra (0 artículos)"
                   class="relative inline-flex items-center gap-2.5 rounded-xl p-3 text-white-cold hover:text-brand-cyan-400 hover:bg-navy-800 transition-colors">
                    <svg aria-hidden="true" class="h-9 w-9" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 27 24">
                        <g transform="translate(0 1)" fill="none" fill-rule="evenodd">
                            <circle stroke-linecap="square" cx="11" cy="20" r="2"/>
                            <circle stroke-linecap="square" cx="22" cy="20" r="2"/>
                            <path d="M7.31 5h18.27l-1.44 10H9.78L6.22 0H0" stroke-linecap="square"/>
                        </g>
                    </svg>
                    <span class="absolute -top-1 -right-1 flex h-6 min-w-6 items-center justify-center rounded-full bg-brand-cyan-500 px-1.5 text-xs font-bold text-navy-900">0</span>
                    <span class="hidden lg:inline text-lg font-semibold">CARRITO</span>
                </a>
            </div>
        </div>

        {{-- Barra de búsqueda (móvil) --}}
        <form class="md:hidden pb-3" action="#" method="get" role="search">
            <div class="flex items-center overflow-hidden rounded-xl border border-navy-600 bg-navy-800 focus-within:border-brand-cyan-500 focus-within:ring-1 focus-within:ring-brand-cyan-500 transition-colors">
                <input type="search" name="q" autocomplete="off" placeholder="Buscar productos y servicios..." aria-label="Buscar"
                       class="w-full bg-transparent px-5 py-4 text-lg text-white-cold placeholder:text-steel-400 focus:outline-none">
                <button type="submit" class="flex items-center justify-center bg-brand-cyan-500 px-5 py-4 text-navy-900 hover:bg-brand-cyan-400 transition-colors" aria-label="Buscar">
                    <svg aria-hidden="true" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 21 21">
                        <g fill="none" fill-rule="evenodd">
                            <path d="M19 19l-5-5" stroke-linecap="square"/>
                            <circle cx="8.5" cy="8.5" r="7.5"/>
                        </g>
                    </svg>
                </button>
            </div>
        </form>
    </div>

    {{-- Navegación principal DEBAJO del header (solo escritorio) --}}
    {{-- Futuro: menú dinámico desde el panel y submenús desplegables como el tema de referencia. --}}
    <nav class="hidden lg:block bg-white border-t border-steel-200" aria-label="Navegación principal">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            <ul class="flex items-center gap-8 h-12 sm:h-14">
                <li>
                    <a href="{{ route('inicio') }}"
                       class="relative inline-flex items-center h-full text-sm font-medium text-navy-900 hover:text-brand-blue-700 transition-colors {{ request()->routeIs('inicio') ? 'after:absolute after:left-0 after:right-0 after:bottom-0 after:h-0.5 after:bg-brand-blue-700 font-semibold' : '' }}">
                        Inicio
                    </a>
                </li>
                {{-- Rutas futuras: reemplazar '#' por las rutas públicas de cada sección. --}}
                <li><a href="{{ route('publica.servicios') }}" class="inline-flex items-center h-full text-sm font-medium text-navy-900 hover:text-brand-blue-700 transition-colors {{ request()->routeIs('publica.servicios') ? 'text-brand-blue-700 font-semibold' : '' }}">Servicios</a></li>
                {{-- "Productos": click navega a la vista pública; mantener presionado
                     (o tocar el chevron) despliega el submenú de categorías activas
                     del panel. La interactividad la maneja nav-productos.js. --}}
                <li data-productos-menu class="relative">
                    <a href="{{ route('publica.productos') }}"
                       data-productos-trigger
                       aria-haspopup="true" aria-expanded="false" aria-controls="productos-submenu"
                       class="inline-flex items-center gap-1.5 h-full text-sm font-medium text-navy-900 hover:text-brand-blue-700 transition-colors {{ request()->routeIs('publica.productos*') ? 'text-brand-blue-700 font-semibold' : '' }}">
                        Productos
                        <svg data-productos-chevron aria-hidden="true" class="h-3 w-3 transition-transform duration-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 12 8">
                            <path stroke-linecap="round" d="M10 2L6 6 2 2"/>
                        </svg>
                    </a>
                    <div id="productos-submenu" data-productos-panel hidden role="menu"
                         class="absolute left-0 top-full z-50 w-72 rounded-b-xl border border-t-0 border-steel-200 bg-white shadow-xl shadow-navy-900/10">
                        <ul class="py-2">
                            <li><a href="{{ route('publica.productos') }}" class="flex items-center justify-between gap-3 px-5 py-2.5 text-sm font-medium text-navy-900 hover:bg-white-cold hover:text-brand-blue-700 transition-colors">Todos los productos</a></li>
                            @forelse ($categoriasMenu as $cat)
                                <li>
                                    <a href="{{ route('publica.productos.categoria', $cat->slug) }}"
                                       class="flex items-center justify-between gap-3 px-5 py-2.5 text-sm font-medium text-navy-900 hover:bg-white-cold hover:text-brand-blue-700 transition-colors {{ request()->routeIs('publica.productos.categoria') && request()->route('categoria')?->slug === $cat->slug ? 'text-brand-blue-700 font-semibold' : '' }}">
                                        <span>{{ $cat->nombre }}</span>
                                        <span class="text-xs text-steel-400">{{ $cat->productos_count }}</span>
                                    </a>
                                </li>
                            @empty
                                <li class="px-5 py-2.5 text-sm text-steel-400">Próximamente más categorías.</li>
                            @endforelse
                        </ul>
                    </div>
                </li>
                <li><a href="{{ route('publica.marcas') }}" class="inline-flex items-center h-full text-sm font-medium text-navy-900 hover:text-brand-blue-700 transition-colors {{ request()->routeIs('publica.marcas') ? 'text-brand-blue-700 font-semibold' : '' }}">Marcas</a></li>
                <li><a href="{{ route('publica.quienes-somos') }}" class="inline-flex items-center h-full text-sm font-medium text-navy-900 hover:text-brand-blue-700 transition-colors {{ request()->routeIs('publica.quienes-somos') ? 'text-brand-blue-700 font-semibold' : '' }}">¿Quiénes somos?</a></li>
                <li><a href="{{ route('publica.cotiza') }}" class="inline-flex items-center h-full text-sm font-medium text-navy-900 hover:text-brand-blue-700 transition-colors {{ request()->routeIs('publica.cotiza') ? 'text-brand-blue-700 font-semibold' : '' }}">Contacto</a></li>
            </ul>
        </div>
    </nav>
</header>

{{-- Menú móvil (desplegable bajo la cabecera) --}}
<nav data-mobile-menu id="menu-movil" class="cw-mobile-menu lg:hidden bg-navy-900 border-t border-navy-700" aria-label="Navegación móvil" hidden>
    <ul class="mx-auto max-w-7xl px-4 sm:px-6 py-4 space-y-1">
        <li><a href="{{ route('inicio') }}" class="block rounded-lg px-3 py-2.5 text-sm font-medium text-white-cold hover:bg-navy-800 hover:text-brand-cyan-400 transition-colors">Inicio</a></li>
        <li><a href="{{ route('publica.servicios') }}" class="block rounded-lg px-3 py-2.5 text-sm font-medium text-navy-100 hover:bg-navy-800 hover:text-brand-cyan-400 transition-colors">Servicios</a></li>
        <li>
            <a href="{{ route('publica.productos') }}" class="block rounded-lg px-3 py-2.5 text-sm font-medium text-navy-100 hover:bg-navy-800 hover:text-brand-cyan-400 transition-colors">Productos</a>
            <ul class="ml-3 pl-3 mt-0.5 space-y-0.5 border-l border-navy-700">
                <li><a href="{{ route('publica.productos') }}" class="block rounded-lg px-3 py-2 text-sm font-medium text-steel-400 hover:bg-navy-800 hover:text-brand-cyan-400 transition-colors">Todos los productos</a></li>
                @forelse ($categoriasMenu as $cat)
                    <li>
                        <a href="{{ route('publica.productos.categoria', $cat->slug) }}" class="block rounded-lg px-3 py-2 text-sm font-medium text-steel-400 hover:bg-navy-800 hover:text-brand-cyan-400 transition-colors">{{ $cat->nombre }}</a>
                    </li>
                @empty
                    <li class="px-3 py-2 text-sm text-steel-600">Próximamente más categorías.</li>
                @endforelse
            </ul>
        </li>
        <li><a href="{{ route('publica.marcas') }}" class="block rounded-lg px-3 py-2.5 text-sm font-medium text-navy-100 hover:bg-navy-800 hover:text-brand-cyan-400 transition-colors">Marcas</a></li>
        <li><a href="{{ route('publica.quienes-somos') }}" class="block rounded-lg px-3 py-2.5 text-sm font-medium text-navy-100 hover:bg-navy-800 hover:text-brand-cyan-400 transition-colors">¿Quiénes somos?</a></li>
        <li><a href="{{ route('publica.cotiza') }}" class="block rounded-lg px-3 py-2.5 text-sm font-medium text-navy-100 hover:bg-navy-800 hover:text-brand-cyan-400 transition-colors">Contacto</a></li>
        <li class="pt-1">
            <a href="{{ route('login') }}" class="flex items-center justify-center rounded-lg border border-navy-600 px-4 py-2.5 text-sm font-semibold uppercase text-white-cold hover:bg-navy-800 transition-colors">Ingresar al sistema</a>
        </li>
    </ul>
</nav>
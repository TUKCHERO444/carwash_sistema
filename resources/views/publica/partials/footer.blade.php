<footer class="bg-navy-900 text-white-cold" role="contentinfo">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 pt-14 pb-8">

        {{-- Bloques del pie --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-10">

            {{-- Marca --}}
            <div>
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-cyan-500 text-navy-900">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </span>
                    <span class="text-lg font-bold tracking-wide">{{ config('carwash.nombre') }}</span>
                </div>
                <p class="mt-4 text-sm leading-relaxed text-navy-100">
                    Especialistas en el cuidado y estética automotriz: lavado especializado,
                    detailing, cambio de aceite y productos de primeras marcas.
                </p>
                <p class="mt-4 text-sm">
                    <span class="text-steel-400">Escríbenos:</span>
                    <a href="tel:{{ preg_replace('/\s+/', '', config('carwash.telefono')) }}" class="text-white-cold hover:text-brand-cyan-400 transition-colors font-medium">
                        {{ config('carwash.telefono') }}
                    </a>
                    <span class="block mt-1">
                        <a href="mailto:{{ config('carwash.email') }}" class="text-navy-100 hover:text-brand-cyan-400 transition-colors text-sm">
                            {{ config('carwash.email') }}
                        </a>
                    </span>
                </p>
            </div>

            {{-- Enlaces --}}
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-widest text-white-cold">Navegación</h3>
                <ul class="mt-4 space-y-2.5 text-sm">
                    {{-- Rutas futuras del sitio público — reemplazar '#' por route(...) --}}
                    <li><a href="{{ route('inicio') }}" class="text-navy-100 hover:text-brand-cyan-400 transition-colors">Inicio</a></li>
                    <li><a href="{{ route('publica.servicios') }}" class="text-navy-100 hover:text-brand-cyan-400 transition-colors">Servicios</a></li>
                    <li><a href="{{ route('publica.productos') }}" class="text-navy-100 hover:text-brand-cyan-400 transition-colors">Productos</a></li>
                    <li><a href="{{ route('publica.marcas') }}" class="text-navy-100 hover:text-brand-cyan-400 transition-colors">Marcas</a></li>
                    <li><a href="{{ route('publica.quienes-somos') }}" class="text-navy-100 hover:text-brand-cyan-400 transition-colors">¿Quiénes somos?</a></li>
                    <li><a href="{{ route('publica.cotiza') }}" class="text-navy-100 hover:text-brand-cyan-400 transition-colors">Cotiza con nosotros</a></li>
                </ul>
            </div>

            {{-- Legal --}}
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-widest text-white-cold">Legal</h3>
                <ul class="mt-4 space-y-2.5 text-sm">
                    <li><a href="{{ config('carwash.enlaces.terminos') }}" class="text-navy-100 hover:text-brand-cyan-400 transition-colors">Términos y condiciones</a></li>
                    <li><a href="{{ config('carwash.enlaces.politica-cookies') }}" class="text-navy-100 hover:text-brand-cyan-400 transition-colors">Política de cookies</a></li>
                    <li><a href="{{ config('carwash.enlaces.libro-reclamaciones') }}" class="text-navy-100 hover:text-brand-cyan-400 transition-colors">Libro de reclamaciones</a></li>
                    <li><a href="{{ route('login') }}" class="text-navy-100 hover:text-brand-cyan-400 transition-colors">Acceso al sistema</a></li>
                </ul>
            </div>

            {{-- Contacto / newsletter --}}
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-widest text-white-cold">Boletín</h3>
                <p class="mt-4 text-sm text-navy-100">Recibe promociones y novedades sobre nuestros servicios.</p>
                {{-- Futuro: suscripción real al boletín (POST a /suscribirse). --}}
                <form action="#" method="post" class="mt-4 space-y-3">
                    @csrf
                    <label for="footer-email" class="sr-only">Tu email</label>
                    <input id="footer-email" type="email" name="email" required placeholder="Tu email"
                           class="w-full rounded-lg border border-navy-600 bg-navy-800 px-4 py-2.5 text-sm text-white-cold placeholder:text-steel-400 focus:border-brand-cyan-500 focus:outline-none focus:ring-1 focus:ring-brand-cyan-500">
                    <button type="submit" class="w-full rounded-lg bg-brand-cyan-500 px-4 py-2.5 text-sm font-semibold uppercase text-navy-900 hover:bg-brand-cyan-400 transition-colors">
                        Suscribirse
                    </button>
                </form>
            </div>
        </div>

        {{-- Línea inferior --}}
        <div class="mt-12 pt-6 border-t border-navy-700 flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-steel-400">
            <p>&copy; {{ now()->format('Y') }} {{ config('carwash.nombre') }}. Todos los derechos reservados.</p>

            {{-- Redes sociales --}}
            <ul class="flex items-center gap-4">
                @foreach (config('carwash.redes') as $nombre => $url)
                    <li>
                        <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="Síguenos en {{ ucfirst($nombre) }}"
                           class="flex h-9 w-9 items-center justify-center rounded-lg border border-navy-700 text-navy-100 hover:text-brand-cyan-400 hover:border-brand-cyan-500 transition-colors">
                            @if ($nombre === 'facebook')
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987H7.898v-2.891h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.891h-2.33v6.988C18.343 21.128 22 16.991 22 12z"/></svg>
                            @elseif ($nombre === 'instagram')
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zm0 10.162a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                            @elseif ($nombre === 'youtube')
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                            @else
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 15.5v-3.5H7.5L8.5 12H11V9.5c0-1.5.5-3 3-3H16v3h-1.5c-.5 0-1 .5-1 1V12H16l-.5 2.5H13.5V17.5H11z"/></svg>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</footer>
@extends('layouts.publica')

@section('titulo', 'Cotiza con nosotros — Carwash El Chinito')

@section('descripcion', 'Cuéntanos qué necesita tu auto y recibe una cotización rápida de Carwash El Chinito. Escríbenos por WhatsApp, email o desde este formulario.')

@section('content')

    {{-- ============================================================
        1. CABECERA DE PÁGINA (breadcrumb + título + intro)
        El tema de referencia centra el header de página (page__header
        --centered) con el título y una descripción centrada.
    ============================================================ --}}
    <section class="bg-navy-900">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 py-12 sm:py-16 text-center">
            <nav aria-label="Ruta de navegación" class="text-xs sm:text-sm text-steel-400">
                <a href="{{ route('inicio') }}" class="hover:text-brand-cyan-400 transition-colors">Inicio</a>
                <span class="mx-2" aria-hidden="true">/</span>
                <span class="text-white-cold" aria-current="page">Cotiza con nosotros</span>
            </nav>
            <h1 class="mt-3 text-3xl sm:text-4xl lg:text-5xl font-semibold tracking-tight text-white-cold leading-tight">
                Cotiza con nosotros
            </h1>
            <p class="mx-auto mt-4 max-w-3xl text-base sm:text-lg text-navy-100 leading-relaxed">
                En Carwash El Chinito entendemos que cada vehículo tiene su propia historia y que cada
                cliente tiene sus propios desafíos. Por eso estamos comprometidos a ofrecerte la mejor
                atención posible: si tienes alguna necesidad específica para tu auto, no dudes en escribirnos.
            </p>
        </div>
    </section>

    {{-- ============================================================
        2. FORMULARIO DE CONTACTO
        Campos del tema de referencia: nombre, email, celular y mensaje.
        FUTURO: envío real del mensaje (guardar en BD / email / WhatsApp).
    ============================================================ --}}
    <section class="bg-white-cold py-16 sm:py-20">
        <div class="mx-auto max-w-3xl px-4 sm:px-6">
            <div class="rounded-2xl border border-steel-200 bg-white p-6 sm:p-10 shadow-sm">
                <h2 class="text-2xl sm:text-3xl font-semibold text-navy-900">Cuéntanos sobre tu auto</h2>
                <p class="mt-2 text-base leading-relaxed text-steel-700">
                    Completa el formulario y te responderemos a la brevedad con una cotización sin compromiso.
                </p>

                {{-- FUTURO: action="{{ route('publica.cotiza.store') }}" con validación. --}}
                <form action="#" method="post" class="mt-8 space-y-5">
                    @csrf

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label for="contact-form-name" class="label-main mb-1.5">Tu nombre</label>
                            <input id="contact-form-name" type="text" name="nombre" required autocomplete="name" maxlength="100"
                                   placeholder="Ej. Juan Pérez"
                                   class="w-full rounded-lg border px-4 py-3 text-base focus:outline-none focus:ring-2 focus:ring-brand-blue-700 transition-colors input-main">
                        </div>
                        <div>
                            <label for="contact-form-email" class="label-main mb-1.5">Tu email</label>
                            <input id="contact-form-email" type="email" name="email" required autocomplete="email" maxlength="120"
                                   placeholder="Ej. juan@correo.com"
                                   class="w-full rounded-lg border px-4 py-3 text-base focus:outline-none focus:ring-2 focus:ring-brand-blue-700 transition-colors input-main">
                        </div>
                    </div>

                    <div>
                        <label for="contact-form-celular" class="label-main mb-1.5">Celular</label>
                        <input id="contact-form-celular" type="tel" name="celular" required autocomplete="tel" maxlength="20"
                               placeholder="Ej. 955 555 555"
                               class="w-full rounded-lg border px-4 py-3 text-base focus:outline-none focus:ring-2 focus:ring-brand-blue-700 transition-colors input-main">
                    </div>

                    <div>
                        <label for="contact-form-message" class="label-main mb-1.5">Tu mensaje</label>
                        <textarea id="contact-form-message" name="mensaje" rows="8" required maxlength="2000"
                                  placeholder="Cuéntanos qué servicio buscas, modelo de vehículo, etc."
                                  class="w-full rounded-lg border px-4 py-3 text-base focus:outline-none focus:ring-2 focus:ring-brand-blue-700 transition-colors input-main"></textarea>
                    </div>

                    <div class="text-center">
                        <button type="submit"
                                class="inline-flex items-center justify-center rounded-xl bg-brand-cyan-500 px-10 min-h-14 py-3.5 text-base font-semibold uppercase tracking-wide text-navy-900 hover:bg-brand-cyan-400 transition-colors">
                            Enviar mensaje
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    {{-- ============================================================
        3. SELLOS DE CONFIANZA
    ============================================================ --}}
    @include('publica.partials.sellos-confianza')

@endsection
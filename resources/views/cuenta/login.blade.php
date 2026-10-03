@extends('layouts.publica')

@section('titulo', 'Iniciar sesión — Carwash El Chinito')

@section('descripcion', 'Inicia sesión en tu panel de cliente de Carwash El Chinito.')

@section('content')
    <div class="mx-auto max-w-md px-4 py-10">
        <div class="bg-surface border-main rounded-2xl border p-8 shadow-sm">
            <h1 class="text-primary text-center text-2xl font-semibold">Mi cuenta</h1>
            <p class="text-secondary mt-2 text-center text-sm">Inicia sesión para ver tu panel de cliente.</p>

            @if (session('success'))
                <p role="status" class="mt-4 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ session('success') }}</p>
            @endif

            @if (session('info'))
                <p role="status" class="mt-4 rounded-lg border border-sky-300 bg-sky-50 px-3 py-2 text-sm text-sky-700">{{ session('info') }}</p>
            @endif

            <form action="{{ route('cuenta.login') }}" method="POST" novalidate class="mt-6">
                @csrf

                {{-- Campo: Correo electrónico --}}
                <div class="mb-4">
                    <label for="email" class="label-main mb-1 block text-sm font-medium">
                        Correo electrónico
                    </label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        autocomplete="email"
                        @error('email') aria-describedby="email-error" aria-invalid="true" @enderror
                        class="input-main w-full rounded-lg border px-3 py-2 text-sm focus:ring-2 focus:ring-sky-500 focus:outline-none
                            {{ $errors->has('email') ? 'border-red-500' : '' }}"
                    >
                    @error('email')
                        <p id="email-error" role="alert" class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Campo: Contraseña --}}
                <div class="mb-6">
                    <label for="password" class="label-main mb-1 block text-sm font-medium">
                        Contraseña
                    </label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        autocomplete="current-password"
                        @error('password') aria-describedby="password-error" aria-invalid="true" @enderror
                        class="input-main w-full rounded-lg border px-3 py-2 text-sm focus:ring-2 focus:ring-sky-500 focus:outline-none
                            {{ $errors->has('password') ? 'border-red-500' : '' }}"
                    >
                    @error('password')
                        <p id="password-error" role="alert" class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button
                    type="submit"
                    class="w-full rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white transition-colors duration-200 hover:bg-sky-700"
                >
                    Iniciar sesión
                </button>
            </form>

            <div class="text-secondary mt-4 space-y-1 text-center text-sm">
                <p>¿No tienes cuenta? <a href="{{ route('cuenta.registro') }}" class="font-medium text-sky-600 hover:underline">Regístrate</a></p>
                <p><a href="{{ route('cuenta.recuperacion.form') }}" class="font-medium text-sky-600 hover:underline">Olvidé mi contraseña</a></p>
                <p><a href="{{ route('cuenta.verificacion.form') }}" class="font-medium text-sky-600 hover:underline">Reenviar verificación</a></p>
            </div>

            @if (config('services.google.client_id'))
                <div class="mt-6">
                    <div class="text-secondary mb-3 flex items-center gap-3 text-xs uppercase">
                        <span class="border-main flex-1 border-t"></span> o <span class="border-main flex-1 border-t"></span>
                    </div>
                    <a href="{{ route('cuenta.google.redirect') }}" class="border-main text-primary flex w-full items-center justify-center gap-2 rounded-lg border bg-white px-4 py-2 text-sm font-medium transition-colors hover:bg-slate-50 dark:bg-slate-800 dark:hover:bg-slate-700">
                        <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.27-4.74 3.27-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0 0 12 23z"/><path fill="#FBBC05" d="M5.84 14.1a6.6 6.6 0 0 1 0-4.2V7.06H2.18a11 11 0 0 0 0 9.88l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15A11 11 0 0 0 2.18 7.06l3.66 2.84C6.71 7.31 9.14 5.38 12 5.38z"/></svg>
                        Continuar con Google
                    </a>
                </div>
            @endif
        </div>
    </div>
@endsection

@extends('layouts.publica')

@section('titulo', 'Mi cuenta — Carwash El Chinito')

@section('descripcion', 'Panel de bienvenida para clientes registrados de Carwash El Chinito.')

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-10">
        <section class="bg-surface border-main rounded-2xl border p-8 text-center shadow-sm">
            <p class="text-secondary text-sm font-semibold tracking-wide uppercase">Mi cuenta</p>
            <h1 class="text-primary mt-2 text-3xl font-bold">¡Bienvenido, {{ $user->name }}!</h1>
            <p class="text-secondary mt-4">
                Gracias por registrarte. Este es tu panel de cliente
                @if ($cliente?->dni)
                    (DNI {{ $cliente->dni }}).
                @else
                    .
                @endif
            </p>
            <p class="text-secondary mt-2 text-sm">
                Aquí podrás ver tus vehículos registrados en el local.
            </p>

            @if (session('success'))
                <p role="status" class="mx-auto mt-4 max-w-md rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ session('success') }}</p>
            @endif

            @if (session('info'))
                <p role="status" class="mx-auto mt-4 max-w-md rounded-lg border border-sky-300 bg-sky-50 px-3 py-2 text-sm text-sky-700">{{ session('info') }}</p>
            @endif

            <div class="mt-6 flex flex-wrap justify-center gap-3">
                <a href="{{ route('cuenta.vehiculos.index') }}" class="rounded-lg bg-sky-600 px-5 py-2.5 font-semibold text-white hover:bg-sky-700">
                    Mis vehículos
                </a>
                <form action="{{ route('cuenta.logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="border-main text-secondary rounded-lg border px-5 py-2.5 font-semibold hover:underline">
                        Cerrar sesión
                    </button>
                </form>
            </div>

            <form action="{{ route('cuenta.sesiones.revocar') }}" method="POST" class="mt-4">
                @csrf
                <button type="submit" class="text-secondary text-sm hover:underline">
                    Cerrar mis otras sesiones
                </button>
            </form>

            @if (config('services.google.client_id'))
                <div class="border-main mx-auto mt-6 max-w-md rounded-xl border p-4 text-left">
                    <h2 class="text-primary text-sm font-semibold">Ingreso con Google</h2>
                    @if ($user->socialIdentities->where('provider', 'google')->isNotEmpty())
                        <p class="text-secondary mt-1 text-sm">Vinculado: {{ $user->socialIdentities->where('provider', 'google')->first()->provider_email }}</p>
                        <form action="{{ route('cuenta.google.desvincular') }}" method="POST" class="mt-2">
                            @csrf
                            <button type="submit" class="text-sm font-medium text-red-600 hover:underline">
                                Desvincular Google
                            </button>
                        </form>
                    @else
                        <p class="text-secondary mt-1 text-sm">Aún no vinculaste tu Google.</p>
                        <a href="{{ route('cuenta.google.redirect') }}" class="mt-2 inline-block text-sm font-medium text-sky-600 hover:underline">
                            Vincular mi Google
                        </a>
                    @endif
                    @error('email')
                        <p role="alert" class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @endif
        </section>
    </div>
@endsection

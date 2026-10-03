@extends('layouts.publica')

@section('titulo', 'Verificar cuenta — Carwash El Chinito')

@section('descripcion', 'Solicita un nuevo enlace de verificación para tu cuenta.')

@section('content')
    <div class="mx-auto max-w-md px-4 py-10">
        <div class="bg-surface border-main rounded-2xl border p-8 shadow-sm">
            <h1 class="text-primary text-center text-2xl font-semibold">Verificar cuenta</h1>
            <p class="text-secondary mt-2 text-center text-sm">Te enviamos un enlace al registrarte. Si venció, pide uno nuevo.</p>

            @if (session('success'))
                <p role="status" class="mt-4 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ session('success') }}</p>
            @endif

            <form action="{{ route('cuenta.verificacion.reenviar') }}" method="POST" novalidate class="mt-6">
                @csrf

                <div class="mb-6">
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

                <button
                    type="submit"
                    class="w-full rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white transition-colors duration-200 hover:bg-sky-700"
                >
                    Reenviar verificación
                </button>
            </form>

            <p class="text-secondary mt-4 text-center text-sm">
                <a href="{{ route('cuenta.login') }}" class="font-medium text-sky-600 hover:underline">Volver al inicio de sesión</a>
            </p>
        </div>
    </div>
@endsection

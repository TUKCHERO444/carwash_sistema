@extends('layouts.publica')

@section('titulo', 'Nueva contraseña — Carwash El Chinito')

@section('descripcion', 'Elige una nueva contraseña para tu cuenta.')

@section('content')
    <div class="mx-auto max-w-md px-4 py-10">
        <div class="bg-surface border-main rounded-2xl border p-8 shadow-sm">
            <h1 class="text-primary text-center text-2xl font-semibold">Nueva contraseña</h1>
            <p class="text-secondary mt-2 text-center text-sm">Al guardarla se cerrarán tus otras sesiones.</p>

            <form action="{{ route('cuenta.recuperacion.actualizar', ['token' => $token]) }}" method="POST" novalidate class="mt-6">
                @csrf

                <div class="mb-4">
                    <label for="password" class="label-main mb-1 block text-sm font-medium">
                        Contraseña <span class="text-secondary font-normal">(mínimo 8 caracteres)</span>
                    </label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        autocomplete="new-password"
                        @error('password') aria-describedby="password-error" aria-invalid="true" @enderror
                        class="input-main w-full rounded-lg border px-3 py-2 text-sm focus:ring-2 focus:ring-sky-500 focus:outline-none
                            {{ $errors->has('password') ? 'border-red-500' : '' }}"
                    >
                    @error('password')
                        <p id="password-error" role="alert" class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-6">
                    <label for="password_confirmation" class="label-main mb-1 block text-sm font-medium">
                        Confirmar contraseña
                    </label>
                    <input
                        type="password"
                        id="password_confirmation"
                        name="password_confirmation"
                        autocomplete="new-password"
                        class="input-main w-full rounded-lg border px-3 py-2 text-sm focus:ring-2 focus:ring-sky-500 focus:outline-none"
                    >
                </div>

                <button
                    type="submit"
                    class="w-full rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white transition-colors duration-200 hover:bg-sky-700"
                >
                    Guardar contraseña
                </button>
            </form>
        </div>
    </div>
@endsection

@extends('layouts.auth')

@section('content')
    <div class="bg-white dark:bg-surface-dark rounded-2xl shadow-sm border border-gray-200 dark:border-border-dark p-8 transition-colors duration-300">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-text-primary-dark mb-6 text-center">Iniciar sesión</h1>

        <form action="{{ route('login') }}" method="POST" novalidate>
            @csrf

            {{-- Campo: Correo electrónico --}}
            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-text-secondary-dark mb-1">
                    Correo electrónico
                </label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="{{ old('email') }}"
                    autocomplete="email"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-slate-800 text-gray-900 dark:text-text-primary-dark
                        {{ $errors->has('email') ? 'border-red-500 bg-red-50 dark:bg-red-900/20' : 'border-gray-300 dark:border-border-dark' }}"
                >
                @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Campo: Contraseña --}}
            <div class="mb-6">
                <label for="password" class="block text-sm font-medium text-gray-700 dark:text-text-secondary-dark mb-1">
                    Contraseña
                </label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-slate-800 text-gray-900 dark:text-text-primary-dark
                        {{ $errors->has('password') ? 'border-red-500 bg-red-50 dark:bg-red-900/20' : 'border-gray-300 dark:border-border-dark' }}"
                >
                @error('password')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Botón de envío --}}
            <button
                type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg text-sm transition-colors duration-200"
            >
                Iniciar sesión
            </button>
        </form>
    </div>
@endsection

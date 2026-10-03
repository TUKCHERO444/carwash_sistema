@extends('layouts.publica')

@section('titulo', 'Vincular Google — Carwash El Chinito')

@section('descripcion', 'Confirma la vinculación de tu cuenta de Google.')

@section('content')
    <div class="mx-auto max-w-md px-4 py-10">
        <div class="bg-surface border-main rounded-2xl border p-8 shadow-sm">
            <h1 class="text-primary text-center text-2xl font-semibold">Vincular Google</h1>
            <p class="text-secondary mt-2 text-center text-sm">
                Vas a vincular la cuenta de Google <strong>{{ $pendiente['email'] }}</strong>
                a tu cuenta {{ auth()->user()->email }}. Solo tú, con sesión iniciada, puedes hacerlo.
            </p>

            @if ($errors->any())
                <div role="alert" class="mt-4 rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form action="{{ route('cuenta.google.confirmar') }}" method="POST" class="mt-6">
                @csrf
                <button
                    type="submit"
                    class="w-full rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white transition-colors duration-200 hover:bg-sky-700"
                >
                    Confirmar vinculación
                </button>
            </form>

            <p class="text-secondary mt-4 text-center text-sm">
                <a href="{{ route('cuenta.panel') }}" class="font-medium text-sky-600 hover:underline">Cancelar y volver a mi cuenta</a>
            </p>
        </div>
    </div>
@endsection

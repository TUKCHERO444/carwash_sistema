@extends('layouts.publica')

@section('titulo', 'Mis vehículos — Carwash El Chinito')

@section('descripcion', 'Vehículos registrados a tu nombre en Carwash El Chinito.')

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-10">
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-primary text-2xl font-semibold">Mis vehículos</h1>
            <a href="{{ route('cuenta.panel') }}" class="text-sm font-medium text-sky-600 hover:underline">Volver a mi cuenta</a>
        </div>

        @if (session('success'))
            <p role="status" class="mb-4 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ session('success') }}</p>
        @endif

        @if ($vehiculos->isEmpty())
            <p class="bg-surface border-main text-secondary rounded-2xl border p-8 text-center text-sm">
                Aún no tienes vehículos registrados. Regístralos en el local en tu próxima visita.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="bg-surface border-main w-full overflow-hidden rounded-2xl border text-left text-sm">
                    <thead>
                        <tr class="border-main border-b">
                            <th class="text-secondary px-4 py-6 font-semibold">Placa</th>
                            <th class="text-secondary px-4 py-6 font-semibold">Marca</th>
                            <th class="text-secondary px-4 py-6 font-semibold">Modelo</th>
                            <th class="text-secondary px-4 py-6 font-semibold">Color</th>
                            <th class="px-4 py-6"><span class="sr-only">Ver</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-main divide-y">
                        @foreach ($vehiculos as $vehiculo)
                            <tr>
                                <td class="text-primary px-4 py-8 font-semibold">{{ $vehiculo->placa }}</td>
                                <td class="text-secondary px-4 py-8">{{ $vehiculo->marca ?? '—' }}</td>
                                <td class="text-secondary px-4 py-8">{{ $vehiculo->modelo ?? '—' }}</td>
                                <td class="text-secondary px-4 py-8">{{ $vehiculo->color ?? '—' }}</td>
                                <td class="px-4 py-8">
                                    <a href="{{ route('cuenta.vehiculos.show', ['vehiculo' => $vehiculo->placa]) }}" class="font-medium text-sky-600 hover:underline">Ver</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $vehiculos->links() }}
            </div>
        @endif
    </div>
@endsection

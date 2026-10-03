@extends('layouts.publica')

@section('titulo', 'Vehículo ' . $vehiculo->placa . ' — Carwash El Chinito')

@section('descripcion', 'Detalle de tu vehículo registrado en Carwash El Chinito.')

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-10">
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-primary text-2xl font-semibold">Vehículo {{ $vehiculo->placa }}</h1>
            <a href="{{ route('cuenta.vehiculos.index') }}" class="text-sm font-medium text-sky-600 hover:underline">Volver a mis vehículos</a>
        </div>

        <dl class="bg-surface border-main rounded-2xl border p-8 shadow-sm">
            <div class="divide-main divide-y">
                <div class="flex justify-between py-3">
                    <dt class="text-secondary text-sm font-medium">Placa</dt>
                    <dd class="text-primary text-sm font-semibold">{{ $vehiculo->placa }}</dd>
                </div>
                <div class="flex justify-between py-3">
                    <dt class="text-secondary text-sm font-medium">Titular</dt>
                    <dd class="text-primary text-sm">{{ $vehiculo->cliente?->nombre_completo ?: '—' }}</dd>
                </div>
                <div class="flex justify-between py-3">
                    <dt class="text-secondary text-sm font-medium">Marca</dt>
                    <dd class="text-primary text-sm">{{ $vehiculo->marca ?? '—' }}</dd>
                </div>
                <div class="flex justify-between py-3">
                    <dt class="text-secondary text-sm font-medium">Modelo</dt>
                    <dd class="text-primary text-sm">{{ $vehiculo->modelo ?? '—' }}</dd>
                </div>
                <div class="flex justify-between py-3">
                    <dt class="text-secondary text-sm font-medium">Color</dt>
                    <dd class="text-primary text-sm">{{ $vehiculo->color ?? '—' }}</dd>
                </div>
                <div class="flex justify-between py-3">
                    <dt class="text-secondary text-sm font-medium">VIN</dt>
                    <dd class="text-primary text-sm">{{ $vehiculo->vin ?? '—' }}</dd>
                </div>
            </div>
        </dl>
    </div>
@endsection

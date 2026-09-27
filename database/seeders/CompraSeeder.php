<?php

namespace Database\Seeders;

use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\Producto;
use App\Models\Proveedor;
use Illuminate\Database\Seeder;

class CompraSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Crea compras de demostración en varios estados para poblar la UI.
     * Las compras `recibida` ya tienen su correlativo asignado.
     * Las líneas se crean con productos existentes y costos realistas.
     */
    public function run(): void
    {
        $proveedores = Proveedor::where('estado', '1')->get();
        if ($proveedores->isEmpty()) {
            return;
        }

        $productos = Producto::where('activo', 1)->get();
        if ($productos->isEmpty()) {
            return;
        }

        // 1) Borrador sin líneas (para probar edición)
        $compraBorrador = Compra::create([
            'correlativo' => null,
            'proveedor_id' => $proveedores->random()->id,
            'fecha' => now()->subDays(5)->format('Y-m-d'),
            'tipo_documento' => 'Factura',
            'numero_documento' => 'F001-001234',
            'estado' => 'borrador',
            'subtotal' => 0,
            'total' => 0,
            'observaciones' => 'Borrador de prueba, pendiente de agregar productos.',
            'user_id' => 1,
        ]);

        // 2) Borrador con 3 líneas
        $lineasBorrador = $this->crearLineas($productos, 3);
        $subtotal = round(array_sum(array_column($lineasBorrador, 'subtotal')), 2);
        $compraConLineas = Compra::create([
            'correlativo' => null,
            'proveedor_id' => $proveedores->random()->id,
            'fecha' => now()->subDays(3)->format('Y-m-d'),
            'tipo_documento' => 'Boleta',
            'numero_documento' => 'B001-005678',
            'estado' => 'borrador',
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'observaciones' => 'Compra lista para recibir.',
            'user_id' => 1,
        ]);
        $this->guardarLineas($compraConLineas, $lineasBorrador);

        // 3) Recibida (con correlativo) — 5 líneas
        $lineasRecibida = $this->crearLineas($productos, 5);
        $subtotal = round(array_sum(array_column($lineasRecibida, 'subtotal')), 2);
        $compraRecibida = Compra::create([
            'correlativo' => 'CMP-0001',
            'proveedor_id' => $proveedores->random()->id,
            'fecha' => now()->subDays(10)->format('Y-m-d'),
            'tipo_documento' => 'Factura',
            'numero_documento' => 'F001-009876',
            'estado' => 'recibida',
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'observaciones' => 'Recepción confirmada, mercadería en stock.',
            'user_id' => 1,
            'fecha_recepcion' => now()->subDays(10),
        ]);
        $this->guardarLineas($compraRecibida, $lineasRecibida);

        // 4) Anulada
        $compraAnulada = Compra::create([
            'correlativo' => null,
            'proveedor_id' => $proveedores->random()->id,
            'fecha' => now()->subDays(15)->format('Y-m-d'),
            'tipo_documento' => 'Nota de venta',
            'numero_documento' => 'NV-000456',
            'estado' => 'anulada',
            'subtotal' => 0,
            'total' => 0,
            'observaciones' => 'Anulada por error en el proveedor.',
            'user_id' => 1,
        ]);

        // 5) Segunda recibida para tests de recepción múltiple
        $lineasRecibida2 = $this->crearLineas($productos, 2);
        $subtotal = round(array_sum(array_column($lineasRecibida2, 'subtotal')), 2);
        $compraRecibida2 = Compra::create([
            'correlativo' => 'CMP-0002',
            'proveedor_id' => $proveedores->random()->id,
            'fecha' => now()->subDays(20)->format('Y-m-d'),
            'tipo_documento' => 'Guia de remision',
            'numero_documento' => 'GR-000789',
            'estado' => 'recibida',
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'observaciones' => 'Segunda recepción de ejemplo.',
            'user_id' => 1,
            'fecha_recepcion' => now()->subDays(20),
        ]);
        $this->guardarLineas($compraRecibida2, $lineasRecibida2);
    }

    /**
     * Genera líneas aleatorias sin repetir producto.
     *
     * @return array<int, array<string, mixed>>
     */
    private function crearLineas($productos, int $cuantas): array
    {
        $seleccionados = $productos->shuffle()->take($cuantas);

        return $seleccionados->map(function ($producto) {
            $cantidad = rand(1, 30);
            $costo = round(rand(500, 50000) / 100, 2);

            return [
                'producto_id' => $producto->id,
                'cantidad' => $cantidad,
                'costo_unitario' => $costo,
                'subtotal' => round($cantidad * $costo, 2),
            ];
        })->values()->all();
    }

    private function guardarLineas(Compra $compra, array $lineas): void
    {
        foreach ($lineas as $linea) {
            DetalleCompra::create($linea + ['compra_id' => $compra->id]);
        }
    }
}

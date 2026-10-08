<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EgresoCaja extends Model
{
    protected $table = 'egresos_caja';

    /**
     * Catálogo de categorías de gasto. Único punto de definición: para
     * ampliar la clasificación basta agregar una entrada aquí (la validación,
     * los formularios y los reportes leen este catálogo).
     *
     * La categoría `compra_abastecimiento` es de uso exclusivo del sistema
     * (pagos del módulo de Compras); el egreso manual no debe duplicar
     * operaciones de mercadería que pertenecen a ese flujo.
     */
    public const CATEGORIAS = [
        'insumos_operativos' => 'Insumos operativos',
        'mantenimiento' => 'Mantenimiento',
        'servicios_basicos' => 'Servicios básicos',
        'transporte_movilidad' => 'Transporte / Movilidad',
        'pago_trabajador' => 'Pago a trabajador',
        'compra_abastecimiento' => 'Compra / Abastecimiento',
        'otros' => 'Otros',
    ];

    /**
     * Categorías seleccionables en el egreso manual. Se excluye
     * `compra_abastecimiento` (reservada al flujo de Compras) para evitar
     * que un gasto operativo duplique una operación de mercadería.
     *
     * @return array<string, string>
     */
    public static function categoriasManuales(): array
    {
        return array_diff_key(self::CATEGORIAS, ['compra_abastecimiento' => true]);
    }

    public static function etiquetaCategoria(?string $categoria): string
    {
        return self::CATEGORIAS[$categoria] ?? 'Sin clasificar';
    }

    protected $fillable = [
        'caja_id',
        'monto',
        'descripcion',
        'tipo_pago',
        'categoria',
        'trabajador_id',
        'user_id',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    /**
     * Relación con Caja
     */
    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    /**
     * Relación con User (usuario que registró el egreso)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Trabajador beneficiario (solo categoría pago_trabajador, opcional).
     */
    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(Trabajador::class);
    }
}

<?php

namespace App\Services;

use App\Models\Asistencia;
use App\Models\Automotor;
use App\Models\Caja;
use App\Models\CambioAceite;
use App\Models\CambioProducto;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\ContenidoWeb;
use App\Models\DetalleServicio;
use App\Models\DetalleVenta;
use App\Models\EgresoCaja;
use App\Models\Lavado;
use App\Models\LavadoTrabajador;
use App\Models\Marca;
use App\Models\MovimientoKardex;
use App\Models\Producto;
use App\Models\RegistroAuditoria;
use App\Models\Servicio;
use App\Models\Trabajador;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class AuditService
{
    /**
     * Módulos cuyo CRUD se registra automáticamente (observers).
     */
    public const AUDITABLE = [
        User::class,
        Role::class,
        Trabajador::class,
        Asistencia::class,
        Producto::class,
        Categoria::class,
        ContenidoWeb::class,
        Marca::class,
        Servicio::class,
        Vehiculo::class,
        Cliente::class,
        Automotor::class,
        Venta::class,
        CambioAceite::class,
        Lavado::class,
        Caja::class,
        EgresoCaja::class,
    ];

    /**
     * Modelos que NUNCA se auditan (pivotes, el propio kardex y la auditoría).
     */
    public const EXCLUIDOS = [
        DetalleVenta::class,
        CambioProducto::class,
        LavadoTrabajador::class,
        DetalleServicio::class,
        MovimientoKardex::class,
        RegistroAuditoria::class,
    ];

    /**
     * Columnas sensibles que no deben persistirse en la auditoría.
     */
    public const SENSIBLES = [
        'password',
        'remember_token',
    ];

    /**
     * Etiqueta de negocio pendiente para el próximo evento Eloquent.
     */
    protected ?string $accionAnotada = null;

    /**
     * Mapeo clase -> nombre legible de módulo.
     */
    protected array $modulos = [
        User::class => 'usuarios',
        Role::class => 'roles',
        Trabajador::class => 'trabajadores',
        Asistencia::class => 'asistencias',
        Producto::class => 'productos',
        Categoria::class => 'categorias',
        ContenidoWeb::class => 'contenido_web',
        Marca::class => 'marcas',
        Servicio::class => 'servicios',
        Vehiculo::class => 'vehiculos',
        Cliente::class => 'clientes',
        Automotor::class => 'automotores',
        Venta::class => 'ventas',
        CambioAceite::class => 'cambio_aceite',
        Lavado::class => 'lavados',
        Caja::class => 'caja',
        EgresoCaja::class => 'caja',
    ];

    /**
     * Define la etiqueta de negocio para la próxima acción Eloquent (una sola vez).
     */
    public function anotarAccion(string $accion): void
    {
        $this->accionAnotada = $accion;
    }

    /**
     * Consume (y limpia) la etiqueta de negocio pendiente.
     */
    public function consumirAccionAnotada(): ?string
    {
        $accion = $this->accionAnotada;
        $this->accionAnotada = null;

        return $accion;
    }

    /**
     * Limpia el contexto pendiente (p. ej. al finalizar la request).
     */
    public function reiniciarContexto(): void
    {
        $this->accionAnotada = null;
    }

    /**
     * ¿Debe auditarse el modelo dado?
     */
    public function esAuditable(Model $modelo): bool
    {
        if (in_array(get_class($modelo), self::EXCLUIDOS, true)) {
            return false;
        }

        return in_array(get_class($modelo), self::AUDITABLE, true);
    }

    /**
     * Nombre legible de módulo para un modelo (o fallback).
     */
    public function moduloDe(Model $modelo, ?string $modulo = null): string
    {
        return $modulo ?? ($this->modulos[get_class($modelo)] ?? class_basename($modelo));
    }

    /**
     * Filtra atributos sensibles y vacíos para persistir en la auditoría.
     */
    public function filtrarAtributos(array $atributos): array
    {
        foreach (self::SENSIBLES as $columna) {
            unset($atributos[$columna]);
        }

        return $atributos;
    }

    /**
     * Registra una acción de auditoría de forma directa y atómica.
     *
     * @param  Model  $modelo  Modelo auditado (o su instancia).
     * @param  string  $accion  Nombre de la acción.
     * @param  array|null  $antes  Valores previos (campos que cambiaron).
     * @param  array|null  $despues  Valores posteriores (campos que cambiaron).
     * @param  string|null  $modulo  Nombre de módulo (opcional, se deduce del modelo).
     */
    public function registrar(
        Model $modelo,
        string $accion,
        ?array $antes = null,
        ?array $despues = null,
        ?string $modulo = null
    ): void {
        if (! $this->esAuditable($modelo)) {
            return;
        }

        DB::table('registros_auditoria')->insert([
            'modulo' => $this->moduloDe($modelo, $modulo),
            'accion' => $accion,
            'auditable_type' => get_class($modelo),
            'auditable_id' => $modelo->getKey(),
            'datos_antes' => $antes === null ? null : json_encode($this->filtrarAtributos($antes)),
            'datos_despues' => $despues === null ? null : json_encode($this->filtrarAtributos($despues)),
            'usuario_id' => auth()->id(),
            'fecha_movimiento' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Registra una acción de sesión (inicio/cierre) sobre un usuario autenticado.
     */
    public function registrarSesion(User $user, string $accion): void
    {
        $this->registrar($user, $accion, null, null, 'sesiones');
    }
}

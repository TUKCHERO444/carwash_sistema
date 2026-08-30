<?php

namespace App\Http\Controllers;

use App\Models\RegistroAuditoria;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AccionesAuditoriaController extends Controller
{
    /**
     * Lista de módulos disponibles para el filtro.
     */
    protected array $modulos = [
        'usuarios' => 'Usuarios',
        'roles' => 'Roles',
        'trabajadores' => 'Trabajadores',
        'productos' => 'Productos',
        'categorias' => 'Categorías',
        'marcas' => 'Marcas',
        'servicios' => 'Servicios',
        'vehiculos' => 'Vehículos',
        'clientes' => 'Clientes',
        'automotores' => 'Automotores',
        'ventas' => 'Ventas',
        'cambio_aceite' => 'Cambio de aceite',
        'lavados' => 'Lavados',
        'caja' => 'Caja',
        'sesiones' => 'Sesiones',
    ];

    /**
     * Lista de acciones disponibles para el filtro.
     */
    protected array $acciones = [
        'crear',
        'actualizar',
        'eliminar',
        'confirmar',
        'toggle estado',
        'toggle activo',
        'ajustar stock',
        'actualizar ticket',
        'anular venta',
        'abrir caja',
        'cerrar caja',
        'registrar egreso',
        'inicio de sesión',
        'cierre de sesión',
    ];

    /**
     * Listado paginado de acciones de auditoría con filtros.
     */
    public function index(Request $request): View
    {
        $filtros = [
            'modulo' => $request->get('modulo'),
            'accion' => $request->get('accion'),
            'usuario_id' => $request->get('usuario_id'),
            'desde' => $request->get('desde'),
            'hasta' => $request->get('hasta'),
            'registro_id' => trim((string) $request->get('registro_id')),
        ];

        $query = RegistroAuditoria::with('usuario')->latest('fecha_movimiento');

        if (! empty($filtros['modulo'])) {
            $query->where('modulo', $filtros['modulo']);
        }

        if (! empty($filtros['accion'])) {
            $query->where('accion', $filtros['accion']);
        }

        if (! empty($filtros['usuario_id'])) {
            $query->where('usuario_id', $filtros['usuario_id']);
        }

        if (! empty($filtros['registro_id'])) {
            $query->where('auditable_id', $filtros['registro_id']);
        }

        if (! empty($filtros['desde'])) {
            $query->whereDate('fecha_movimiento', '>=', $filtros['desde']);
        }

        if (! empty($filtros['hasta'])) {
            $query->whereDate('fecha_movimiento', '<=', $filtros['hasta']);
        }

        $registros = $query->paginate(15)->withQueryString();

        return view('auditoria.acciones.index', [
            'registros' => $registros,
            'filtros' => $filtros,
            'modulos' => $this->modulos,
            'acciones' => $this->acciones,
        ]);
    }

    /**
     * Detalle de un registro de auditoría con sus datos antes/después.
     */
    public function show(RegistroAuditoria $registroAuditoria): View
    {
        $registroAuditoria->load('usuario', 'auditable');

        return view('auditoria.acciones.show', compact('registroAuditoria'));
    }
}

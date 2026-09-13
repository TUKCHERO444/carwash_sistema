<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Servicio;
use App\Services\ContenidoWebService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\View\View;

class PaginaInicioController extends Controller
{
    /**
     * Muestra la página pública de inicio del sistema.
     *
     * Cada sección se enciende/apaga desde el panel de contenidos web.
     */
    public function index(): View
    {
        $contenido = app(ContenidoWebService::class);

        // Servicios reales publicados (toggle activo + orden manual)
        $servicios = Servicio::web()->take(3)->get();

        // Marcas curadas desde el panel (o todas por nombre)
        $marcas = $contenido->marcasWeb();

        // Productos destacados activos con su marca y categoría (para enlazar
        // al detalle público de cada uno).
        $productos = Producto::where('activo', true)
            ->with('marca', 'categoria')
            ->latest()
            ->take(6)
            ->get();

        $empresa = config('carwash');

        return view('publica.inicio', compact('contenido', 'servicios', 'marcas', 'productos', 'empresa'));
    }

    /**
     * Muestra la página pública de servicios (servicios reales publicados).
     *
     * FUTURO: cada bloque de datos de abajo se reemplazará por una consulta
     * real a BD (categorías de servicios visibles en la web, con imagen
     * subida desde el panel). Hoy se entregan valores por defecto para
     * maquetar la página; la vista ya está preparada para recibirlos.
     */
    public function servicios(): View
    {
        $servicios = Servicio::web()->get();
        $contenido = app(ContenidoWebService::class);
        $empresa = config('carwash');

        return view('publica.servicios', compact('servicios', 'contenido', 'empresa'));
    }

    /**
     * Muestra la página pública de productos (mosaico de categorías con
     * productos activos, ordenadas por nombre).
     */
    public function productos(): View
    {
        $contenido = app(ContenidoWebService::class);

        $categorias = Categoria::whereHas('productos', function (Builder $q) {
            $q->where('activo', true);
        })
            ->withCount(['productos as activos_count' => function (Builder $q) {
                $q->where('activo', true);
            }])
            // Productos activos ordenados con foto primero: se usan para la
            // imagen de portada del tile (mosaico), con respaldo en gradiente.
            ->with(['productos' => function (HasMany $q) {
                $q->where('activo', true)->orderByRaw('foto IS NULL');
            }])
            ->orderBy('nombre')
            ->get();

        $categorias_productos = $categorias->map(function (Categoria $categoria) {
            return [
                'slug' => $categoria->slug,
                'nombre' => $categoria->nombre,
                'descripcion' => $categoria->descripcion,
                'icono' => $this->iconoParaCategoria($categoria->slug),
                'imagen' => optional($categoria->productos->first())?->foto_url,
            ];
        })->values()->all();

        $empresa = config('carwash');

        return view('publica.productos', compact('contenido', 'categorias_productos', 'empresa'));
    }

    /**
     * Deriva el ícono del mosaico a partir del slug de la categoría.
     */
    protected function iconoParaCategoria(string $slug): string
    {
        return match (true) {
            str_contains($slug, 'aceite'),
            str_contains($slug, 'lubricante'),
            str_contains($slug, 'liquido') => 'lavado',
            str_contains($slug, 'filtro'),
            str_contains($slug, 'bateria'),
            str_contains($slug, 'electrico') => 'ceramico',
            str_contains($slug, 'freno') => 'descontaminado',
            str_contains($slug, 'neumatico') => 'limpiadores',
            str_contains($slug, 'suspension'),
            str_contains($slug, 'correa'),
            str_contains($slug, 'cadena') => 'carroceria',
            default => 'ceramico',
        };
    }

    /**
     * Muestra la página pública "¿Quiénes somos?" (misión, visión y valores).
     *
     * FUTURO: cada bloque de datos de abajo se reemplazará por contenido
     * editable desde el panel (editor de contenidos). Hoy se entregan valores
     * por defecto para maquetar la página; la vista ya está preparada para
     * recibirlos.
     */
    public function quienesSomos(): View
    {
        // FUTURO: ContenidoWeb::where('clave', 'historia')->first()
        $mision = 'Brindar un servicio de estética automotriz de excelencia, cuidando cada vehículo con productos originales y un equipo especializado, para que nuestros clientes salgan siempre satisfechos de nuestro local.';
        $vision = 'Ser reconocidos como la lavandería y edición automotriz líder de la región, innovando constantemente en técnicas, equipos y productos de primer nivel.';

        // FUTURO: Valor::where('visible_web', true)->orderBy('orden')->get()
        $valores = [
            ['titulo' => 'Calidad', 'detalle' => 'Productos originales y un acabado impecable en cada servicio.', 'icono' => 'check'],
            ['titulo' => 'Honestidad', 'detalle' => 'Precios claros y un trato transparente con cada cliente.', 'icono' => 'shield'],
            ['titulo' => 'Puntualidad', 'detalle' => 'Cumplimos los plazos prometidos para que tu auto esté a tiempo.', 'icono' => 'clock'],
            ['titulo' => 'Cercanía', 'detalle' => 'Atención personalizada y asesoría desde el primer momento.', 'icono' => 'chat'],
        ];

        $empresa = config('carwash');

        return view('publica.quienes-somos', compact('mision', 'vision', 'valores', 'empresa'));
    }

    /**
     * Muestra la página pública de marcas con las que trabajamos.
     *
     * Se renderiza la curaduría del panel cuando existe; si está vacía,
     * todas las marcas ordenadas por nombre.
     */
    public function marcas(): View
    {
        $contenido = app(ContenidoWebService::class);
        $marcas = $contenido->marcasWeb();

        $empresa = config('carwash');

        return view('publica.marcas', compact('contenido', 'marcas', 'empresa'));
    }

    /**
     * Muestra la página pública "Cotiza con nosotros" (formulario de contacto).
     *
     * El tema de referencia es una página con header centrado (título +
     * descripción) seguido de un formulario de contacto con los campos
     * nombre, email, celular y mensaje.
     *
     * FUTURO: guardar el mensaje en BD (MensajeContacto) o enviarlo por email
     * / WhatsApp. Hoy el formulario maqueta la interfaz (action="#").
     */
    public function cotiza(): View
    {
        $empresa = config('carwash');

        return view('publica.cotiza', compact('empresa'));
    }

    /**
     * Muestra los productos reales de una categoría (vista filtrada).
     *
     * Enlaza la gestión de inventario con la web: cada categoría del panel
     * que tenga al menos un producto activo se publica en el dropdown del
     * navbar. Esta vista replica el "collection" del tema de referencia
     * (breadcrumb, h1, contador y grid de cards de producto con estado de
     * stock).
     */
    public function productosCategoria(Categoria $categoria): View
    {
        $productos = $categoria->productos()
            ->where('activo', true)
            ->with('marca')
            ->orderBy('nombre')
            ->paginate(12);

        $empresa = config('carwash');

        return view('publica.productos-categoria', compact('categoria', 'productos', 'empresa'));
    }

    /**
     * Muestra la página pública de detalle de un producto de una categoría.
     *
     * Replica el "product page" del tema de referencia: galería (imagen),
     * breadcrumb, marca, título, descripción, precio y estado de stock, con
     * botón que lleva al formulario de cotización (no hay carrito).
     * Solo se publica un producto activo dentro de su propia categoría.
     */
    public function productoDetalle(Categoria $categoria, Producto $producto): View
    {
        abort_unless($producto->activo && $producto->categoria_id === $categoria->id, 404);

        $producto->load('marca');

        $relacionados = $categoria->productos()
            ->where('activo', true)
            ->whereKeyNot($producto->id)
            ->with('marca')
            ->orderBy('nombre')
            ->limit(4)
            ->get();

        $empresa = config('carwash');

        return view('publica.producto-detalle', compact('categoria', 'producto', 'relacionados', 'empresa'));
    }
}

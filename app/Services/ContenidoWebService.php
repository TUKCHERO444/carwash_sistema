<?php

namespace App\Services;

use App\Models\ContenidoWeb;
use App\Models\Marca;
use Illuminate\Database\Eloquent\Collection;

class ContenidoWebService
{
    /**
     * Definición de cada clave del panel de contenidos web.
     * clave => ['tipo' => bool|string|json, 'seccion' => ..., 'default' => ...]
     */
    public const DEFAULTS = [
        'inicio_mostrar_marcas' => ['tipo' => 'bool', 'seccion' => 'inicio', 'default' => true],
        'inicio_mostrar_servicios' => ['tipo' => 'bool', 'seccion' => 'inicio', 'default' => true],
        'inicio_mostrar_productos' => ['tipo' => 'bool', 'seccion' => 'inicio', 'default' => true],
        'inicio_mostrar_proyecto' => ['tipo' => 'bool', 'seccion' => 'inicio', 'default' => true],
        'productos_mostrar_mosaico' => ['tipo' => 'bool', 'seccion' => 'productos', 'default' => true],
        'productos_titulo' => ['tipo' => 'string', 'seccion' => 'productos', 'default' => '¿Qué producto buscas para tu auto?'],
        'productos_intro' => ['tipo' => 'string', 'seccion' => 'productos', 'default' => 'Explora las colecciones y encuentra el producto ideal para proteger y lucir tu vehículo.'],
        'servicios_titulo' => ['tipo' => 'string', 'seccion' => 'servicios', 'default' => '¿Qué necesita tu auto?'],
        'servicios_intro' => ['tipo' => 'string', 'seccion' => 'servicios', 'default' => 'Cada servicio es realizado por especialistas y con productos de primeras marcas.'],
        'marcas_titulo' => ['tipo' => 'string', 'seccion' => 'marcas', 'default' => 'Trabajamos con las mejores marcas'],
        'marcas_intro' => ['tipo' => 'string', 'seccion' => 'marcas', 'default' => 'Productos originales y equipos profesionales de primeras marcas para el cuidado de tu vehículo.'],
        'marcas_web' => ['tipo' => 'json', 'seccion' => 'marcas', 'default' => []], // [{marca_id, orden}]
    ];

    /**
     * Cache en memoria del mapa clave => valor de la BD (1 query por request).
     */
    private ?array $valores = null;

    /**
     * Claves registradas en el panel.
     */
    public function claves(): array
    {
        return array_keys(self::DEFAULTS);
    }

    /**
     * Claves del panel agrupadas por sección (para armar la vista).
     */
    public function clavesPorSeccion(): array
    {
        $porSeccion = [];

        foreach (self::DEFAULTS as $clave => $meta) {
            $porSeccion[$meta['seccion']][] = $clave;
        }

        return $porSeccion;
    }

    /**
     * Lee un valor booleano con fallback al default (alias de bool para la vista).
     */
    public function boolValue(string $clave): bool
    {
        return $this->bool($clave);
    }

    /**
     * Lee un texto con fallback al default (alias de text para la vista).
     */
    public function textValue(string $clave): ?string
    {
        return $this->text($clave);
    }

    /**
     * Mapa completo clave => valor de la BD (sin defaults).
     */
    public function all(): array
    {
        if ($this->valores === null) {
            $this->valores = ContenidoWeb::pluck('valor', 'clave')->toArray();
        }

        return $this->valores;
    }

    /**
     * Lee un valor booleano con fallback al default definido.
     */
    public function bool(string $clave): bool
    {
        $meta = self::DEFAULTS[$clave] ?? null;
        if ($meta === null || $meta['tipo'] !== 'bool') {
            return false;
        }

        $valor = $this->all()[$clave] ?? null;

        return $valor === null
            ? (bool) $meta['default']
            : in_array($valor, ['1', 'true', 1], true);
    }

    /**
     * Lee un texto con fallback al default definido (o al pasado por parámetro).
     */
    public function text(string $clave, ?string $fallback = null): ?string
    {
        $valor = $this->all()[$clave] ?? null;

        if ($valor !== null) {
            return (string) $valor;
        }

        if ($fallback !== null) {
            return $fallback;
        }

        $meta = self::DEFAULTS[$clave] ?? null;

        return $meta !== null ? (string) $meta['default'] : null;
    }

    /**
     * Lee un valor JSON con fallback al default definido.
     */
    public function json(string $clave): array
    {
        $meta = self::DEFAULTS[$clave] ?? null;
        $valor = $this->all()[$clave] ?? null;

        if ($valor === null) {
            return $meta['default'] ?? [];
        }

        $decodificado = json_decode($valor, true);

        return is_array($decodificado) ? $decodificado : [];
    }

    /**
     * Marca -> orden proveniente de la curaduría del panel.
     */
    public function estadoMarcasWeb(): array
    {
        $mapa = [];

        foreach ($this->json('marcas_web') as $item) {
            $mapa[(int) ($item['marca_id'] ?? 0)] = (int) ($item['orden'] ?? 0);
        }

        return $mapa;
    }

    /**
     * Marcas curadas del panel (ordenadas por su posición `orden`). Si la
     * curaduría está vacía, devuelve todas las marcas ordenadas por nombre.
     */
    public function marcasWeb(): Collection
    {
        $curaduria = $this->json('marcas_web');

        if (empty($curaduria)) {
            return Marca::orderBy('nombre')->get();
        }

        $ordenadas = collect($curaduria)
            ->filter(fn ($item) => isset($item['marca_id']))
            ->sortBy(fn ($item) => (int) ($item['orden'] ?? 0))
            ->values();

        $ids = $ordenadas->pluck('marca_id')->map(fn ($id) => (int) $id)->all();
        $posiciones = array_flip($ids);

        return Marca::whereIn('id', $ids)->get()
            ->sortBy(fn (Marca $marca) => $posiciones[$marca->id] ?? PHP_INT_MAX)
            ->values();
    }
}

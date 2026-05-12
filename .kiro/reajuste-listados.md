# Guía de Reajuste de Listados (Paginación y Layout)

Esta guía detalla los patrones aplicados para mejorar la legibilidad y la experiencia móvil en los módulos de listado del sistema.

## 1. Ajuste de Paginación

Para mejorar la velocidad de carga y reducir el scroll vertical excesivo, se ha estandarizado el número de registros por página.

- **Cambio**: De 15 a 10 registros.
- **Implementación**: Modificar en el Controlador correspondiente.
  ```php
  // Antes
  $items = Modelo::paginate(15);
  
  // Después
  $items = Modelo::paginate(10);
  ```

---

## 2. Optimización Visual (Layout)

Se han realizado tres ajustes clave en la estructura de las tablas para mejorar la interacción táctil y la visibilidad en móviles.

### A. Aumento del Tamaño Vertical (Padding)
Para que los elementos sean más fáciles de tocar y leer en móviles, se ha duplicado el espacio vertical.
- **Cabeceras (`th`)**: Cambiar `py-3` por `py-6`.
- **Celdas (`td`)**: Cambiar `py-4` por `py-8` (o `py-3` por `py-6` si aplica).

### B. Visibilidad y Scroll Móvil
Para evitar que la información quede "bloqueada" o cortada en pantallas pequeñas, se debe permitir el desplazamiento horizontal.
- **Cambio**: Reemplazar `overflow-hidden` por `overflow-x-auto` en el contenedor principal de la tabla.
  ```html
  <!-- Antes -->
  <div class="... overflow-hidden">
  
  <!-- Después -->
  <div class="... overflow-x-auto">
  ```

### C. Condensación de Información (Módulos Pendientes)
En los listados de tipo "Pendiente" o "En Proceso", se ha simplificado la vista para mostrar solo lo esencial.
- **Columnas recomendadas**:
    1. **Foto**: Miniatura del registro.
    2. **Identificador principal**: Placa, Nombre o Código.
    3. **Acciones**: Botón principal de proceso (ej: Abrir Ticket).

---

## 3. Ejemplo de Implementación (Blade)

Estructura recomendada para un listado condensado y optimizado:

```html
<div class="bg-white rounded-lg border border-gray-200 overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th scope="col" class="px-4 py-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Foto</th>
                <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Placa</th>
                <th scope="col" class="px-6 py-6 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            @foreach($items as $item)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-6 whitespace-nowrap">
                        <!-- Lógica de Imagen -->
                    </td>
                    <td class="px-6 py-8 whitespace-nowrap text-sm text-gray-700 font-medium">
                        {{ $item->placa }}
                    </td>
                    <td class="px-6 py-8 whitespace-nowrap text-sm">
                        <!-- Botones de Acción -->
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
```

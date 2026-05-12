# Documento de Requisitos

## Introducción

Este documento describe los requisitos para la reestructuración del JavaScript embebido en las vistas Blade del proyecto Laravel. El objetivo es extraer todo el código JavaScript de los archivos `.blade.php` y moverlo a módulos dedicados dentro de `resources/js/`, organizados por módulo de negocio, manteniendo la funcionalidad existente intacta y registrando cada módulo en el pipeline de Vite.

### Alcance

Tras el análisis del proyecto, se identificaron **7 vistas** con JavaScript embebido en **4 módulos**:

| Módulo | Vistas con JS embebido |
|---|---|
| `ventas` | `create.blade.php` |
| `ingresos` | `create.blade.php`, `edit.blade.php`, `confirmar.blade.php` |
| `cambio-aceite` | `create.blade.php`, `edit.blade.php` |
| `productos` | `edit.blade.php` |

El JS embebido en el layout (`layouts/app.blade.php`) ya fue migrado previamente a `resources/js/app.js` y está fuera del alcance.

---

## Glosario

- **Blade_View**: Archivo de plantilla `.blade.php` de Laravel ubicado en `resources/views/`.
- **JS_Module**: Archivo JavaScript dedicado ubicado en `resources/js/`, organizado por carpeta de módulo.
- **Vite**: Bundler de frontend configurado en `vite.config.js` que procesa los archivos de `resources/js/`.
- **Inline_Script**: Bloque `<script>...</script>` embebido directamente dentro de una Blade_View.
- **@vite_Directive**: Directiva de Blade que carga un archivo JS compilado por Vite en el HTML final.
- **Shared_Logic**: Código JavaScript que es idéntico o funcionalmente equivalente entre dos o más vistas del mismo módulo.
- **Module_Folder**: Carpeta dentro de `resources/js/` que agrupa los JS_Modules de un mismo módulo de negocio (ej. `resources/js/ventas/`).
- **Entry_Point**: Archivo JS registrado en el array `input` de `vite.config.js` para que Vite lo compile y genere un bundle.

---

## Requisitos

### Requisito 1: Auditoría de JavaScript embebido

**User Story:** Como desarrollador, quiero identificar todas las vistas que contienen JavaScript embebido, para tener un inventario completo antes de iniciar la migración.

#### Criterios de Aceptación

1. THE Desarrollador SHALL identificar cada Blade_View que contiene al menos un Inline_Script.
2. WHEN se complete la auditoría, THE Desarrollador SHALL producir un inventario que liste, por módulo, cada Blade_View afectada y una descripción de la responsabilidad del Inline_Script que contiene.
3. THE Inventario SHALL incluir los 4 módulos identificados: `ventas`, `ingresos`, `cambio-aceite` y `productos`.

---

### Requisito 2: Creación de la estructura de carpetas JS por módulo

**User Story:** Como desarrollador, quiero una estructura de carpetas organizada en `resources/js/`, para que cada módulo de negocio tenga su propio espacio de archivos JavaScript.

#### Criterios de Aceptación

1. THE Desarrollador SHALL crear una Module_Folder dentro de `resources/js/` por cada módulo que tenga Inline_Scripts: `resources/js/ventas/`, `resources/js/ingresos/`, `resources/js/cambio-aceite/` y `resources/js/productos/`.
2. WHEN se cree una Module_Folder, THE Desarrollador SHALL crear dentro de ella un JS_Module por cada Blade_View que tenga Inline_Script en ese módulo.
3. THE nombre de cada JS_Module SHALL coincidir con el nombre de la Blade_View de origen (ej. `create.js`, `edit.js`, `confirmar.js`).

---

### Requisito 3: Extracción del código JavaScript a módulos dedicados

**User Story:** Como desarrollador, quiero mover el código JavaScript de las vistas a archivos JS dedicados, para separar la lógica de presentación de la lógica de comportamiento.

#### Criterios de Aceptación

1. WHEN se migre un Inline_Script, THE Desarrollador SHALL copiar el contenido del bloque `<script>` al JS_Module correspondiente sin modificar su lógica.
2. WHEN el JS_Module esté creado, THE Desarrollador SHALL eliminar el bloque `<script>` completo de la Blade_View de origen.
3. THE JS_Module SHALL contener únicamente el código JavaScript extraído, sin etiquetas `<script>` ni directivas Blade.
4. IF el código JavaScript de un Inline_Script referencia variables PHP inyectadas mediante `@json()` u otras directivas Blade, THEN THE Desarrollador SHALL exponer esos datos como variables globales en la Blade_View usando un bloque `<script>` mínimo de inicialización de datos, separado de la lógica principal que reside en el JS_Module.
5. THE bloque de inicialización de datos SHALL contener únicamente asignaciones de variables globales (ej. `window.serviciosExistentes = @json(...)`) y no lógica de comportamiento.

---

### Requisito 4: Registro de los módulos JS en Vite

**User Story:** Como desarrollador, quiero que cada JS_Module sea procesado por Vite, para que el navegador reciba el código compilado y optimizado.

#### Criterios de Aceptación

1. THE Desarrollador SHALL registrar cada JS_Module como un Entry_Point en el array `input` de `vite.config.js`.
2. WHEN se registre un Entry_Point, THE Vite SHALL compilar el JS_Module y generar un archivo en `public/build/`.
3. THE `vite.config.js` SHALL mantener los Entry_Points existentes (`resources/css/app.css` y `resources/js/app.js`) sin modificación.

---

### Requisito 5: Carga de los módulos JS en las vistas Blade

**User Story:** Como desarrollador, quiero que cada vista cargue su JS_Module correspondiente mediante la directiva `@vite`, para que el código JavaScript siga ejecutándose correctamente en el navegador.

#### Criterios de Aceptación

1. WHEN se elimine un Inline_Script de una Blade_View, THE Desarrollador SHALL agregar una directiva `@vite` en esa Blade_View que referencie el JS_Module correspondiente.
2. THE directiva `@vite` SHALL colocarse al final del contenido de la sección `@section('content')`, antes del cierre `@endsection`.
3. WHEN una Blade_View requiera datos PHP para su JS_Module, THE Blade_View SHALL incluir un bloque `<script>` de inicialización de datos antes de la directiva `@vite`.

---

### Requisito 6: Identificación y extracción de lógica compartida

**User Story:** Como desarrollador, quiero identificar el código JavaScript duplicado entre vistas del mismo módulo, para extraerlo a un archivo compartido y evitar redundancia.

#### Criterios de Aceptación

1. THE Desarrollador SHALL analizar los JS_Modules de cada módulo para identificar Shared_Logic.
2. WHEN se identifique Shared_Logic entre dos o más vistas de un mismo módulo, THE Desarrollador SHALL extraer esa lógica a un archivo `shared.js` dentro de la Module_Folder correspondiente.
3. THE archivo `shared.js` SHALL exportar las funciones o constantes compartidas usando la sintaxis de módulos ES (`export function`, `export const`).
4. THE JS_Modules que consuman Shared_Logic SHALL importar las funciones necesarias desde `shared.js` usando `import`.
5. IF un módulo no tiene Shared_Logic entre sus vistas, THEN THE Desarrollador SHALL omitir la creación del archivo `shared.js` para ese módulo.

---

### Requisito 7: Preservación de la funcionalidad existente

**User Story:** Como desarrollador, quiero que la migración no rompa ningún flujo de usuario existente, para garantizar que la aplicación siga funcionando correctamente tras la reestructuración.

#### Criterios de Aceptación

1. WHEN se complete la migración de un módulo, THE Aplicación SHALL ejecutar todas las interacciones JavaScript de ese módulo de forma idéntica a como lo hacía antes de la migración.
2. THE búsqueda Ajax con debounce en los formularios de `ventas/create`, `ingresos/create`, `ingresos/edit`, `ingresos/confirmar`, `cambio-aceite/create` y `cambio-aceite/edit` SHALL seguir funcionando correctamente tras la migración.
3. THE cálculo dinámico de totales, descuentos y validación de pago mixto en los formularios afectados SHALL producir los mismos resultados antes y después de la migración.
4. THE previsualización de imágenes en `ingresos/create`, `ingresos/edit`, `ingresos/confirmar`, `cambio-aceite/create`, `cambio-aceite/edit` y `productos/edit` SHALL seguir funcionando correctamente tras la migración.
5. IF el JS_Module de una vista depende de datos del servidor (ej. lista de servicios existentes en `ingresos/edit`), THEN THE Blade_View SHALL proveer esos datos mediante variables globales antes de cargar el JS_Module, de modo que el JS_Module pueda leerlos sin cambios en su lógica.
6. THE renderizado dinámico de tablas de detalle en los formularios de `ventas/create`, `ingresos/edit`, `ingresos/confirmar`, `cambio-aceite/create` y `cambio-aceite/edit` SHALL seguir funcionando correctamente tras la migración.

---

### Requisito 8: Limpieza final de las vistas Blade

**User Story:** Como desarrollador, quiero que las vistas Blade queden libres de lógica JavaScript compleja, para mejorar la legibilidad y mantenibilidad del código de presentación.

#### Criterios de Aceptación

1. WHEN se complete la migración de todos los módulos, THE Blade_View SHALL no contener ningún Inline_Script con lógica de comportamiento (funciones, event listeners, llamadas Ajax).
2. THE única excepción permitida es el bloque `<script>` de inicialización de datos que expone variables PHP como variables globales de JavaScript.
3. THE Blade_View SHALL mantener intacto todo su contenido HTML y las directivas Blade existentes.

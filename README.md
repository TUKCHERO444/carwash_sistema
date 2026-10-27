# Sistema de Lavado de Autos (Car Wash)

Sistema de punto de venta y gestión operativa para un negocio de lavado de autos, orientado al mercado peruano. Incluye ventas, caja, inventario, compras, servicios, lavados, cambio de aceite, trabajadores, clientes, reportes, auditoría y una tienda pública con carrito.

## Stack

- Laravel 12 / PHP 8.2
- Blade + Tailwind CSS 4 + Vite 7 (modo oscuro)
- SQLite para desarrollo y pruebas (PostgreSQL opcional para despliegue)
- Spatie Laravel Permission (roles y permisos)
- Cloudinary para imágenes
- PHPUnit 11 y Vitest + fast-check para pruebas

## Requisitos

Antes de instalar, verifica que tengas:

- PHP 8.2 o superior con las extensiones `openssl`, `pdo`, `pdo_sqlite`, `sqlite3`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `zip`, `curl` y `gd`.
- Composer 2.
- Node.js 20.19+ (se recomienda 22 LTS o superior) y npm.
- Git.
- Opcional: XAMPP, Laragon o Apache/Nginx si deseas servir el proyecto sin `php artisan serve`.

Verifica las versiones:

```powershell
php -v
composer -V
node -v
npm -v
```

## Instalación en entorno local

1. Clona el repositorio y entra al directorio:

```powershell
git clone <url-del-repositorio> carwash_sistema
cd carwash_sistema
```

2. Instala las dependencias de PHP:

```powershell
composer install
```

3. Crea el archivo de configuración y genera la clave de la aplicación:

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

En Linux/macOS o Git Bash:

```bash
cp .env.example .env
php artisan key:generate
```

4. Crea el archivo de SQLite (solo si usarás SQLite, que es la configuración por defecto):

```powershell
New-Item -ItemType File -Path database/database.sqlite -Force
```

En Linux/macOS:

```bash
touch database/database.sqlite
```

5. Revisa la conexión en `.env`. Para SQLite debe estar así:

```env
DB_CONNECTION=sqlite
```

Ajusta también la URL base del entorno local:

```env
APP_URL=http://localhost:8000
```

6. Ejecuta las migraciones y carga los datos de demostración:

```powershell
php artisan migrate --seed
```

Si necesitas reiniciar la base de datos desde cero:

```powershell
php artisan migrate:fresh --seed
```

7. Instala las dependencias del frontend y compila los assets:

```powershell
npm install
npm run build
```

8. Inicia el entorno de desarrollo completo:

```powershell
composer run dev
```

Este comando levanta en paralelo el servidor de Laravel, el worker de colas, los logs con Pail y Vite. Abre `http://localhost:8000` en el navegador.

## Credenciales de demostración

Con `APP_ENV=local` o `testing` y `ADMIN_PASSWORD` vacío, el seeder crea el administrador inicial:

- Correo: `admin@sistema.com`
- Contraseña: `password`

En producción es obligatorio definir `ADMIN_EMAIL` y `ADMIN_PASSWORD` con una contraseña que cumpla la política de seguridad. Si no se define, el administrador no se crea.

## Variables de entorno importantes

| Variable | Descripción |
| --- | --- |
| `APP_ENV` | `local` en desarrollo; `production` en despliegue. |
| `APP_DEBUG` | `true` en local; debe ser `false` en producción. |
| `APP_URL` | URL base del sistema, por ejemplo `http://localhost:8000`. |
| `DB_CONNECTION` | `sqlite` por defecto o `pgsql` para PostgreSQL. |
| `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` | Credenciales del administrador inicial. |
| `VEHICLE_API_TOKEN` | Token de `api.json.pe` para consultar DNI y placa. |
| `CLOUDINARY_URL` | Credenciales de Cloudinary para subir imágenes. |
| `WHATSAPP_PEDIDOS` | Número de WhatsApp de 9 dígitos para pedidos del carrito. |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | Login con Google de la zona pública (opcional). |

Las APIs externas son tolerantes a fallos: si no hay token o el servicio no responde, los formularios siguen funcionando sin autocompletado.

### Cloudinary

Las imágenes de productos, lavados y cambio de aceite se almacenan en Cloudinary. Agrega la credencial en `.env`:

```env
CLOUDINARY_URL=cloudinary://<api_key>:<api_secret>@<cloud_name>
```

Sin esta variable las demás funciones siguen operando, pero las subidas de imágenes fallarán.

## Ejecución en desarrollo

- Servidor completo (recomendado): `composer run dev`
- Solo Laravel: `php artisan serve`
- Solo Vite: `npm run dev`
- Logs: `php artisan pail`
- Colas: `php artisan queue:listen --tries=1 --timeout=0`

Si cambias archivos en `resources/js` o `resources/css`, mantén Vite corriendo o vuelve a ejecutar `npm run build`.

## Despliegue local

### Opción 1: servidor integrado de Laravel

```powershell
npm run build
php artisan migrate --force
php artisan serve --host=127.0.0.1 --port=8000
```

### Opción 2: Apache / XAMPP / Laragon

1. Configura el `DocumentRoot` del sitio apuntando a la carpeta `public` del proyecto, por ejemplo `C:\xampp\htdocs\carwash_sistema\public`. Nunca apuntes a la raíz del proyecto.
2. Habilita `mod_rewrite`.
3. Permite la lectura de `.htaccess` (`AllowOverride All`).
4. Compila los assets con `npm run build`.
5. Verifica el estado del sistema en `http://localhost/ping`. Si no modificaste el `DocumentRoot`, accede mediante `http://localhost/carwash_sistema/public/ping`.

### Opción 3: Docker (opcional)

El repositorio incluye `Dockerfile` y `start.sh`, pensados para un despliegue con PostgreSQL. Para probarlo en local:

```powershell
docker build -t carwash-sistema .
docker run --rm -p 8080:80 --env-file .env carwash-sistema
```

Si usas SQLite dentro del contenedor, crea el archivo `database/database.sqlite` antes del build y monta la carpeta `database` como volumen para no perder los datos:

```powershell
docker run --rm -p 8080:80 --env-file .env -v "${PWD}/database:/var/www/html/database" carwash-sistema
```

El contenedor ejecuta las migraciones automáticamente al iniciar y expone el puerto `8080` en el host.

## Pruebas y calidad de código

```powershell
composer run test        # PHPUnit con SQLite en memoria
npm run test             # Vitest (pruebas de propiedades)
vendor/bin/pint          # Formatea el código PHP
vendor/bin/pint --test   # Verifica el formato sin modificar archivos
```

Las pruebas no requieren una base de datos externa ni conexión a internet: las APIs y Cloudinary se simulan.

## Comandos útiles

| Comando | Descripción |
| --- | --- |
| `php artisan migrate:fresh --seed` | Reinicia la base de datos y carga datos demo. |
| `php artisan data:clean` | Normaliza datos heredados sin borrar información. |
| `php artisan config:clear` | Limpia la caché de configuración. |
| `php artisan route:list` | Lista las rutas registradas. |
| `php artisan test --filter=NombreTest` | Ejecuta una prueba específica. |

## Notas del proyecto

- La zona horaria es `America/Lima` (`config/app.php`). No la cambies a UTC ni dependas de la hora del servidor.
- Las rutas estáticas y AJAX deben declararse antes de las rutas de recursos para evitar conflictos de parámetros.
- Los permisos se definen en `database/seeders/PermissionSeeder.php`; los nuevos módulos deben registrarse allí.
- Los modelos auditables deben agregarse a la lista de `AuditServiceProvider`.
- Las vistas listado usan `paginate(10)` y contenedores con `overflow-x-auto`.
- La documentación funcional de cada módulo está en `.kiro/specs/<modulo>/` y las guías de interfaz en `.kiro/`.

## Solución de problemas

- **`Database file at path ... does not exist`**: crea `database/database.sqlite` y verifica `DB_CONNECTION=sqlite`.
- **`Vite manifest not found`**: ejecuta `npm run build` o mantén `npm run dev` activo.
- **Error 419 al enviar formularios**: confirma que migraste la base de datos (las sesiones usan la tabla `sessions`) y que `APP_KEY` está generada.
- **No se ve el administrador inicial**: define `ADMIN_EMAIL` y `ADMIN_PASSWORD` o ejecuta en entorno `local`/`testing` con la contraseña demo.
- **Cambios de estilos o JavaScript no se reflejan**: limpia la caché con `php artisan optimize:clear` y vuelve a compilar.
- **No cargan imágenes**: revisa `CLOUDINARY_URL` y la conectividad con Cloudinary.

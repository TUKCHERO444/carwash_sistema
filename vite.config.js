import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                // Ventas
                'resources/js/ventas/create.js',
                // Lavados
                'resources/js/lavados/create.js',
                'resources/js/lavados/edit.js',
                'resources/js/lavados/confirmar.js',
                // Cambio de aceite
                'resources/js/cambio-aceite/create.js',
                'resources/js/cambio-aceite/edit.js',
                'resources/js/cambio-aceite/confirmar.js',
                // Productos
                'resources/js/productos/index.js',
                'resources/js/productos/create.js',
                'resources/js/productos/edit.js',
                // Vehiculos
                'resources/js/vehiculos/create.js',
                // Clientes
                'resources/js/clientes/validate.js',
                // Categorias
                'resources/js/categorias/validate.js',
                // Marcas
                'resources/js/marcas/validate.js',
                // Automotores
                'resources/js/automotores/validate.js',
                // Users
                'resources/js/users/toggle.js',
                'resources/js/users/validate.js',
                // Roles
                'resources/js/roles/validate.js',
                // Trabajadores
                'resources/js/trabajadores/index.js',
                'resources/js/trabajadores/create.js',
                'resources/js/trabajadores/edit.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    test: {
        // Entorno Node puro — sin DOM — para funciones puras de cálculo
        environment: 'node',
        include: ['tests/js/**/*.test.js'],
        reporters: ['verbose'],
    },
});

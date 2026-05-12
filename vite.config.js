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
                // Ingresos
                'resources/js/ingresos/create.js',
                'resources/js/ingresos/edit.js',
                'resources/js/ingresos/confirmar.js',
                // Cambio de aceite
                'resources/js/cambio-aceite/create.js',
                'resources/js/cambio-aceite/edit.js',
                'resources/js/cambio-aceite/confirmar.js',
                // Productos
                'resources/js/productos/index.js',
                'resources/js/productos/create.js',
                'resources/js/productos/edit.js',
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

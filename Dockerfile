# 1. Usar imagen oficial de PHP con Apache
FROM php:8.2-apache

# 2. Configurar variables de entorno por defecto
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    PORT=80 \
    APACHE_DOCUMENT_ROOT=/var/www/html/public

# 3. Instalar dependencias del sistema y extensiones de PHP necesarias
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    nodejs \
    npm \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pdo pdo_pgsql pgsql zip bcmath opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# 4. Habilitar mod_rewrite de Apache (Requerido para Laravel)
RUN a2enmod rewrite

# 5. Configurar Apache para Render (Puerto dinámico)
COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf
RUN sed -i 's/Listen 80/Listen ${PORT}/g' /etc/apache2/ports.conf

# 6. Instalar Composer globalmente
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 7. Establecer el directorio de trabajo
WORKDIR /var/www/html

# 8. Copiar archivos de dependencias primero para optimizar caché de Docker
COPY composer.json composer.lock* package.json package-lock.json* ./

# 9. Instalar dependencias de PHP y Node (Sin dev packages en PHP)
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist
RUN npm install

# 10. Copiar todo el código de la aplicación
COPY . .

# 11. Generar Autoload de Composer
RUN composer dump-autoload --optimize

# 12. Compilar los assets del frontend (Vite / Tailwind)
RUN npm run build

# 13. Asignar permisos correctos a las carpetas sensibles
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 14. Dar permisos de ejecución al script de arranque
RUN chmod +x /var/www/html/start.sh

# 15. Exponer el puerto
EXPOSE ${PORT}

# 16. Iniciar el contenedor usando el script start.sh
CMD ["/var/www/html/start.sh"]

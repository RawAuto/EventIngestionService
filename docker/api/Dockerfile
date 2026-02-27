FROM php:8.3-fpm-alpine

# Install nginx, SQLite and development headers
RUN apk add --no-cache nginx sqlite sqlite-dev

# Install PHP SQLite extension
RUN docker-php-ext-install pdo pdo_sqlite

# Remove dev packages to reduce image size
RUN apk del sqlite-dev

# Copy PHP configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Copy nginx configuration
COPY docker/api/nginx.conf /etc/nginx/nginx.conf

# Create app directory
WORKDIR /app

# Copy application files
COPY composer.json ./
COPY public/ ./public/
COPY src/ ./src/
COPY bin/ ./bin/

# Create data directory
RUN mkdir -p /app/data && chown -R www-data:www-data /app/data

# Create nginx pid directory
RUN mkdir -p /run/nginx

# Expose port 80
EXPOSE 80

# Start nginx and php-fpm
CMD php-fpm -D && nginx -g "daemon off;"


FROM php:8.3-cli-alpine

# Install SQLite and development headers
RUN apk add --no-cache sqlite sqlite-dev

# Install PHP SQLite extension
RUN docker-php-ext-install pdo pdo_sqlite

# Remove dev packages to reduce image size
RUN apk del sqlite-dev

# Copy PHP configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Create app directory
WORKDIR /app

# Copy application files
COPY composer.json ./
COPY public/ ./public/
COPY src/ ./src/
COPY bin/ ./bin/

# Create data directory
RUN mkdir -p /app/data

# Run worker
CMD ["php", "bin/worker.php"]


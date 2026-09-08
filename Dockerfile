FROM dunglas/frankenphp:latest-php8.3-alpine

# Set working directory
WORKDIR /app

# Install curl for healthcheck
RUN apk add --no-cache curl

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy dependency definition files first for efficient caching
COPY composer.json composer.lock* ./

# Install production dependencies
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Copy application files
COPY . .

# Copy Caddyfile for custom routing
COPY Caddyfile /etc/caddy/Caddyfile

# Default server name (port 80 HTTP)
ENV SERVER_NAME=:80

# Expose port
EXPOSE 80

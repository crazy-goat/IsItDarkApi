# Multi-stage build for IsItDarkApi
# Stage 1: Build environment
FROM php:8.2-cli-alpine AS builder

WORKDIR /app

# Install dependencies
RUN apk add --no-cache \
    git \
    unzip \
    curl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy composer files
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy application code
COPY . .

# Generate cities and map (optional - can be done at runtime)
# RUN php tools/map-generator.php

# Stage 2: Production image with static PHP
FROM scratch

# Copy static PHP binary (built separately via static-php-cli)
# This assumes php binary is available at /php in build context
COPY --from=builder /usr/local/bin/php /php

# Copy application
WORKDIR /app
COPY --from=builder /app .

# Create non-root user
USER 1000:1000

# Expose port
EXPOSE 8787

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD ["/php", "-r", "exit(file_get_contents('http://localhost:8787/') ? 0 : 1);"]

# Start the application
CMD ["/php", "start.php", "start"]

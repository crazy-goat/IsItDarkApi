FROM php:8.4-cli-alpine

WORKDIR /app

# Install dependencies
RUN apk add --no-cache \
    git \
    unzip \
    curl \
    linux-headers \
    $PHPIZE_DEPS

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy composer files
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy application code
COPY . .

# Generate map
RUN php tools/map-generator.php || true

# Create non-root user
RUN adduser -D -u 1000 appuser
USER 1000:1000

# Expose port
EXPOSE 8787

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD php -r "exit(file_get_contents('http://localhost:8787/') ? 0 : 1);" || exit 1

# Start the application
CMD ["php", "start.php", "start"]

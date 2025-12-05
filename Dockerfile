FROM wordpress:php8.2-apache

# Install additional PHP extensions for the chatbot
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite for WordPress permalinks
RUN a2enmod rewrite

# Copy WordPress files
COPY --chown=www-data:www-data . /var/www/html/

# Use sample config as wp-config.php (will use env vars)
RUN cp /var/www/html/wp-config-sample-render.php /var/www/html/wp-config.php

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]

FROM php:8.2-apache

# Enable mod_rewrite for clean URLs
RUN a2enmod rewrite

# Install system dependencies + SQLite support
RUN apt-get update && apt-get install -y --no-install-recommends \
    libsqlite3-dev \
    libzip-dev \
    zip \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

# Copy Apache config
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Set document root
ENV APACHE_DOCUMENT_ROOT /var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . /var/www/html/

# Create data and uploads dirs with proper permissions
RUN mkdir -p /var/www/html/data /var/www/html/uploads && \
    chown -R www-data:www-data /var/www/html/data /var/www/html/uploads && \
    chmod -R 755 /var/www/html/data /var/www/html/uploads

# PHP upload limits for game archives (100MB)
RUN echo "post_max_size = 110M" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "upload_max_filesize = 100M" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "max_input_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini

# Expose port
EXPOSE 80

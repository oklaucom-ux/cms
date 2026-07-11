FROM php:8.2-apache

# Install required PHP extensions for the CMS
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache modules for performance and security
RUN a2enmod rewrite deflate headers expires

# Set the working directory
WORKDIR /var/www/html

# Copy the CMS files to the container
COPY . /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose port 80
EXPOSE 80

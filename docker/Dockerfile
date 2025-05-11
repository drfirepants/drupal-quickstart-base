FROM php:8.2-apache

# 1) Install system packages, PHP extensions & Xdebug
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libzip-dev \
    default-mysql-client \
    git \
    unzip \
  && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j$(nproc) gd opcache pdo_mysql zip

# Install Xdebug
RUN pecl install xdebug \
  && docker-php-ext-enable xdebug

# Copy Xdebug configuration
COPY docker/xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Optional: Create Xdebug log directory if logging is enabled in xdebug.ini
# RUN mkdir -p /var/log/xdebug && chown www-data:www-data /var/log/xdebug

# 2) Enable Apache mod_rewrite
RUN a2enmod rewrite
COPY docker/apache-config.conf /etc/apache2/sites-available/000-default.conf

# 3) Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# 4) Set the working directory to /var/www/html
WORKDIR /var/www/html

# 5) Copy in your composer.json (and patches.json, etc.)
COPY composer.json .
COPY composer.lock ./
COPY patches.json ./

# 6) Install Drupal (core, contrib, etc.)
RUN composer install --no-interaction --prefer-dist

# 7) Install Drush **in the project**, not globally
RUN composer require drush/drush:12.x --dev
RUN ln -s /var/www/html/vendor/bin/drush /usr/local/bin/drush

# 8) Put Drush on the PATH
ENV PATH="/var/www/html/vendor/bin:${PATH}"

# 9) Create needed directories, fix permissions
RUN mkdir -p web/sites/default/files \
    web/modules/custom \
    web/themes/custom \
  && chown -R www-data:www-data /var/www/html

# 10) Copy your custom entrypoint
COPY docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]

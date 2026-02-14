# 1. Utiliser PHP avec Apache intégré
FROM php:8.2-apache

# 2. Installer les extensions PHP et les outils système
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite

# 3. Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. Définir le répertoire de travail
WORKDIR /var/www/html

# 5. Copier les fichiers du projet
COPY . /var/www/html/

# 6. Installer les dépendances PHP (si composer.json existe)
RUN if [ -f composer.json ]; then composer install --no-dev --optimize-autoloader; fi

# 7. Permissions
RUN chown -R www-data:www-data /var/www/html

# 8. Exposer le port 80
EXPOSE 80
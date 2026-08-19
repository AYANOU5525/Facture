# 1. Utiliser PHP avec Apache intégré
FROM php:8.2-apache

# 2. Installer les extensions PHP et les outils système nécessaires
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# 3. Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. Définir le répertoire de travail
WORKDIR /var/www/html

# 5. Copier les fichiers du projet dans le conteneur
COPY . /var/www/html/

# 6. Installer les dépendances PHP si composer.json est présent
RUN if [ -f composer.json ]; then composer install --no-dev --optimize-autoloader; fi

# 7. Donner les permissions appropriées à Apache
RUN chown -R www-data:www-data /var/www/html

# 8. Exposer le port 80 pour le serveur web
EXPOSE 80
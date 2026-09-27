# 1. Utiliser PHP avec Apache intégré
FROM php:8.2-apache

# 2. Installer les extensions PHP et les outils système nécessaires
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    openssl \
    default-mysql-client \
    && docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite ssl \
    && rm -rf /var/lib/apt/lists/*

# 2bis. HTTPS local — certificat auto-signé (nécessaire côté téléphone pour que le navigateur
# autorise la caméra du scanner mobile : getUserMedia exige un contexte sécurisé, refusé sur une
# IP LAN en simple HTTP). Avertissement navigateur normal et attendu pour un certificat
# auto-signé — "Avancé" > "Continuer" une fois suffit. Pour un vrai certificat reconnu, préférer
# un reverse-proxy (ex. Caddy) ou Let's Encrypt en production.
RUN mkdir -p /etc/ssl/factupro && \
    openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
        -keyout /etc/ssl/factupro/factupro-selfsigned.key \
        -out /etc/ssl/factupro/factupro-selfsigned.crt \
        -subj "/CN=localhost" \
        -addext "subjectAltName=DNS:localhost,IP:127.0.0.1"
COPY docker/apache-ssl.conf /etc/apache2/sites-available/000-default-ssl.conf
RUN a2ensite 000-default-ssl

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

# 8. Exposer les ports HTTP et HTTPS du serveur web
EXPOSE 80 443
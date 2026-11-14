# Environnement de DÉMONSTRATION/RECETTE (jamais pour la production). Fournisseur SMS simulé, données fictives.
FROM php:8.3-cli-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends git unzip curl ca-certificates libpq-dev libicu-dev libzip-dev libpng-dev libjpeg-dev \
 && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - && apt-get install -y --no-install-recommends nodejs \
 && docker-php-ext-configure gd --with-jpeg && docker-php-ext-install -j"$(nproc)" pdo_pgsql intl zip gd bcmath pcntl \
 && pecl install redis && docker-php-ext-enable redis \
 && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY . /app
RUN composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader \
 && npm install --no-audit --no-fund && npm run build && rm -rf node_modules
# Robuste sous Windows : retire d'éventuelles fins de ligne CRLF du script, puis le lance avec bash.
RUN sed -i 's/\r$//' docker/demo/entrypoint.sh
EXPOSE 8000
ENTRYPOINT ["bash", "docker/demo/entrypoint.sh"]

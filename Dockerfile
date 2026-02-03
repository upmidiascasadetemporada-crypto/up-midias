# PHP + Apache
FROM php:8.2-apache

# Ativa mod_rewrite
RUN a2enmod rewrite

# Atualiza pacotes e instala dependências do SQLite
RUN apt-get update \
    && apt-get install -y sqlite3 libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Copia o projeto para o Apache
COPY . /var/www/html/

# Permissões para uploads e SQLite
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html

# Porta exigida pelo Render
ENV PORT=10000
EXPOSE 10000

# Apache escutando na porta correta
RUN sed -i 's/80/${PORT}/g' /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

# Start
CMD ["apache2-foreground"]

# Usa PHP com Apache
FROM php:8.2-apache

# Ativa mod_rewrite (URLs amigáveis)
RUN a2enmod rewrite

# Instala extensões necessárias
RUN docker-php-ext-install pdo pdo_sqlite

# Copia o projeto para o Apache
COPY . /var/www/html/

# Permissões para uploads e banco SQLite
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html

# Apache escuta na porta do Render
ENV PORT=10000
EXPOSE 10000

# Ajusta Apache para usar a porta do Render
RUN sed -i 's/80/${PORT}/g' /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

# Inicia Apache
CMD ["apache2-foreground"]

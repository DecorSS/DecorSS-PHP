# PHP 8.2 Apache imajını kullan
FROM php:8.2-apache

# Gerekirse MySQL eklentisi kur
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Proje dosyalarını Apache'nin root'una kopyala
COPY . /var/www/html/

# Apache'nin document root'u (index.php kökteyse sorun yok)
WORKDIR /var/www/html/

# Render $PORT environment variable atıyor, Apache'yi ona yönlendirmemiz lazım
RUN sed -i "s/80/\${PORT}/g" /etc/apache2/sites-available/000-default.conf

EXPOSE 10000
CMD ["apache2-foreground"]

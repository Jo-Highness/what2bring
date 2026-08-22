FROM php:8.3-apache

# pdo_sqlite is bundled in the official PHP image. Enable rewrite+headers for .htaccess.
RUN a2enmod rewrite headers

# Point Apache at the public/ document root and allow .htaccess overrides.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
 && printf '<Directory /var/www/html/public>\n  AllowOverride All\n  Require all granted\n</Directory>\n' \
      > /etc/apache2/conf-available/z-fragmichnicht.conf \
 && a2enconf z-fragmichnicht

# App code (config.php + data/ are provided via bind mounts at runtime).
COPY . /var/www/html
RUN mkdir -p /var/www/html/data && chown -R www-data:www-data /var/www/html/data

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
  CMD php -r '$c=@file_get_contents("http://127.0.0.1/index.php?r=admin.login");exit($c!==false?0:1);' || exit 1

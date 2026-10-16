# What2Bring — production image: Apache + PHP, runs as www-data on port 8080,
# compatible with a read-only root filesystem. State lives in the /data volume.
FROM php:8.5-apache

# Apache: non-root friendly (port 8080, runtime dirs under /tmp), docroot = public/,
# no server signature, no directory listings.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public \
    APACHE_RUN_DIR=/tmp/apache2/run \
    APACHE_LOCK_DIR=/tmp/apache2/lock \
    APACHE_PID_FILE=/tmp/apache2/run/apache2.pid \
    W2B_DATA_DIR=/data \
    W2B_CONFIG=/data/config.php \
    W2B_DB_FILE=/data/what2bring.sqlite

# ${APACHE_DOCUMENT_ROOT} is meant literally: Apache expands it at runtime.
# hadolint ignore=SC2016
RUN a2enmod headers \
 && sed -ri 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf \
 && sed -ri -e 's/<VirtualHost \*:80>/<VirtualHost *:8080>/' \
            -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf \
 && { \
      echo 'ServerName localhost'; \
      echo 'ServerTokens Prod'; \
      echo 'ServerSignature Off'; \
      echo 'TraceEnable Off'; \
      echo '<Directory /var/www/html/public>'; \
      echo '  Options -Indexes'; \
      echo '  AllowOverride All'; \
      echo '  Require all granted'; \
      echo '</Directory>'; \
    } > /etc/apache2/conf-available/z-what2bring.conf \
 && a2enconf z-what2bring \
 && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && { \
      echo 'expose_php = Off'; \
      echo 'session.save_path = /tmp'; \
      echo 'upload_max_filesize = 1M'; \
      echo 'post_max_size = 2M'; \
    } > "$PHP_INI_DIR/conf.d/zz-what2bring.ini" \
 && mkdir -p /data \
 && chown www-data:www-data /data \
 && chmod 0770 /data

# App code is owned by root and read-only for the web server user.
COPY --chown=root:root . /var/www/html
COPY --chmod=0755 docker/entrypoint.sh /usr/local/bin/w2b-entrypoint

# www-data (uid/gid 33 in the Debian-based php image); numeric so hosts can resolve it.
USER 33:33
EXPOSE 8080
VOLUME ["/data"]

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
  CMD ["php", "-r", "exit(trim((string) @file_get_contents('http://127.0.0.1:8080/index.php?r=health')) === 'ok' ? 0 : 1);"]

ENTRYPOINT ["w2b-entrypoint"]
CMD ["apache2-foreground"]

# Imagem de produção: código e dependências dentro da imagem, sem pacotes de desenvolvimento.
# FrankenPHP = servidor web (Caddy) + PHP num processo só. Escuta em HTTP na 8080;
# o HTTPS fica com o proxy reverso do servidor.

FROM composer:2 AS dependencias
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

FROM dunglas/frankenphp:1-php8.3

RUN install-php-extensions pdo_pgsql zip opcache

# OPcache de produção: o código não muda dentro do container.
RUN { \
      echo 'opcache.enable=1'; \
      echo 'opcache.enable_cli=1'; \
      echo 'opcache.validate_timestamps=0'; \
      echo 'opcache.memory_consumption=128'; \
    } > /usr/local/etc/php/conf.d/zz-radar.ini \
    && cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

ENV SERVER_NAME=:8080

WORKDIR /app
COPY --from=dependencias /app /app
COPY docker/producao/iniciar.sh /usr/local/bin/iniciar

# Roda como www-data, não como root.
RUN php artisan package:discover --ansi \
    && chmod +x /usr/local/bin/iniciar \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache /data /config

USER www-data
EXPOSE 8080

ENTRYPOINT ["iniciar"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

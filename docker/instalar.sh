#!/bin/sh
# Instala o Laravel por baixo do código do Radar e prepara o banco.
# Rode com: docker compose run --rm app sh docker/instalar.sh
set -e
cd /app

if [ ! -f artisan ]; then
    echo "→ Baixando o esqueleto do Laravel 12..."
    rm -rf /tmp/esqueleto
    composer create-project laravel/laravel:^12.0 /tmp/esqueleto --no-install --no-scripts --prefer-dist -n

    echo "→ Copiando arquivos do Laravel (sem sobrescrever os do Radar)..."
    cd /tmp/esqueleto
    find . -type f | while read -r arquivo; do
        if [ ! -e "/app/$arquivo" ]; then
            mkdir -p "/app/$(dirname "$arquivo")"
            cp "$arquivo" "/app/$arquivo"
        fi
    done
    cd /app
fi

if [ ! -f vendor/autoload.php ]; then
    echo "→ Instalando dependências..."
    composer install -n --prefer-dist
fi

[ -f .env ] || cp .env.example .env
grep -q '^APP_KEY=base64' .env || php artisan key:generate --force

echo "→ Criando tabelas..."
php artisan migrate --force

echo ""
echo "Pronto! Agora rode:"
echo "  docker compose up -d"
echo "  docker compose exec app php artisan vagas:coletar"
echo "E abra http://localhost:8000"

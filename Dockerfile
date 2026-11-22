# お試し用のデモを Render の無料プランで動かすための箱（D-048・docs/demo.md）
# 手元の開発は今までどおり Sail（compose.yaml）を使う。この Dockerfile は Render だけが使う

# 1. 画面を組み立てる（Vite）。できた public/build だけを次の箱に持っていく
FROM node:24-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js tsconfig.json ./
COPY resources ./resources
RUN npm run build

# 2. アプリを動かす箱（PHP 8.5。SQLite を使うための pdo_sqlite は、はじめから入っている）
FROM php:8.5-cli
WORKDIR /var/www/html

# composer がパッケージを広げるのに unzip を使う
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 本番用の部品だけを入れる（faker は、起動のたびにダミーデータを入れるので require に入れてある）
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader

COPY . .
COPY --from=assets /app/public/build ./public/build
RUN composer dump-autoload --no-dev --optimize \
    && php artisan package:discover --ansi \
    && chmod +x docker/start.sh

# Render は、PORT の環境変数で待ち受ける番号を渡す
ENV PORT=10000
EXPOSE 10000

CMD ["docker/start.sh"]

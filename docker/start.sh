#!/bin/sh
# お試し用のデモ（Render）を起動する（D-048・docs/demo.md）。
# Render の無料プランは、眠って起きるとディスクの中身が消えるので、起動のたびにデータベースを作り直してダミーデータを入れる
set -e

DB_FILE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"

# 1. SQLite のファイルを作り直す（WAL の書きかけのファイルも消す）
rm -f "$DB_FILE" "$DB_FILE-wal" "$DB_FILE-shm"
mkdir -p "$(dirname "$DB_FILE")"
touch "$DB_FILE"

# 2. 設定と画面のひな形をキャッシュして、速く動くようにする
php artisan config:cache
php artisan view:cache

# 3. 表を作って、ダミーデータを入れる
php artisan migrate --force --seed

# 4. サーバーを動かす（exec で、Render からの止める合図がサーバーに届くようにする）
exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"

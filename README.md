# タスクマネージャー（task-manager）

[![CI](https://github.com/kazuyuki-a-dev/task-manager/actions/workflows/ci.yml/badge.svg)](https://github.com/kazuyuki-a-dev/task-manager/actions/workflows/ci.yml)

約20人の製造業の職場で、「だれが何をやっているか」と「締め切り」がひと目で分かり、遅れる前に気づけるようにするタスク管理アプリです。今は Excel で管理している製造の指示を置きかえることを目指しています。

> 開発を始めたところです。まだ機能はありません。

## 動かし方

Docker と PHP 8.3 以上、Composer が必要です。

```sh
git clone https://github.com/kazuyuki-a-dev/task-manager.git
cd task-manager
composer install
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
```

http://localhost を開くと画面が出ます。

テスト：`./vendor/bin/sail test`

## 資料

| 資料 | 中身 |
|---|---|
| [要件定義書](docs/requirements.md) | 目的、優先度、完成の境界、要件の対応表 |
| [決めたことの記録](docs/decisions.md) | 決めたことと、その理由 |

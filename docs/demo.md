# お試し用のデモ（Render の無料プラン）

| 項目 | 内容 |
|---|---|
| 版 | 0.2 |
| 作成日 | 2026-11-22 |
| 関係する Issue | #49 |
| 決めたこと | D-048〜D-057（[docs/decisions.md](decisions.md)） |

## 改訂履歴

| 版 | 日付 | 内容 |
|---|---|---|
| 0.1 | 2026-11-22 | 最初の版。Render の設定の手順、環境変数の一覧、確かめ方、戻し方を書いた。手元で Render と同じ環境変数の箱を動かして確かめた。Render への公開は、まだしていない |
| 0.2 | 2026-11-22 | Render に公開した。URL を書き、外から開いて確かめた記録を足した |

---

## 1. このデモは何か

URL：https://task-manager-6877.onrender.com

- だれでも触れる、**ダミーデータだけ**のお試し用です。会社で使う本番ではありません（本番は D-005 のとおり、社内だけで動かします）
- Render の無料の Web サービスで動かします。無料のため、次のことが起きます。これを**デモの仕様**とします
  - 15分アクセスがないと眠ります。次に開くと、起きるまで1分ほどかかります
  - 起きたときと、新しくデプロイ（公開しなおすこと）したときに、データベースを作り直して、ダミーデータを入れなおします。**触った内容は元に戻ります**
- デモのときだけ、次のように動きます（環境変数 `DEMO_MODE=true` で切りかえ）

| 動き | 理由 |
|---|---|
| 全部の画面の上に「お試し用のデモです…」の帯を出す | 本物のデータと思われないように。触った内容が戻ることを知らせるため |
| 管理者のログインの画面に「ダミー管理者／パスワード password」を出す | 初めての人が、登録しなくても管理者の画面を試せるように |
| ダミー管理者の名前・パスワード・役割を変えられず、消せない | だれかが変えると、次の人が管理者の画面に入れなくなるため |
| バックアップを止める。管理者の画面には「デモでは止めています」と出す | データは起動のたびに作り直すので、とっておく意味がないため |
| https の入口（Render）の後ろで動く設定にする | Render は https で受けて、箱には http で渡すため |

## 2. Render の環境変数

Render の画面の「Environment」に入れます。**APP_KEY は秘密の値**なので、Render にだけ入れ、Git・Issue・会話には書きません。

| 名前 | 値 | 説明 |
|---|---|---|
| `APP_NAME` | `タスクマネージャー` | 画面のタブに出る名前 |
| `APP_ENV` | `production` | 本番の動き（くわしいエラーを画面に出さない） |
| `APP_DEBUG` | `false` | エラーのくわしい中身を画面に出さない |
| `APP_KEY` | （手元で作った値） | 作り方は「3. 設定の手順」の 3-1 |
| `APP_URL` | `https://（Render の URL）` | Render がくれる `https://〜.onrender.com` |
| `LOG_CHANNEL` | `stderr` | 記録を Render の「Logs」に出す |
| `DB_CONNECTION` | `sqlite` | データベースは SQLite（1つのファイル） |
| `DB_DATABASE` | `/var/www/html/database/database.sqlite` | 箱の中の、データベースのファイルの場所 |
| `DB_BUSY_TIMEOUT` | `5000` | ほかの人が書いているあいだ、5秒まで待つ |
| `DB_JOURNAL_MODE` | `wal` | 読むのと書くのを同時にできるようにする |
| `SESSION_DRIVER` | `database` | ログインの状態をデータベースに残す |
| `CACHE_STORE` | `database` | 一時的なデータをデータベースに残す |
| `QUEUE_CONNECTION` | `sync` | あとで動かす仕事を、その場で動かす |
| `DEMO_MODE` | `true` | デモの動きにする（1. の表） |

`PORT` は Render が自動で入れるので、入れなくてよいです。

## 3. 設定の手順（はじめて公開するとき）

### 3-1. APP_KEY を作る（手元で）

打つもの：

```sh
./vendor/bin/sail artisan key:generate --show
```

`base64:` で始まる文字が出ます。これをコピーして、3-3 で Render に貼ります。**ほかの場所には貼りません**。

### 3-2. Render で Web サービスを作る

1. Render にログインし、「New」→「Web Service」を押す
2. GitHub の `task-manager` のリポジトリを選ぶ
3. 次のように選ぶ

| 項目 | 選ぶもの |
|---|---|
| Branch | `main` |
| Runtime（Language） | `Docker`（リポジトリの `Dockerfile` を使う） |
| Instance Type | `Free` |
| Health Check Path | `/up` |

Name は `task-manager` にしたが、URL には Render が後ろに `-6877` を付けた（`https://task-manager-6877.onrender.com`）。`APP_URL` は、作ったあとに出た URL に直す。

### 3-3. 環境変数を入れる

「2. Render の環境変数」の表を、上から1つずつ入れます。`APP_URL` は、作ったあとに出る URL に直します。

### 3-4. デプロイする

「Create Web Service」を押します。「Logs」に次の文字が出たら成功です。

```
INFO  Seeding database.
INFO  Server running on [http://0.0.0.0:10000].
```

起動のたびに、`docker/start.sh` が次の順で動きます。

1. SQLite のファイルを作り直す
2. 設定と画面のひな形をキャッシュする
3. 表を作り、ダミーデータを入れる（`migrate --force --seed`）
4. サーバーを動かす

## 4. 確かめ方

公開したあと（またはデプロイしなおしたあと）に、上から順に確かめ、結果を下の「確かめた記録」に残します。

| # | 確かめること | こうなればよい |
|---|---|---|
| 1 | どの画面も、いちばん上に帯がある | ボード・完了の履歴・会社カレンダー・管理者の画面のすべて |
| 2 | ボードで、指示を押して小さな画面を開く | 始める・完了にする・担当を足すができる |
| 3 | 完了の履歴で、品名の一部で探す | しぼられる。`%` を打っても全部は出ない |
| 4 | 会社カレンダーを開く | 休み・行事・休む人が出る |
| 5 | 管理者のログインの画面 | 「ダミー管理者／パスワード password」が出て、その名前とパスワードで入れる |
| 6 | 管理者の画面 → 社員 → ダミー管理者を消す | 「お試し用のデモなので…」と出て、消えない |
| 7 | 管理者の画面のいちばん上 | 「バックアップ：お試し用のデモでは止めています」と出る |
| 8 | 何か触ってから、15分以上あけて開く | 触った内容が元に戻っている |
| 9 | スマホの幅で開く | 帯が折り返して、画面がこわれない |

### 確かめた記録

| 確認日 | 確かめたこと | 方法 | 結果 | 根拠 |
|---|---|---|---|---|
| 2026-11-22 | 箱を作って起動できる・帯・ログインの入り方・バックアップの表示・ダミー管理者を守る・WAL と待ち時間・ダミーデータが入る | 手元で `docker build` し、2. と同じ環境変数で `docker run` した。画面は curl で開いた | 期待どおり | PR #50 |
| 2026-11-22 | 公開した URL が動いている・帯が出る・画面の部品が https の URL から読まれる・管理者のログインの画面が開ける | 手元から curl で `/up`・`/`・`/admin/login` を開いた | `/up` と `/admin/login` が 200。帯が出た。js・css は `https://task-manager-6877.onrender.com/build/` から読まれていた | https://task-manager-6877.onrender.com |
| （ブラウザで確かめたら書く） | 4. の 1〜9 | Render の URL をブラウザで開く | | |

## 5. うまくいかないとき・戻し方

| こまったこと | やること |
|---|---|
| デプロイしたら画面が開かない | Render の「Logs」でエラーを見る。直すまでは、Render の「Events」から前のデプロイを選んで「Rollback」を押すと、前の版に戻る |
| 画面が `500` になる | 「Logs」を見る。多いのは `APP_KEY` の入れわすれ・入れまちがい |
| 画面が崩れる（js・css が読めない） | `APP_URL` が `https://` で始まっているか確かめる |
| ダミーデータを、今すぐ元に戻したい | Render の「Manual Deploy」→「Deploy latest commit」を押す（起動しなおすと作り直される） |
| デモを止めたい | Render の「Settings」→「Suspend Web Service」を押す。消すときは「Delete Web Service」 |

## 6. 手元でデモと同じ形で動かす

手元の Sail（MySQL）はそのままで、`.env` を変えずに試せます。

```sh
docker build -t task-manager-demo .
docker run --rm -p 18080:10000 \
  -e APP_ENV=production -e APP_DEBUG=false -e APP_KEY="（3-1 で作った値）" \
  -e APP_URL=http://localhost:18080 -e LOG_CHANNEL=stderr \
  -e DB_CONNECTION=sqlite -e DB_DATABASE=/var/www/html/database/database.sqlite \
  -e DB_BUSY_TIMEOUT=5000 -e DB_JOURNAL_MODE=wal \
  -e SESSION_DRIVER=database -e CACHE_STORE=database -e QUEUE_CONNECTION=sync \
  -e DEMO_MODE=true task-manager-demo
```

http://localhost:18080 で開けます。デモでは https にする設定がはたらくので、手元ではログインしたあとの移動先が `https://` になり開けません。手元では画面を見るところまでにします。

## 7. MySQL（手元・会社の本番）とのちがい

| ちがい | デモ（SQLite） | 手元・会社の本番（MySQL） |
|---|---|---|
| 検索の、全角と半角・ひらがなとカタカナ | 区別する（例：「ｼｬﾌﾄ」で「シャフト」は出ない） | 区別しない |
| 品番の大文字と小文字 | 区別しない（マイグレーションで合わせた） | 区別しない |
| バックアップ | 止めている | 毎日とる（[バックアップと復元の手順書](backup-and-recovery.md)） |

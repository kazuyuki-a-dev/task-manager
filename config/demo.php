<?php

/*
 * お試し用のデモ（Render の無料プラン・D-048）。docs/demo.md
 * 会社で使う本番と手元の開発では、DEMO_MODE を付けない（false のまま）
 */
return [

    // true のとき：全部の画面の上に帯を出す・ログインの画面に入り方を出す・ダミー管理者を守る・バックアップを止める
    'enabled' => (bool) env('DEMO_MODE', false),

    // ログインの画面に出し、直したり消したりできないように守る管理者（DatabaseSeeder が作る）
    'admin_name' => 'ダミー管理者',

    // ダミー管理者のパスワード（UserFactory の admin() と同じ）。ダミーデータだけのデモなので、画面に出してよい
    'admin_password' => 'password',

];

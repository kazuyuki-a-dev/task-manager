<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * MySQL のときだけ流すテスト（mysqldump を使うバックアップなど）。
     * CI では SQLite（お試し用のデモ）でも流すが（D-052）、デモではバックアップを止めているので、SQLite では飛ばす。
     * setUp の中では parent::setUp() より前に呼ぶ（あとで飛ばすと、データベースの片づけが動かず、ほかのテストがこわれるため）。
     * そのときはまだ Laravel が動いていないので、環境変数で見分ける（付けていなければ .env の mysql）
     */
    protected function skipUnlessMysql(): void
    {
        if ((getenv('DB_CONNECTION') ?: 'mysql') !== 'mysql') {
            $this->markTestSkipped('MySQL のときだけ流すテスト');
        }
    }
}

<?php

namespace App\Providers;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // お試し用のデモ（Render）だけの設定（D-057）。会社の本番（社内だけ・D-005）は http のこともあるので、デモのときだけ
        if (config('demo.enabled')) {
            // Render の https の入口の後ろで動くので、入口が付けた「元は https」の印を信じる
            TrustProxies::at('*');
            // 画面のリンクや、画面の部品（js・css）の場所を https で作る
            URL::forceHttps();
        }
    }
}

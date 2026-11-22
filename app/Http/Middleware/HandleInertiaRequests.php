<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            // パスワードを入れた管理者の名前。入っていないか、10分たって終わっていれば null（R-06・D-011）
            'admin' => fn () => EnsureAdminSession::isActive($request) ? $request->user()->only(['id', 'name']) : null,
            // 画面の上に一度だけ出すお知らせ（10分で管理者の操作を終えたときなど）
            'notice' => fn () => $request->session()->get('notice'),
            // お試し用のデモか（D-048）。ログインの画面の入り方や、バックアップの表示を切りかえる
            'demo' => (bool) config('demo.enabled'),
        ];
    }
}

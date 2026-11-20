<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * 管理者の画面の見張り（R-06・D-011）。
 * - 管理者のパスワードを入れていなければ、パスワードの画面へ
 * - 10分操作がなければ、管理者の操作を終えて、パスワードの画面へ
 */
class EnsureAdminSession
{
    /**
     * 何分操作がなければ終えるか
     */
    public const IDLE_MINUTES = 10;

    private const SESSION_KEY = 'admin_last_activity';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->role !== UserRole::Admin || ! $user->is_active) {
            return redirect()->route('admin.login');
        }

        $last = $request->session()->get(self::SESSION_KEY);
        if ($last === null || now()->getTimestamp() - $last > self::IDLE_MINUTES * 60) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')
                ->with('notice', self::IDLE_MINUTES.'分操作がなかったので、管理者の操作を終えました。もう一度パスワードを入れてください。');
        }

        self::touch($request);

        return $next($request);
    }

    /**
     * 「最後に操作した時刻」を今にする
     */
    public static function touch(Request $request): void
    {
        $request->session()->put(self::SESSION_KEY, now()->getTimestamp());
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureAdminSession;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 管理者のパスワード（R-06・D-004・D-011）
 */
class LoginController extends Controller
{
    /**
     * 1分のあいだに、何回までまちがえてよいか
     */
    public const MAX_ATTEMPTS = 5;

    public function show(): Response
    {
        return Inertia::render('admin/Login', [
            // 選べるのは、使える管理者だけ
            'admins' => User::query()
                ->where('role', UserRole::Admin)
                ->where('is_active', true)
                ->orderBy('id')
                ->get(['id', 'name'])
                ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
                ->all(),
            // お試し用のデモのときだけ、入り方を出す（D-048）。ダミーデータだけなので、パスワードを出してよい
            'demoLogin' => config('demo.enabled')
                ? ['name' => config('demo.admin_name'), 'password' => config('demo.admin_password')]
                : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
            'password' => ['required', 'string'],
        ], [
            'user_id.required' => '名前を選んでください。',
            'password.required' => 'パスワードを入れてください。',
        ]);

        // パスワードを当てずっぽうで何度も試されないように、まちがいの回数を端末ごとに数える
        $key = 'admin-login:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'password' => "何回もまちがえたので、{$seconds}秒たってから、もう一度入れてください。",
            ]);
        }

        // 使える管理者で、パスワードが合っているときだけ入れる。暗号にした値どうしで比べる
        $ok = Auth::attempt([
            'id' => $validated['user_id'],
            'password' => $validated['password'],
            'role' => UserRole::Admin->value,
            'is_active' => true,
        ]);

        if (! $ok) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages([
                'password' => '名前かパスワードがちがいます。',
            ]);
        }

        RateLimiter::clear($key);
        // 入る前のセッションの番号を使い回されないように、新しくする
        $request->session()->regenerate();
        EnsureAdminSession::touch($request);

        return redirect()->route('admin.home');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('board');
    }
}

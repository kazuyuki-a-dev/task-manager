<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 社員の登録（R-07・D-036）。管理者だけが使う
 */
class MemberController extends Controller
{
    /**
     * 管理者のパスワードの、いちばん短い長さ
     */
    public const PASSWORD_MIN = 8;

    public function index(Request $request): Response
    {
        return Inertia::render('admin/Members', [
            // 消した社員は出さない
            'members' => User::query()
                ->where('is_active', true)
                ->orderByRaw("role = 'admin' desc")
                ->orderBy('id')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role->value,
                    'isDummy' => $user->is_dummy,
                    // 自分自身は、消したり作業者にしたりできない
                    'isSelf' => $user->id === $request->user()->id,
                ])
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'role' => ['required', Rule::enum(UserRole::class)],
            // 管理者にするときだけ、パスワードを決める
            'password' => ['nullable', 'string', 'min:'.self::PASSWORD_MIN, 'max:255', 'required_if:role,admin'],
        ], $this->messages());

        $role = UserRole::from($validated['role']);
        User::create([
            'name' => $validated['name'],
            'role' => $role,
            // 作業者はパスワードを持たない（D-003）
            'password' => $role === UserRole::Admin ? $validated['password'] : null,
        ]);

        return back()->with('notice', "{$validated['name']}さんを{$role->label()}として登録しました。");
    }

    public function update(Request $request, User $member): RedirectResponse
    {
        $this->ensureNotDeleted($member);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'role' => ['required', Rule::enum(UserRole::class)],
            // 空なら今のパスワードのまま。作業者から管理者にするときは、決めないといけない
            'password' => [
                'nullable', 'string', 'min:'.self::PASSWORD_MIN, 'max:255',
                Rule::requiredIf(fn () => $request->input('role') === UserRole::Admin->value && $member->password === null),
            ],
        ], $this->messages());

        $role = UserRole::from($validated['role']);

        DB::transaction(function () use ($request, $member, $validated, $role) {
            if ($role === UserRole::Worker && $member->role === UserRole::Admin) {
                $this->ensureAdminRemains($request, $member, 'role', '作業者にする');
            }

            $member->name = $validated['name'];
            $member->role = $role;
            if ($role === UserRole::Worker) {
                // 作業者はパスワードを持たない
                $member->password = null;
            } elseif (($validated['password'] ?? null) !== null) {
                $member->password = $validated['password'];
            }
            $member->save();
        });

        return back()->with('notice', "{$member->name}さんを直しました。");
    }

    /**
     * 消す。データは残して印を付けるので、過去の指示と完了の履歴には名前が残る（D-036）
     */
    public function destroy(Request $request, User $member): RedirectResponse
    {
        $this->ensureNotDeleted($member);

        DB::transaction(function () use ($request, $member) {
            if ($member->role === UserRole::Admin) {
                $this->ensureAdminRemains($request, $member, 'member', '消す');
            }
            $member->update(['is_active' => false]);
        });

        return back()->with('notice', "{$member->name}さんを消しました。");
    }

    /**
     * 自分自身と、最後の1人の管理者は、消したり作業者にしたりできない。
     * 管理者の画面にだれも入れなくなるのを防ぐ
     */
    private function ensureAdminRemains(Request $request, User $member, string $field, string $action): void
    {
        if ($member->id === $request->user()->id) {
            throw ValidationException::withMessages([
                $field => "自分自身を{$action}ことはできません。ほかの管理者にたのんでください。",
            ]);
        }

        // 2人の管理者が同時にたがいを消して、0人になることがないように、管理者の行に鍵をかけて数える
        $admins = User::query()
            ->where('role', UserRole::Admin)
            ->where('is_active', true)
            ->lockForUpdate()
            ->count();

        if ($admins <= 1) {
            throw ValidationException::withMessages([
                $field => "管理者が1人だけなので、{$action}ことはできません。先にほかの管理者を足してください。",
            ]);
        }
    }

    /**
     * 消した社員は、直せない（画面にも出していない）
     */
    private function ensureNotDeleted(User $member): void
    {
        abort_unless($member->is_active, 404);
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'name.required' => '名前を入れてください。',
            'name.max' => '名前は50文字までにしてください。',
            'role.required' => '役割を選んでください。',
            'role.enum' => '役割は作業者か管理者を選んでください。',
            'password.required_if' => '管理者にするときは、パスワードを決めてください。',
            'password.required' => '管理者にするときは、パスワードを決めてください。',
            'password.min' => 'パスワードは'.self::PASSWORD_MIN.'文字以上にしてください。',
        ];
    }
}

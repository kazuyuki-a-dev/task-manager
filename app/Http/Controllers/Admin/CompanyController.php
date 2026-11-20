<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 取引会社（R-14・D-033）。管理者だけが使う
 */
class CompanyController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Companies', [
            'companies' => Company::query()
                ->withCount('products')
                ->orderBy('name')
                ->get()
                ->map(fn (Company $company) => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'productsCount' => $company->products_count,
                    'isDummy' => $company->is_dummy,
                ])
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Company::create($this->validated($request));

        return back()->with('notice', '取引会社を登録しました。');
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $company->update($this->validated($request, $company));

        return back()->with('notice', '取引会社を直しました。');
    }

    /**
     * 製品を1つも登録していない会社だけ消せる（D-033）
     */
    public function destroy(Company $company): RedirectResponse
    {
        if ($company->products()->exists()) {
            throw ValidationException::withMessages([
                'company' => "「{$company->name}」には製品が登録されているので、消せません。",
            ]);
        }

        $company->delete();

        return back()->with('notice', '取引会社を消しました。');
    }

    /**
     * @return array{name: string}
     */
    private function validated(Request $request, ?Company $company = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('companies', 'name')->ignore($company?->id)],
        ], [
            'name.required' => '会社名を入れてください。',
            'name.max' => '会社名は255文字までにしてください。',
            'name.unique' => 'その会社名は、もう登録されています。',
        ]);
    }
}

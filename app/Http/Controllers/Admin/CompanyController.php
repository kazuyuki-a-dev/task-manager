<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    /**
     * 会社を足す。品名も入れたときは、その会社の製品も1件いっしょに足す（D-034）
     */
    public function store(Request $request): RedirectResponse
    {
        $company = $this->validated($request);
        $product = $request->validate([
            'product_code' => ['nullable', 'string', 'max:50', Rule::unique('products', 'code')],
            // 品番だけ入れて品名が空のときは、品名のない製品になってしまうのではねる
            'product_name' => ['nullable', 'string', 'max:255', 'required_with:product_code'],
        ], [
            'product_code.unique' => 'その品番は、もう登録されています。',
            'product_code.max' => '品番は50文字までにしてください。',
            'product_name.required_with' => '品番を入れたときは、品名も入れてください。',
            'product_name.max' => '品名は255文字までにしてください。',
        ]);

        // 送られてこなかった欄は、空として扱う
        $productName = $product['product_name'] ?? null;
        $productCode = $product['product_code'] ?? null;

        // 会社と製品はまとめて登録する。途中で失敗したら、どちらも登録しない
        DB::transaction(function () use ($company, $productName, $productCode) {
            $created = Company::create($company);
            if ($productName !== null) {
                Product::create([
                    'company_id' => $created->id,
                    'code' => $productCode,
                    'name' => $productName,
                ]);
            }
        });

        return back()->with('notice', $productName !== null
            ? '取引会社と製品を登録しました。'
            : '取引会社を登録しました（製品は0件）。');
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

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Product;
use App\Support\Like;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 製品の一覧と検索（R-12・D-019）。管理者だけが使う
 */
class ProductController extends Controller
{
    /**
     * 1ページに出す件数
     */
    public const PER_PAGE = 50;

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        // 選んでいる取引会社。選んでいなければ、すべての会社の製品を出す
        $companyId = $request->integer('company') ?: null;

        $products = Product::query()
            ->with('company:id,name')
            ->withCount('orders')
            ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
            ->when($q !== '', function ($query) use ($q) {
                // 「%」や「_」を打っても、何にでも合う印として扱われないようにする（Like）
                $query->where(fn ($w) => $w
                    ->where(fn ($x) => Like::where($x, 'code', $q))
                    ->orWhere(fn ($x) => Like::where($x, 'name', $q)));
            })
            // 品番のあるものを先に、品番の順。品番のないものは最後に品名の順
            ->orderByRaw('code IS NULL')
            ->orderBy('code')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('admin/Products', [
            'q' => $q,
            'companyId' => $companyId,
            'companies' => Company::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Company $company) => ['id' => $company->id, 'name' => $company->name])
                ->all(),
            'products' => [
                'data' => $products->getCollection()->map(fn (Product $product) => [
                    'id' => $product->id,
                    'companyId' => $product->company_id,
                    'companyName' => $product->company->name,
                    'code' => $product->code,
                    'name' => $product->name,
                    'ordersCount' => $product->orders_count,
                    'isDummy' => $product->is_dummy,
                ])->all(),
                'total' => $products->total(),
                'currentPage' => $products->currentPage(),
                'lastPage' => $products->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Product::create($this->validated($request));

        return back()->with('notice', '製品を登録しました。');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $product->update($this->validated($request, $product));

        return back()->with('notice', '製品を直しました。');
    }

    /**
     * 指示に一度も使っていない製品だけ消せる（R-12）
     */
    public function destroy(Product $product): RedirectResponse
    {
        if ($product->orders()->exists()) {
            throw ValidationException::withMessages([
                'product' => "「{$product->name}」は指示に使ったことがあるので、消せません。",
            ]);
        }

        $product->delete();

        return back()->with('notice', '製品を消しました。');
    }

    /**
     * @return array{company_id: int, code: ?string, name: string}
     */
    private function validated(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            // 製品は必ずどこか1社の取引会社のもの（D-033）
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            // 空の品番は null になる（Laravel が空の文字を null に直す）。null どうしは重なってもよい
            'code' => ['nullable', 'string', 'max:50', Rule::unique('products', 'code')->ignore($product?->id)],
            'name' => ['required', 'string', 'max:255'],
        ], [
            'company_id.required' => '取引会社を選んでください。',
            'company_id.exists' => '選んだ取引会社が見つかりません。',
            'code.unique' => 'その品番は、もう登録されています。',
            'code.max' => '品番は50文字までにしてください。',
            'name.required' => '品名を入れてください。',
            'name.max' => '品名は255文字までにしてください。',
        ]);
    }
}

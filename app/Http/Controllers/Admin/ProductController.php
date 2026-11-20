<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
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

        $products = Product::query()
            ->withCount('orders')
            ->when($q !== '', function ($query) use ($q) {
                // 「%」や「_」を打っても、何にでも合う印として扱われないようにする
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(fn ($w) => $w->where('code', 'like', $like)->orWhere('name', 'like', $like));
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
            'products' => [
                'data' => $products->getCollection()->map(fn (Product $product) => [
                    'id' => $product->id,
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
     * @return array{code: ?string, name: string}
     */
    private function validated(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            // 空の品番は null になる（Laravel が空の文字を null に直す）。null どうしは重なってもよい
            'code' => ['nullable', 'string', 'max:50', Rule::unique('products', 'code')->ignore($product?->id)],
            'name' => ['required', 'string', 'max:255'],
        ], [
            'code.unique' => 'その品番は、もう登録されています。',
            'code.max' => '品番は50文字までにしてください。',
            'name.required' => '品名を入れてください。',
            'name.max' => '品名は255文字までにしてください。',
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Http\Controllers\Admin\ProductController;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * R-12 製品の一覧と検索（D-019）
 */
class AdminProductTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 管理者としてパスワードを入れた状態にする
     */
    private function asAdmin(): static
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->withSession(['admin_last_activity' => now()->getTimestamp()]);
    }

    public function test_product_pages_require_the_admin_password(): void
    {
        $product = Product::factory()->create();

        $this->get('/admin/products')->assertRedirect('/admin/login');
        $this->post('/admin/products', ['company_id' => $product->company_id, 'name' => '新しい製品'])->assertRedirect('/admin/login');
        $this->patch("/admin/products/{$product->id}", ['company_id' => $product->company_id, 'name' => '直した'])->assertRedirect('/admin/login');
        $this->delete("/admin/products/{$product->id}")->assertRedirect('/admin/login');

        $this->assertSame(1, Product::count());
        $this->assertNotSame('直した', $product->refresh()->name);
    }

    /**
     * 作業者は管理者の画面に入れない（ログインしていても管理者でなければ）
     */
    public function test_worker_cannot_use_product_pages(): void
    {
        $this->actingAs(User::factory()->create())
            ->withSession(['admin_last_activity' => now()->getTimestamp()])
            ->get('/admin/products')
            ->assertRedirect('/admin/login');
    }

    public function test_list_shows_products_with_company_and_how_many_orders_use_them(): void
    {
        $company = Company::factory()->create(['name' => 'ダミー東和工業']);
        $used = Product::factory()->create(['company_id' => $company->id, 'code' => 'AA-0001', 'name' => 'シャフト']);
        ProductionOrder::factory()->count(2)->create(['product_id' => $used->id]);
        $unused = Product::factory()->withoutCode()->create(['company_id' => $company->id, 'name' => 'ステー']);

        $this->asAdmin()->get('/admin/products')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/Products')
            ->where('companyId', null)
            ->where('companies', [['id' => $company->id, 'name' => 'ダミー東和工業']])
            ->where('products.total', 2)
            ->where('products.data.0', ['id' => $used->id, 'companyId' => $company->id, 'companyName' => 'ダミー東和工業', 'code' => 'AA-0001', 'name' => 'シャフト', 'ordersCount' => 2, 'isDummy' => false])
            ->where('products.data.1', ['id' => $unused->id, 'companyId' => $company->id, 'companyName' => 'ダミー東和工業', 'code' => null, 'name' => 'ステー', 'ordersCount' => 0, 'isDummy' => false])
        );
    }

    /**
     * 取引会社を選ぶと、その会社の製品だけが出る。品名を打てば、その会社の中でしぼられる（D-033）
     */
    public function test_choosing_a_company_shows_only_its_products(): void
    {
        $towa = Company::factory()->create();
        $hokuriku = Company::factory()->create();
        Product::factory()->create(['company_id' => $towa->id, 'name' => 'シャフト']);
        Product::factory()->create(['company_id' => $towa->id, 'name' => 'フランジ']);
        Product::factory()->create(['company_id' => $hokuriku->id, 'name' => 'シャフト']);

        $this->asAdmin()->get("/admin/products?company={$towa->id}")->assertInertia(fn (Assert $page) => $page
            ->where('companyId', $towa->id)
            ->where('products.total', 2)
            ->where('products.data', fn ($data) => collect($data)->every(fn ($p) => $p['companyId'] === $towa->id))
        );
        $this->get("/admin/products?company={$towa->id}&q=シャ")->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 1)
            ->where('products.data.0.name', 'シャフト')
            ->where('products.data.0.companyId', $towa->id)
        );
    }

    /**
     * 品番でも品名でも、一部が合えば出る。大文字・小文字はどちらでもよい
     *
     * @return array<string, array{string, list<string>}>
     */
    public static function searches(): array
    {
        return [
            '品番の一部' => ['0660', ['シャフト']],
            '品番の小文字' => ['sh-', ['シャフト']],
            '品名の一部' => ['フラン', ['フランジ']],
            '品番と品名の両方に合う' => ['FL', ['フランジ']],
            'どれにも合わない' => ['ないもの', []],
            '「%」は何にでも合う印にしない' => ['%', ['割合100%カバー']],
        ];
    }

    /**
     * @param  list<string>  $expected
     */
    #[DataProvider('searches')]
    public function test_search_by_part_of_code_or_name(string $q, array $expected): void
    {
        Product::factory()->create(['code' => 'SH-0660', 'name' => 'シャフト']);
        Product::factory()->create(['code' => 'FL-0100', 'name' => 'フランジ']);
        Product::factory()->withoutCode()->create(['name' => '割合100%カバー']);

        $this->asAdmin()->get('/admin/products?q='.urlencode($q))->assertInertia(fn (Assert $page) => $page
            ->where('q', $q)
            ->where('products.total', count($expected))
            ->where('products.data', fn ($data) => collect($data)->pluck('name')->all() === $expected)
        );
    }

    public function test_list_is_split_into_pages(): void
    {
        Product::factory()->count(ProductController::PER_PAGE + 1)->create();

        $this->asAdmin()->get('/admin/products')->assertInertia(fn (Assert $page) => $page
            ->has('products.data', ProductController::PER_PAGE)
            ->where('products.lastPage', 2)
        );
        $this->get('/admin/products?page=2')->assertInertia(fn (Assert $page) => $page->has('products.data', 1));
    }

    public function test_admin_can_add_a_product_with_or_without_code(): void
    {
        $c = Company::factory()->create()->id;
        $this->asAdmin()->from('/admin/products')->post('/admin/products', ['company_id' => $c, 'code' => 'SH-0660', 'name' => 'シャフト'])
            ->assertRedirect('/admin/products')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('notice', '製品を登録しました。');
        // 品番が空の製品は、いくつあってもよい
        $this->post('/admin/products', ['company_id' => $c, 'code' => '', 'name' => 'ステー'])->assertSessionHasNoErrors();
        $this->post('/admin/products', ['company_id' => $c, 'code' => '', 'name' => 'スペーサー'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', ['company_id' => $c, 'code' => 'SH-0660', 'name' => 'シャフト', 'is_dummy' => false]);
        $this->assertSame(2, Product::whereNull('code')->count());
    }

    public function test_name_is_required(): void
    {
        $c = Company::factory()->create()->id;
        $this->asAdmin()->from('/admin/products')->post('/admin/products', ['company_id' => $c, 'code' => 'SH-0660', 'name' => ''])
            ->assertSessionHasErrors(['name' => '品名を入れてください。']);

        $this->assertSame(0, Product::count());
    }

    /**
     * 製品は必ずどこか1社の取引会社のもの（D-033）
     */
    public function test_company_is_required(): void
    {
        $this->asAdmin()->from('/admin/products')->post('/admin/products', ['name' => 'シャフト'])
            ->assertSessionHasErrors(['company_id' => '取引会社を選んでください。']);
        $this->post('/admin/products', ['company_id' => 999999, 'name' => 'シャフト'])
            ->assertSessionHasErrors(['company_id' => '選んだ取引会社が見つかりません。']);

        $this->assertSame(0, Product::count());
    }

    /**
     * 同じ品番は二重に登録できない。大文字・小文字がちがうだけのものも同じ品番として扱う
     */
    public function test_the_same_code_cannot_be_registered_twice(): void
    {
        $existing = Product::factory()->create(['code' => 'SH-0660']);
        $c = $existing->company_id;

        $this->asAdmin()->from('/admin/products')->post('/admin/products', ['company_id' => $c, 'code' => 'SH-0660', 'name' => 'べつの製品'])
            ->assertSessionHasErrors(['code' => 'その品番は、もう登録されています。']);
        $this->post('/admin/products', ['company_id' => $c, 'code' => 'sh-0660', 'name' => 'べつの製品'])
            ->assertSessionHasErrors('code');

        $this->assertSame(1, Product::count());
    }

    public function test_admin_can_update_a_product(): void
    {
        $product = Product::factory()->create(['code' => 'SH-0660', 'name' => 'シャフト']);
        $other = Company::factory()->create();

        // 品番をそのままにして品名だけ直すのはよい（自分の品番とは重ならない）
        $this->asAdmin()->from('/admin/products')->patch("/admin/products/{$product->id}", ['company_id' => $product->company_id, 'code' => 'SH-0660', 'name' => 'シャフト（長）'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('notice', '製品を直しました。');
        // 品番を空にも、取引会社を変えることもできる
        $this->patch("/admin/products/{$product->id}", ['company_id' => $other->id, 'code' => '', 'name' => 'シャフト（長）'])->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertNull($product->code);
        $this->assertSame('シャフト（長）', $product->name);
        $this->assertSame($other->id, $product->company_id);
    }

    public function test_update_cannot_take_another_products_code(): void
    {
        Product::factory()->create(['code' => 'FL-0100']);
        $product = Product::factory()->create(['code' => 'SH-0660']);

        $this->asAdmin()->from('/admin/products')->patch("/admin/products/{$product->id}", ['company_id' => $product->company_id, 'code' => 'FL-0100', 'name' => 'シャフト'])
            ->assertSessionHasErrors('code');

        $this->assertSame('SH-0660', $product->refresh()->code);
    }

    public function test_unused_product_can_be_deleted(): void
    {
        $product = Product::factory()->create();

        $this->asAdmin()->from('/admin/products')->delete("/admin/products/{$product->id}")
            ->assertSessionHasNoErrors()
            ->assertSessionHas('notice', '製品を消しました。');

        $this->assertModelMissing($product);
    }

    /**
     * 指示に使ったことがある製品は消せない（完了した指示でも）
     */
    public function test_product_used_by_an_order_cannot_be_deleted(): void
    {
        $product = Product::factory()->create(['name' => 'シャフト']);
        ProductionOrder::factory()->status(OrderStatus::Done)->create(['product_id' => $product->id]);

        $this->asAdmin()->from('/admin/products')->delete("/admin/products/{$product->id}")
            ->assertSessionHasErrors(['product' => '「シャフト」は指示に使ったことがあるので、消せません。']);

        $this->assertModelExists($product);
    }

    /**
     * 新しく登録した製品は、ボードの小さな画面でも選べる
     */
    public function test_new_product_appears_in_the_board_picker(): void
    {
        $c = Company::factory()->create(['name' => 'ダミー東和工業'])->id;
        $this->asAdmin()->post('/admin/products', ['company_id' => $c, 'code' => 'NW-0001', 'name' => '新しい製品']);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('products', fn ($products) => collect($products)->contains(fn ($p) => $p['name'] === '新しい製品' && $p['companyName'] === 'ダミー東和工業'))
        );
    }
}

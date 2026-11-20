<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * R-14 取引会社（D-033）
 */
class AdminCompanyTest extends TestCase
{
    use RefreshDatabase;

    private function asAdmin(): static
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->withSession(['admin_last_activity' => now()->getTimestamp()]);
    }

    public function test_company_pages_require_the_admin_password(): void
    {
        $company = Company::factory()->create();

        $this->get('/admin/companies')->assertRedirect('/admin/login');
        $this->post('/admin/companies', ['name' => 'ダミー新会社'])->assertRedirect('/admin/login');
        $this->patch("/admin/companies/{$company->id}", ['name' => '直した'])->assertRedirect('/admin/login');
        $this->delete("/admin/companies/{$company->id}")->assertRedirect('/admin/login');

        $this->assertSame(1, Company::count());
        $this->assertNotSame('直した', $company->refresh()->name);
    }

    public function test_list_shows_companies_in_name_order_with_product_counts(): void
    {
        $b = Company::factory()->create(['name' => 'ダミー北陸製作所']);
        $a = Company::factory()->create(['name' => 'ダミー中央機械']);
        Product::factory()->count(2)->create(['company_id' => $b->id]);

        $this->asAdmin()->get('/admin/companies')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/Companies')
            ->has('companies', 2)
            ->where('companies.0', ['id' => $a->id, 'name' => 'ダミー中央機械', 'productsCount' => 0, 'isDummy' => false])
            ->where('companies.1', ['id' => $b->id, 'name' => 'ダミー北陸製作所', 'productsCount' => 2, 'isDummy' => false])
        );
    }

    public function test_admin_can_add_and_rename_a_company(): void
    {
        // 品番・品名の欄を送らなくても、会社だけを登録できる
        $this->asAdmin()->from('/admin/companies')->post('/admin/companies', ['name' => 'ダミー東和工業'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('notice', '取引会社を登録しました（製品は0件）。');
        $company = Company::where('name', 'ダミー東和工業')->firstOrFail();

        $this->patch("/admin/companies/{$company->id}", ['name' => 'ダミー東和精工'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('notice', '取引会社を直しました。');
        // 名前をそのままにして保存するのはよい（自分の名前とは重ならない）
        $this->patch("/admin/companies/{$company->id}", ['name' => 'ダミー東和精工'])->assertSessionHasNoErrors();

        $this->assertSame('ダミー東和精工', $company->refresh()->name);
        $this->assertFalse($company->is_dummy);
    }

    /**
     * 会社を足す欄で品名もいっしょに入れると、その会社の製品を1件足す（D-034）
     */
    public function test_adding_a_company_with_a_product_creates_both(): void
    {
        $this->asAdmin()->from('/admin/companies')->post('/admin/companies', [
            'name' => 'ダミー東和工業',
            'product_code' => 'SH-0660',
            'product_name' => 'シャフト',
        ])->assertSessionHasNoErrors()->assertSessionHas('notice', '取引会社と製品を登録しました。');

        $company = Company::where('name', 'ダミー東和工業')->firstOrFail();
        $this->assertSame(1, $company->products()->count());
        $this->assertDatabaseHas('products', ['company_id' => $company->id, 'code' => 'SH-0660', 'name' => 'シャフト']);
    }

    /**
     * 品番・品名が空なら、製品0件の会社として登録する
     */
    public function test_adding_a_company_without_product_creates_zero_products(): void
    {
        $this->asAdmin()->from('/admin/companies')->post('/admin/companies', [
            'name' => 'ダミー東和工業',
            'product_code' => '',
            'product_name' => '',
        ])->assertSessionHasNoErrors()->assertSessionHas('notice', '取引会社を登録しました（製品は0件）。');

        $this->assertSame(0, Company::where('name', 'ダミー東和工業')->firstOrFail()->products()->count());
        $this->assertSame(0, Product::count());
    }

    /**
     * 品名のない製品は作れない。そのときは会社も登録しない
     */
    public function test_product_code_without_name_is_rejected_and_nothing_is_created(): void
    {
        $this->asAdmin()->from('/admin/companies')->post('/admin/companies', [
            'name' => 'ダミー東和工業',
            'product_code' => 'SH-0660',
            'product_name' => '',
        ])->assertSessionHasErrors(['product_name' => '品番を入れたときは、品名も入れてください。']);

        $this->assertSame(0, Company::count());
        $this->assertSame(0, Product::count());
    }

    /**
     * 品番がほかの製品と重なるときは、会社も登録しない
     */
    public function test_duplicate_product_code_is_rejected_and_the_company_is_not_created(): void
    {
        Product::factory()->create(['code' => 'SH-0660']);

        $this->asAdmin()->from('/admin/companies')->post('/admin/companies', [
            'name' => 'ダミー東和工業',
            'product_code' => 'SH-0660',
            'product_name' => 'シャフト',
        ])->assertSessionHasErrors(['product_code' => 'その品番は、もう登録されています。']);

        $this->assertFalse(Company::where('name', 'ダミー東和工業')->exists());
        $this->assertSame(1, Product::count());
    }

    /**
     * 登録してある会社には、小さな画面から製品を足す（製品の登録のしくみを使う）
     */
    public function test_product_can_be_added_to_an_existing_company(): void
    {
        $company = Company::factory()->create();

        $this->asAdmin()->from('/admin/companies')->post('/admin/products', ['company_id' => $company->id, 'code' => '', 'name' => 'フランジ'])
            ->assertSessionHasNoErrors();

        $this->get('/admin/companies')->assertInertia(fn (Assert $page) => $page->where('companies.0.productsCount', 1));
    }

    public function test_name_is_required_and_cannot_be_registered_twice(): void
    {
        Company::factory()->create(['name' => 'ダミー東和工業']);

        $this->asAdmin()->from('/admin/companies')->post('/admin/companies', ['name' => ''])
            ->assertSessionHasErrors(['name' => '会社名を入れてください。']);
        $this->post('/admin/companies', ['name' => 'ダミー東和工業'])
            ->assertSessionHasErrors(['name' => 'その会社名は、もう登録されています。']);

        $this->assertSame(1, Company::count());
    }

    public function test_company_without_products_can_be_deleted(): void
    {
        $company = Company::factory()->create();

        $this->asAdmin()->from('/admin/companies')->delete("/admin/companies/{$company->id}")
            ->assertSessionHasNoErrors()
            ->assertSessionHas('notice', '取引会社を消しました。');

        $this->assertModelMissing($company);
    }

    /**
     * 製品が登録されている会社は消せない（D-033）
     */
    public function test_company_with_products_cannot_be_deleted(): void
    {
        $company = Company::factory()->create(['name' => 'ダミー東和工業']);
        Product::factory()->create(['company_id' => $company->id]);

        $this->asAdmin()->from('/admin/companies')->delete("/admin/companies/{$company->id}")
            ->assertSessionHasErrors(['company' => '「ダミー東和工業」には製品が登録されているので、消せません。']);

        $this->assertModelExists($company);
    }

    /**
     * 会社名を直すと、ボードのカードの会社名も変わる
     */
    public function test_renamed_company_appears_on_the_board(): void
    {
        $company = Company::factory()->create(['name' => 'ダミー東和工業']);
        $product = Product::factory()->create(['company_id' => $company->id]);
        ProductionOrder::factory()->create(['product_id' => $product->id]);

        $this->asAdmin()->patch("/admin/companies/{$company->id}", ['name' => 'ダミー東和精工']);

        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('columns.0.orders.0.companyName', 'ダミー東和精工'));
    }
}

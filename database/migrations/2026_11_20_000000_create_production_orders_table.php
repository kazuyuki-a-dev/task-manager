<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 取引会社（R-14・D-033）。登録するのは会社名だけ
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            // 同じ会社名は二重に登録できない
            $table->string('name')->unique();
            // ダミーデータの印
            $table->boolean('is_dummy')->default(false);
            $table->timestamps();
        });

        // 製品の一覧（D-019）
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            // 製品は必ずどこか1社の取引会社のもの。製品が登録されている会社は消せない（D-033）
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            // 品番は空でもよい。入れたときは二重に登録できない（空どうしは重なってもよい）
            $table->string('code')->nullable()->unique();
            $table->string('name');
            // ダミーデータの印
            $table->boolean('is_dummy')->default(false);
            $table->timestamps();

            // 品名の一部で探すため
            $table->index('name');
        });

        // 製造指示。今の Excel の1行が、ここの1行になる（D-002）
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            // 履歴が残っている製品は消せないようにする
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            // 材料集結日は表示するだけ（D-009）。決まっていない指示もあるので空でもよい
            $table->date('material_ready_date')->nullable();
            $table->date('due_date');
            // not_started / in_progress / done。App\Enums\OrderStatus
            $table->string('status')->default('not_started');
            // 「作業中」にした日時と「完了」にした日時（D-017）
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            // ダミーデータの印
            $table->boolean('is_dummy')->default(false);
            $table->timestamps();

            // ボードは「状態ごとに、納期の近い順」に読むので、その形で探しやすくする
            $table->index(['status', 'due_date']);
        });

        // 指示の担当。1つの指示に何人でも入れられる（D-018）
        Schema::create('order_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained()->cascadeOnDelete();
            // 社員は消さずに使えなくする（R-07）ので、担当した記録は残る
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            // 同じ人を同じ指示に二重に入れない
            $table->unique(['production_order_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_assignees');
        Schema::dropIfExists('production_orders');
        Schema::dropIfExists('products');
        Schema::dropIfExists('companies');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 製造指示。今の Excel の1行が、ここの1行になる（D-002）
     */
    public function up(): void
    {
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->string('product_name');
            $table->unsignedInteger('quantity');
            // 材料集結日は表示するだけ（D-009）。決まっていない指示もあるので空でもよい
            $table->date('material_ready_date')->nullable();
            $table->date('due_date');
            // 担当がまだ決まっていない指示もある。社員は消さずに使えなくするので、消えたときだけ空にする
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            // not_started / in_progress / done。App\Enums\OrderStatus
            $table->string('status')->default('not_started');
            // ダミーデータの印
            $table->boolean('is_dummy')->default(false);
            $table->timestamps();

            // ボードは「状態ごとに、納期の近い順」に読むので、その形で探しやすくする
            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_orders');
    }
};

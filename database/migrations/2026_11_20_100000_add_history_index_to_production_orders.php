<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 完了の履歴（R-10）は「製品ごとに、完了した指示を、完了した日の新しい順」に読むので、その形で探しやすくする。
     * 今あるデータを消さないように、テーブルを作るファイルは書きかえず、索引だけを足す
     */
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->index(['product_id', 'status', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'status', 'completed_at']);
        });
    }
};

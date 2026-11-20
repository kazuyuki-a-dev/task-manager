<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 指示の変更の記録（R-08・D-041）。だれが変えたかは残さない（D-023）。
     * 今あるデータを消さないように、新しいファイルで足す
     */
    public function up(): void
    {
        Schema::create('order_logs', function (Blueprint $table) {
            $table->id();
            // 指示を消したら、その指示の記録もいっしょに消える
            $table->foreignId('production_order_id')->constrained()->cascadeOnDelete();
            // created / status / product / quantity / material_ready_date / due_date / assignee_added / assignee_removed。App\Enums\OrderLogAction
            $table->string('action');
            // 何から何に。名前や製品名は、変えたときのまま文字で残す（あとで名前を直しても、記録は当時のまま）
            $table->string('old_value')->nullable();
            $table->string('new_value')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // 指示ごとに、新しい順に読む
            $table->index(['production_order_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_logs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 会社カレンダー（R-13・D-040）。今あるデータを消さないように、新しいファイルで足す
     */
    public function up(): void
    {
        // 社員の休み
        Schema::create('absences', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            // 社員は消さずに印を付けるので、休みの記録も残る（D-036）
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            // paid（有給）か absent（欠勤）。App\Enums\AbsenceType。管理者の画面でだけ見える（D-020）
            $table->string('type');
            $table->timestamps();

            // 同じ人を同じ日に二重に入れない。月ごとに読むので、日付から探しやすくもなる
            $table->unique(['date', 'user_id']);
        });

        // 行事（会社の休みも、今はここに登録する）
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->string('title', 50);
            // 少しだけ書ける内容。空でもよい
            $table->string('body', 200)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
        Schema::dropIfExists('absences');
    }
};

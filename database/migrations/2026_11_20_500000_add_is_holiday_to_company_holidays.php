<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 日曜日は、はじめから会社の休みにする（D-043）。
     * 日曜日の分を全部入れておくのではなく、「決まりとちがう日」だけを記録する。
     * - 月〜土曜日を休みにした日：is_holiday = true
     * - 日曜日を出勤日にした日（休日出勤）：is_holiday = false
     * 今ある行は、これまでどおり「休み」として残す
     */
    public function up(): void
    {
        Schema::table('company_holidays', function (Blueprint $table) {
            $table->boolean('is_holiday')->default(true)->after('date');
        });
    }

    public function down(): void
    {
        Schema::table('company_holidays', function (Blueprint $table) {
            $table->dropColumn('is_holiday');
        });
    }
};

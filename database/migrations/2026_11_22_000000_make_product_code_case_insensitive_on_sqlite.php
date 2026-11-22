<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SQLite（お試し用のデモ）のときだけ、品番の大文字・小文字を区別しないようにする。
 * MySQL は、はじめから区別しない（「SH-0660」と「sh-0660」は同じ品番）ので、何もしない（D-056）
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->string('code')->nullable()->collation('NOCASE')->change();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->string('code')->nullable()->collation('BINARY')->change();
        });
    }
};

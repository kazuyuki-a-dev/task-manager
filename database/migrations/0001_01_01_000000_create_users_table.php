<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 社員。メールは使わない（D-006）ので、メールアドレスの列は持たない
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // admin（管理者）か worker（作業者）。App\Enums\UserRole
            $table->string('role')->default('worker');
            // 管理者だけが持つ。作業者は名前を選ぶだけなので空（D-003・D-004）
            $table->string('password')->nullable();
            // 辞めた人は消さずに使えなくする。過去の記録に名前を残すため（R-07）
            $table->boolean('is_active')->default(true);
            // ダミーデータの印
            $table->boolean('is_dummy')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('sessions');
    }
};

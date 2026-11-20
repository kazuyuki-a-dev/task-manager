<?php

namespace App\Enums;

/**
 * 社員の役割（D-004）
 */
enum UserRole: string
{
    /** 指示を作る・担当を決める・社員を登録する。操作にはパスワードがいる */
    case Admin = 'admin';

    /** 自分が担当する指示の状態を変える。名前を選ぶだけで操作する */
    case Worker = 'worker';

    public function label(): string
    {
        return match ($this) {
            self::Admin => '管理者',
            self::Worker => '作業者',
        };
    }
}

<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder;

/**
 * 「〜を含む」の検索。「%」や「_」を打っても、何にでも合う印として扱われないようにする。
 * 印を消す文字は「!」にして、ESCAPE '!' を付ける。
 * SQLite（お試し用のデモ）は ESCAPE を付けないと印を消せず、「\」を使うと MySQL と SQLite で読み方がちがってエラーになるため（D-056）
 */
class Like
{
    /**
     * 打った文字を、「その文字を含む」の形にする（例：「10%」→「%10!%%」）
     */
    public static function pattern(string $q): string
    {
        // 「!」を先に置きかえる（あとで足した「!」を、もう一度置きかえないように strtr で一度に置きかえる）
        return '%'.strtr($q, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
    }

    /**
     * 列が、打った文字を含む。列の名前は、プログラムの中で決めたものだけを渡す（打った文字を入れない）
     */
    public static function where(Builder $query, string $column, string $q, string $boolean = 'and'): Builder
    {
        return $query->whereRaw("{$column} like ? escape '!'", [self::pattern($q)], $boolean);
    }
}

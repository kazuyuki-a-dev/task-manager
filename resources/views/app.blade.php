<!DOCTYPE html>
<html lang="ja">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }}</title>
        {{-- アイコン（#52）。今のブラウザは svg、古いブラウザは ico、スマホのホーム画面は png を使う --}}
        <link rel="icon" href="/favicon.ico" sizes="32x32">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        @viteReactRefresh
        @vite(['resources/js/app.tsx'])
        <x-inertia::head />
    </head>
    <body class="antialiased">
        {{-- お試し用のデモのときだけ、全部の画面の上に出す（D-048）。全画面の共通の枠なので、ここに1つだけ置く --}}
        @if (config('demo.enabled'))
            <div role="note" class="border-b border-amber-300 bg-amber-100 px-4 py-2 text-center text-sm font-medium text-amber-900">
                お試し用のデモです。会社・製品・人の名前はすべてダミーです。触った内容は、しばらくすると元に戻ります
            </div>
        @endif
        <x-inertia::app />
    </body>
</html>

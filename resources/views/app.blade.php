<!DOCTYPE html>
<html lang="ja">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }}</title>
        @viteReactRefresh
        @vite(['resources/js/app.tsx'])
        <x-inertia::head />
    </head>
    <body class="antialiased">
        <x-inertia::app />
    </body>
</html>

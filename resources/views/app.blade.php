<!DOCTYPE html>
<html lang="id" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title data-inertia="title">SuaraNetijen — Indeks Sentimen Publik Indonesia</title>
            <meta data-inertia="description" name="description" content="Cari tahu opini netizen tentang brand, produk, dan layanan di Indonesia lewat sentimen publik dan rating pengguna di SuaraNetijen.">
            <meta data-inertia="robots" name="robots" content="index, follow">
            <link data-inertia="canonical" rel="canonical" href="{{ url()->current() }}">
            <meta data-inertia="og:title" property="og:title" content="SuaraNetijen — Indeks Sentimen Publik Indonesia">
            <meta data-inertia="og:description" property="og:description" content="Cari tahu opini netizen tentang brand, produk, dan layanan di Indonesia lewat sentimen publik dan rating pengguna di SuaraNetijen.">
            <meta data-inertia="og:url" property="og:url" content="{{ url()->current() }}">
            <meta data-inertia="og:type" property="og:type" content="website">
            <meta data-inertia="og:site_name" property="og:site_name" content="{{ config('app.name', 'SuaraNetijen') }}">
            <meta data-inertia="og:locale" property="og:locale" content="id_ID">
            <meta data-inertia="og:image" property="og:image" content="{{ rtrim(config('app.url'), '/') }}/logo.svg">
            <meta data-inertia="twitter:card" name="twitter:card" content="summary">
            <meta data-inertia="twitter:title" name="twitter:title" content="SuaraNetijen — Indeks Sentimen Publik Indonesia">
            <meta data-inertia="twitter:description" name="twitter:description" content="Cari tahu opini netizen tentang brand, produk, dan layanan di Indonesia lewat sentimen publik dan rating pengguna di SuaraNetijen.">
            <meta data-inertia="twitter:image" name="twitter:image" content="{{ rtrim(config('app.url'), '/') }}/logo.svg">
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>

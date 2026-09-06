@php
    $status ??= 500;
    $title ??= 'Ada gangguan di sisi kami';
    $message ??= 'Terjadi kesalahan tak terduga. Silakan coba lagi sebentar lagi.';
@endphp
<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $status }} — {{ $title }} | SuaraNetijen</title>
        <link rel="icon" href="{{ asset('favicon.svg') }}?v=2" type="image/svg+xml">
        <style>
            :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
            * { box-sizing: border-box; }
            body { margin: 0; min-height: 100vh; background: #f4f8f1; color: #18392d; }
            main { display: grid; min-height: 100vh; place-items: center; padding: 3rem 1.5rem; }
            .content { width: min(100%, 32rem); text-align: center; }
            .brand { display: inline-flex; align-items: center; gap: .75rem; color: #18392d; text-decoration: none; }
            .brand svg { width: 3rem; height: 3rem; color: #087f5b; }
            .brand-name { font-size: 1.35rem; font-weight: 700; letter-spacing: -.04em; }
            .brand-name span { color: #087f5b; }
            .tagline { margin: .3rem 0 0; color: #68746b; font-size: .75rem; }
            .status { margin: 3rem 0 0; color: #087f5b; font-size: .75rem; font-weight: 700; letter-spacing: .28em; }
            h1 { margin: 1rem 0 0; font-size: clamp(1.9rem, 4vw, 2.5rem); line-height: 1.15; }
            p { margin: 1rem auto 0; max-width: 28rem; color: #68746b; line-height: 1.7; }
            .button { display: inline-block; margin-top: 2rem; border-radius: 999px; background: #087f5b; color: #fff; padding: .8rem 1.4rem; font-size: .9rem; font-weight: 700; text-decoration: none; }
            .button:hover { background: #066446; }
        </style>
    </head>
    <body>
        <main>
            <div class="content">
                <a class="brand" href="{{ url('/') }}" aria-label="SuaraNetijen — Beranda">
                    <svg viewBox="0 0 48 48" aria-hidden="true">
                        <path fill="currentColor" fill-rule="evenodd" d="M15 4C8.925 4 4 8.925 4 15v15c0 6.075 4.925 11 11 11h3v5l10-5h5c6.075 0 11-4.925 11-11V15c0-6.075-4.925-11-11-11H15Zm-1 15a2 2 0 0 1 2 2v6a2 2 0 0 1-4 0v-6a2 2 0 0 1 2-2Zm10-7a2 2 0 0 1 2 2v16a2 2 0 0 1-4 0V14a2 2 0 0 1 2-2Zm10 4a2 2 0 0 1 2 2v8a2 2 0 0 1-4 0v-8a2 2 0 0 1 2-2Z" />
                    </svg>
                    <span>
                        <span class="brand-name">suara<span>netijen.</span></span>
                        <span class="tagline">Sebelum pilih, cek kata netizen.</span>
                    </span>
                </a>
                <div class="status">ERROR {{ $status }}</div>
                <h1>{{ $title }}</h1>
                <p>{{ $message }}</p>
                <a class="button" href="{{ url('/') }}">Kembali ke beranda</a>
            </div>
        </main>
    </body>
</html>

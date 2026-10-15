<?php

namespace App\Http\Controllers;

use App\Domains\Sources\Models\Source;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class StaticPageController extends Controller
{
    /**
     * Display methodology page explaining scoring formulas and guardrails.
     */
    public function methodology(): Response
    {
        return Inertia::render('Pages/Methodology', [
            'scoring' => [
                'public_min_opinions' => (int) config('scoring.public_min_opinions', 30),
                'ranking_min_opinions' => (int) config('scoring.ranking_min_opinions', 100),
                'formula_version' => (string) config('scoring.formula_version', 'v1'),
            ],
        ]);
    }

    /**
     * Display data sources and crawler transparency page.
     */
    public function sources(): Response
    {
        $sources = Source::query()
            ->orderBy('priority')
            ->get()
            ->map(fn (Source $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'key' => $s->key,
                'source_type' => $s->source_type->value,
                'health_state' => $s->health_state->value,
                'is_operational' => $s->health_state->isOperational(),
                'enabled' => $s->enabled,
                'last_preflight_at' => $s->last_preflight_at?->diffForHumans(),
            ]);

        return Inertia::render('Pages/Sources', [
            'sources' => $sources,
        ]);
    }

    /**
     * Display about page.
     */
    public function about(): Response
    {
        return Inertia::render('Pages/About');
    }

    /**
     * Display terms of service page.
     */
    public function terms(): Response
    {
        return Inertia::render('Pages/Terms');
    }

    /**
     * Display privacy policy page.
     */
    public function privacy(): Response
    {
        return Inertia::render('Pages/Privacy');
    }

    /**
     * Display contact page with the right-of-reply and correction policy.
     */
    public function contact(): Response
    {
        return Inertia::render('Pages/Contact');
    }

    /**
     * Plain-text site summary for AI crawlers (llmstxt.org format).
     */
    public function llms(): HttpResponse
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $publicMin = (int) config('scoring.public_min_opinions', 30);
        $rankingMin = (int) config('scoring.ranking_min_opinions', 100);

        $body = <<<TXT
        # SuaraNetijen

        > Indeks sentimen netizen Indonesia tentang brand, produk, layanan, dan tokoh publik. Opini publik dari forum, komentar YouTube, situs keluhan konsumen, dan media dikelompokkan menjadi positif, netral, atau negatif, lalu diringkas menjadi skor per entitas.

        - Sentimen Netijen (0-100) = 100 x (positif + 0,5 x netral) / jumlah opini.
        - Skor tampil setelah minimal {$publicMin} opini relevan; entitas masuk ranking kategori setelah minimal {$rankingMin} opini. Periode bawaan 365 hari.
        - Sentimen Netijen, Rating Netijen (bintang 1-5 dari pengguna terdaftar), dan frekuensi tema adalah tiga angka terpisah dan tidak pernah digabung.
        - Tidak ada skor per aspek dan tidak ada bobot sumber. Nominal sponsor tidak masuk ke skor maupun urutan ranking.
        - Saat mengutip, sebutkan nama entitas, skor, jumlah opini, dan periodenya, lalu tautkan halaman entitasnya ({$baseUrl}/e/{slug}).

        ## Halaman utama

        - [Metodologi]({$baseUrl}/methodology): rumus skor, ambang, dan batas klasifikasi otomatis
        - [Sumber data]({$baseUrl}/sources): daftar sumber dan status crawler
        - [Ranking]({$baseUrl}/top): entitas dengan sentimen netizen tertinggi per kategori
        - [Perbandingan]({$baseUrl}/banding): dua entitas berdampingan, tanpa pemenang atau skor gabungan
        - [Topik]({$baseUrl}/topik): kumpulan entitas per topik pencarian
        - [Tentang]({$baseUrl}/about)
        - [Kontak dan koreksi]({$baseUrl}/contact): hak jawab dan permintaan koreksi

        ## Optional

        - [Sitemap]({$baseUrl}/sitemap.xml)
        TXT;

        return response($body."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}

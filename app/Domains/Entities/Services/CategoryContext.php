<?php

namespace App\Domains\Entities\Services;

class CategoryContext
{
    /**
     * Contextual descriptions for category and top ranking pages (docs/31 Fase 1).
     *
     * @var array<string, string>
     */
    protected static array $descriptions = [
        'smartphone' => 'Indeks sentimen publik dan ulasan netizen untuk berbagai merek dan tipe smartphone di Indonesia. Ketahui kelebihan, kekurangan, dan kepuasan pengguna sebelum memilih perangkat baru.',
        'mobil' => 'Pantau reputasi dan sentimen netizen mengenai brand serta model mobil di Indonesia. Analisis opini riil publik seputar kenyamanan, konsumsi bahan bakar, hingga layanan purnajual.',
        'motor' => 'Rangkuman suara netizen untuk sepeda motor di Indonesia, dari motor matik harian hingga motor sport. Dapatkan perbandingan kepuasan dan keluhan pengguna langsung dari komunitas.',
        'isp-telco' => 'Evaluasi kestabilan koneksi, kualitas jaringan seluler, dan layanan pelanggan provider internet di Indonesia berdasarkan pengalaman riil netizen.',
        'cloud-hosting' => 'Sentimen netizen mengenai penyedia layanan cloud, VPS, dan web hosting di Indonesia. Pelajari performa uptime dan responsivitas bantuan teknis dari sudut pandang pengguna.',
        'saas-software' => 'Indeks kepuasan pengguna aplikasi dan perangkat lunak produktivitas di Indonesia. Temukan keandalan fitur dan kemudahan integrasi dari ulasan netizen.',
        'bank-e-wallet' => 'Agregat opini publik seputar kemudahan transaksi digital, keandalan aplikasi mobile banking, dan dompet digital terpopuler di Indonesia.',
        'e-commerce' => 'Tinjauan sentimen konsumen mengenai platform belanja online di Indonesia, mencakup pengalaman belanja, kecepatan pengiriman, hingga kemudahan penanganan keluhan.',
        'fmcg' => 'Sentimen netizen terhadap produk kebutuhan harian dan barang konsumen di Indonesia berdasarkan ulasan serta diskusi terbuka di media sosial.',
        'logistics' => 'Indeks sentimen netizen mengenai kecepatan pengiriman, kehati-hatian kurir, dan akurasi pelacakan paket oleh berbagai jasa ekspedisi di Indonesia.',
        'ride-hailing' => 'Sentimen publik terhadap platform transportasi online dan layanan pesan antar makanan di Indonesia dari sudut pandang penumpang dan konsumen.',
        'maskapai-penerbangan' => 'Ulasan netizen seputar ketepatan waktu, kenyamanan kabin, dan penanganan penumpang maskapai penerbangan di Indonesia.',
        'institusi-layanan-publik' => 'Indeks kepuasan publik terhadap layanan instansi dan fasilitas publik di Indonesia berdasarkan aspirasi terbuka warga netizen.',
        'brand-umum' => 'Kumpulan sentimen publik untuk berbagai merek konsumen terkemuka di Indonesia yang aktif diperbincangkan netizen.',
    ];

    /**
     * Get the contextual description for a category by slug or name.
     */
    public static function getDescription(string $slug, string $fallbackName = ''): string
    {
        if (isset(self::$descriptions[$slug])) {
            return self::$descriptions[$slug];
        }

        $name = $fallbackName !== '' ? $fallbackName : ucwords(str_replace('-', ' ', $slug));

        return "Eksplorasi sentimen publik dan opini netizen Indonesia seputar brand, produk, dan layanan dalam kategori {$name}. Dihitung dari opini publik yang terbuka.";
    }
}

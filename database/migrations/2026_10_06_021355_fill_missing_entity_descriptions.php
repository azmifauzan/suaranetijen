<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-line descriptions for entities that clear the public threshold but had none (docs/31 Fase 1).
 * The description is the opening text of the entity page. Only empty descriptions are filled, so an
 * admin's edit is never overwritten; the same lines are in database/data/seed_entities.csv for the
 * entities that file lists, because the importer would otherwise blank them on the next import.
 */
return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private const DESCRIPTIONS = [
        'github' => 'Platform hosting kode dan kolaborasi pengembang perangkat lunak',
        'matahari' => 'Jaringan department store fesyen dan gaya hidup asal Indonesia',
        'nissan' => 'Produsen mobil asal Jepang',
        'mazda' => 'Produsen mobil asal Jepang',
        'kia' => 'Produsen mobil asal Korea Selatan',
        'vidio' => 'Layanan streaming video, film, dan siaran olahraga asal Indonesia',
        'mazda-2' => 'Mobil hatchback dan sedan kompak dari Mazda',
        'jago' => 'Bank digital asal Indonesia',
        'honda-civic' => 'Mobil sedan kompak dari Honda',
        'chery' => 'Produsen mobil asal Tiongkok',
        'honda-jazz' => 'Mobil hatchback dari Honda',
        'pertamina' => 'Perusahaan energi milik negara Indonesia',
        'chanel' => 'Rumah mode dan kecantikan mewah asal Prancis',
        'ajaib' => 'Aplikasi investasi saham dan reksa dana asal Indonesia',
        'benefit' => 'Merek kosmetik asal Amerika Serikat',
        'emina' => 'Merek kosmetik lokal Indonesia',
        'maybelline' => 'Merek kosmetik asal Amerika Serikat',
        'toyota-yaris' => 'Mobil hatchback dari Toyota',
        'garnier' => 'Merek perawatan kulit dan rambut asal Prancis',
        'mazda-3' => 'Mobil kompak dari Mazda',
        'pixy' => 'Merek kosmetik populer di Indonesia',
        'flip' => 'Aplikasi transfer uang antarbank asal Indonesia',
        'iphone-17' => 'Smartphone dari Apple',
        'avoskin' => 'Merek perawatan kulit lokal Indonesia',
        'mazda-cx-5' => 'SUV kompak dari Mazda',
        'xlsmart' => 'Operator seluler hasil penggabungan XL Axiata dan Smartfren',
        'isuzu' => 'Produsen kendaraan komersial dan mobil asal Jepang',
        'toyota-rush' => 'SUV dari Toyota',
        'suzuki-swift' => 'Mobil hatchback dari Suzuki',
        'sociolla' => 'Platform belanja kecantikan online asal Indonesia',
        'suzuki-ertiga' => 'Mobil MPV dari Suzuki',
        'nivea' => 'Merek perawatan kulit asal Jerman',
        'range-rover' => 'SUV mewah dari Land Rover',
        'ford-fiesta' => 'Mobil hatchback dari Ford',
        'mazda-6' => 'Mobil sedan dari Mazda',
    ];

    public function up(): void
    {
        foreach (self::DESCRIPTIONS as $slug => $description) {
            DB::table('entities')
                ->where('slug', $slug)
                ->where(fn ($query) => $query->whereNull('description')->orWhere('description', ''))
                ->update(['description' => $description, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach (self::DESCRIPTIONS as $slug => $description) {
            DB::table('entities')->where('slug', $slug)->where('description', $description)->update(['description' => null]);
        }
    }
};

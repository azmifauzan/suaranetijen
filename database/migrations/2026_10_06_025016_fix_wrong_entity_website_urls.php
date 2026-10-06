<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Wikidata text search returned homonyms (docs/31): "Benefit" got a journal, "Club" Atletico Madrid, "Jago" a
 * Serbian town, "DANA" an Israeli company, "Zoom" GitLab. Each row clears the link or points it at the right
 * site, but only while the stored URL still contains the known-bad fragment, so an admin's edit is never
 * overwritten. Replacements were checked to load and carry the right organisation's name; entities whose
 * correct site could not be confirmed are cleared (no link is better than a wrong one).
 */
return new class extends Migration
{
    /**
     * slug => [fragment of the wrong URL, replacement or null].
     *
     * @var array<string, array{0: string, 1: string|null}>
     */
    private const FIXES = [
        'dana' => ['danainternational', 'https://www.dana.id'],
        'joko-widodo' => ['presidenri', null],
        'jago' => ['jagodina', 'https://www.jago.com'],
        'honda-jazz' => ['rpmnews', null],
        'indihome' => ['useetv', 'https://www.telkomsel.com/indihome'],
        'jenius' => ['sttmcileungsi', 'https://www.jenius.com'],
        'zoom' => ['gitlab', 'https://www.zoom.com'],
        'benefit' => ['journals.ums', null],
        'club' => ['atleticodemadrid', null],
        'axis' => ['4ad.com', 'https://www.axis.co.id'],
        'pixy' => ['allartenter', null],
        'suzuki-baleno' => ['toyota.co.za', null],
        'suzuki-xl7' => ['web.archive.org', null],
        'bri' => ['ib.bri.co.id', 'https://bri.co.id'],
        'bank-neo-commerce' => ['yudhabhakti', 'https://www.bankneocommerce.co.id'],
        'gopay' => ['gojek.com', 'https://gopay.co.id'],
        'honda-hr-v' => ['honda.co.uk', 'https://www.honda-indonesia.com/hr-v'],
        'toyota-rush' => ['daihatsu.co.id', 'https://www.toyota.astra.co.id'],
        'toyota-raize' => ['daihatsu.co.id', 'https://www.toyota.astra.co.id'],
        'honda-mobil' => ['honda-indonesia.com/mobilio', 'https://www.honda-indonesia.com'],
        'vespa-sprint' => ['models/primavera', 'https://www.vespa.com'],
        'roma' => ['romamovie', null],
        'emina' => ['eminanetwork', null],
        'xiaomi-14' => ['xiaomi-14t-pro', null],
        'mazda' => ['mazda.com/ja', 'https://www.mazda.co.id'],
        'mazda-3' => ['mazda.fr', 'https://www.mazda.co.id'],
        'mazda-cx-5' => ['mazdausa', 'https://www.mazda.co.id'],
        'toyota-fortuner' => ['toyota.com.ph', 'https://www.toyota.astra.co.id'],
        'toyota-yaris' => ['toyota.fr', 'https://www.toyota.astra.co.id'],
        'garnier' => ['garnierusa', 'https://www.garnier.co.id'],
        'samsung-galaxy-s24-ultra' => ['samsung.com/us', 'https://www.samsung.com/id/smartphones/galaxy-s24-ultra/'],
        'ford-fiesta' => ['ford.it', null],
        'wardah' => ['radenfatah', null],
        'dove' => ['clubdevo', null],
        'aero' => ['finnair', null],
        'btn' => ['nationalrail', null],
        'sasa' => ['sazu.si', null],
        'tropical' => ['lukfactory', null],
        'sunlight' => ['krillbite', null],
        'uus' => ['airportus', null],
        'equil' => ['stainkudus', null],
        'djp' => ['dedalvs', null],
        'gmc-yukon' => ['chevrolet.com', null],
        'daihatsu-gran-max' => ['mazda.co.jp', null],
        'chevrolet-captiva' => ['wuling.id', null],
        'mitsubishi-mirage-g4' => ['dodge.com', null],
        'toyota-calya' => ['daihatsu.co.id', null],
        'toyota-agya' => ['daihatsu.co.id', null],
        'daihatsu-xenia' => ['toyota.astra.co.id', null],
        'fiat-500' => ['500clubitalia', null],
        'suzuki-karimun' => ['web.archive.org', null],
        'isuzu-panther' => ['web.archive.org', null],
        'subaru-impreza' => ['sti.jp', null],
        'bentley-continental' => ['continentalgt', null],
        'sweety' => ['holypeak', null],
        'map' => ['museoarteponce', null],
    ];

    public function up(): void
    {
        foreach (self::FIXES as $slug => [$wrongFragment, $replacement]) {
            DB::table('entities')
                ->where('slug', $slug)
                ->where('website_url', 'like', '%'.$wrongFragment.'%')
                ->update(['website_url' => $replacement, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // The previous values were wrong; there is nothing worth restoring.
    }
};

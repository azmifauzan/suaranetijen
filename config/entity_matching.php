<?php

// Entity alias hygiene for EntityMatcher and source discovery (docs/10, precision over recall).

// Words that show a brand name shared with an everyday or foreign word is about
// household appliances ("sharp" the TV brand vs "sharp" the English adjective).
$applianceContext = ['kompor', 'rice cooker', 'magic com', 'magic jar', 'penanak nasi', 'kulkas', 'freezer', 'mesin cuci', 'dispenser', 'kipas', 'setrika', 'blender', 'mixer', 'water heater', 'pemanas air', 'oven', 'microwave', 'air fryer', 'vacuum', 'penyedot debu', 'ac', 'tv', 'televisi', 'elektronik', 'peralatan', 'dapur', 'garansi', 'servis', 'service center'];

return [

    /*
    |--------------------------------------------------------------------------
    | Blocked Aliases
    |--------------------------------------------------------------------------
    |
    | Normalized aliases that are everyday words, slang, or ambiguous between
    | entities. They are never matched against opinion text, never used as
    | source search queries, and cannot be added through the admin or seed
    | import. "ga" is Indonesian slang for "tidak"; matching it as Garuda
    | Indonesia attributed lotion and car opinions to the airline.
    |
    */
    'blocked_aliases' => ['ga', 'do', 'map', 'sap', 'tam', 'bl', 'ct', 'rk', 'garuda', 'gigi'],

    /*
    |--------------------------------------------------------------------------
    | Context-Required Aliases
    |--------------------------------------------------------------------------
    |
    | Brand names that are also everyday words. The term only matches when the
    | text also contains one of its context words, so "jago" (Indonesian for
    | "skilled") in a car review is not attributed to the bank.
    |
    */
    'context_required_aliases' => [
        'jago' => ['bank', 'rekening', 'kantong', 'saldo', 'tabungan', 'transfer', 'aplikasi', 'app', 'nasabah', 'debit', 'kartu', 'atm', 'bunga', 'deposito', 'ojk', 'lps', 'syariah', 'mbanking', 'qris'],
        'vidio' => ['langganan', 'premier', 'platinum', 'diamond', 'streaming', 'siaran', 'sinetron', 'series', 'liga', 'aplikasi', 'app', 'akun', 'paket'],
        'flip' => ['transfer', 'antarbank', 'bank', 'rekening', 'saldo', 'topup', 'top up', 'ewallet', 'e wallet', 'kirim uang', 'biaya admin', 'qris', 'aplikasi', 'app', 'globe'],
        'matahari' => ['department store', 'dept store', 'mall', 'gerai', 'store', 'outlet', 'belanja', 'kasir', 'diskon', 'toko'],
        'ajaib' => ['saham', 'reksa dana', 'reksadana', 'investasi', 'sekuritas', 'trading', 'kripto', 'crypto', 'portofolio', 'aplikasi', 'app'],
        'bibit' => ['saham', 'reksa dana', 'reksadana', 'investasi', 'sbn', 'obligasi', 'portofolio', 'aplikasi', 'app'],
        'bonceng' => ['ojek', 'ojol', 'driver', 'order', 'orderan', 'tarif', 'mitra', 'aplikasi', 'app'],
        'club' => ['air mineral', 'air minum', 'galon', 'amdk'],
        'aqua' => ['air mineral', 'air minum', 'galon', 'amdk', 'danone', 'minum'],
        'roma' => ['biskuit', 'biscuit', 'wafer', 'malkist', 'kelapa', 'camilan', 'cemilan', 'snack', 'mayora'],
        'fiesta' => ['nugget', 'sosis', 'frozen', 'karaage', 'ayam', 'chicken', 'goreng'],
        'lion' => ['pesawat', 'penerbangan', 'maskapai', 'terbang', 'bandara', 'tiket', 'bagasi', 'pilot', 'pramugari', 'flight', 'delay'],
        'zoom' => ['meeting', 'rapat', 'meet', 'video call', 'vicon', 'webinar', 'kelas online', 'link', 'aplikasi', 'app'],
        'sharp' => $applianceContext,
        'cosmos' => $applianceContext,
        'modena' => $applianceContext,
        'kirin' => $applianceContext,
        'gea' => $applianceContext,
        'bosch' => $applianceContext,
        'mito' => $applianceContext,
        'cakap' => ['kursus', 'kelas', 'belajar', 'les', 'bahasa', 'aplikasi', 'app'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Uppercase-Only Alias Length
    |--------------------------------------------------------------------------
    |
    | Letter-only aliases up to this many characters (BCA, KAI, XL) only match
    | when written in capitals in the original text, since the lowercase form
    | is usually an ordinary word.
    |
    */
    'uppercase_only_max_length' => 3,

    /*
    |--------------------------------------------------------------------------
    | Model Variant Suffixes
    |--------------------------------------------------------------------------
    |
    | A model-number alias ("s24", "iphone 15") does not match when the text
    | continues with one of these tokens ("S24 FE", "iPhone 15 Pro"): that is a
    | different model, and attributing it to the base model skews its score. An
    | entity whose own name or alias is the longer phrase still wins by length.
    |
    */
    'model_variant_suffixes' => ['fe', 'plus', 'ultra', 'pro', 'max', 'lite', 'mini', 'se', 'edge'],

    /*
    |--------------------------------------------------------------------------
    | Candidate Cache
    |--------------------------------------------------------------------------
    |
    | Seconds the normalized name/alias set of all active entities is cached for
    | EntityMatcher. 0 disables the cache (tests).
    |
    */
    'candidates_cache_seconds' => (int) env('ENTITY_MATCHER_CACHE_SECONDS', 60),

];

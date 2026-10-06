<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Suzuki Satria F150 pointed at the Philippine Raider R150 page and Suzuki Carry at the Japanese Carry page.
 * Both are sold in Indonesia, so link the Indonesian site (checked: "Suzuki Indonesia"). Only while the stored
 * URL still holds the foreign host, so an edit is kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['suzuki-satria-f150' => 'suzuki.com.ph', 'suzuki-carry' => 'suzuki.co.jp'] as $slug => $foreignHost) {
            DB::table('entities')
                ->where('slug', $slug)
                ->where('website_url', 'like', '%'.$foreignHost.'%')
                ->update(['website_url' => 'https://www.suzuki.co.id', 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // The previous values were foreign-market pages for an Indonesian product.
    }
};

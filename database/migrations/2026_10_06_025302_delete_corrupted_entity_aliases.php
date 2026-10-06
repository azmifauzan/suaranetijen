<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * On 7 Sep 2026 a one-off batch created 44 aliases whose normalized value had every capital letter replaced
 * by a dash ("Megawati Soekarnoputri" -> "-egawati-oekarnoputri") and attached person names to unrelated
 * companies: DANA, Zoom, Canva, OVO, Slack, PLN. They can never match text, but the entity page prints the
 * alias as "also known as". A real normalized alias is lowercase letters, digits and spaces, so anything
 * starting with a dash is one of these.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('entity_aliases')->where('normalized_alias', 'like', '-%')->delete();
    }

    public function down(): void
    {
        // Corrupted rows are not worth restoring.
    }
};

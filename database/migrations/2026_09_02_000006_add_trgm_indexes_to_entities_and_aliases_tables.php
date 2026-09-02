<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm;');
            DB::statement('CREATE INDEX IF NOT EXISTS entities_name_trgm_idx ON entities USING gin (name gin_trgm_ops);');
            DB::statement('CREATE INDEX IF NOT EXISTS entity_aliases_normalized_alias_trgm_idx ON entity_aliases USING gin (normalized_alias gin_trgm_ops);');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS entities_name_trgm_idx;');
            DB::statement('DROP INDEX IF EXISTS entity_aliases_normalized_alias_trgm_idx;');
        }
    }
};

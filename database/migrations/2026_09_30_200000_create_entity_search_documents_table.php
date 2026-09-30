<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('entity_search_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')
                ->unique()
                ->constrained('entities')
                ->cascadeOnDelete();

            $table->text('description_text')->nullable();
            $table->text('theme_text')->nullable();
            $table->text('spec_text')->nullable();
            $table->text('summary_text')->nullable();

            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX IF NOT EXISTS entity_search_docs_desc_trgm_idx ON entity_search_documents USING gin (description_text gin_trgm_ops);');
            DB::statement('CREATE INDEX IF NOT EXISTS entity_search_docs_theme_trgm_idx ON entity_search_documents USING gin (theme_text gin_trgm_ops);');
            DB::statement('CREATE INDEX IF NOT EXISTS entity_search_docs_spec_trgm_idx ON entity_search_documents USING gin (spec_text gin_trgm_ops);');
            DB::statement('CREATE INDEX IF NOT EXISTS entity_search_docs_summary_trgm_idx ON entity_search_documents USING gin (summary_text gin_trgm_ops);');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS entity_search_docs_desc_trgm_idx;');
            DB::statement('DROP INDEX IF EXISTS entity_search_docs_theme_trgm_idx;');
            DB::statement('DROP INDEX IF EXISTS entity_search_docs_spec_trgm_idx;');
            DB::statement('DROP INDEX IF EXISTS entity_search_docs_summary_trgm_idx;');
        }

        Schema::dropIfExists('entity_search_documents');
    }
};

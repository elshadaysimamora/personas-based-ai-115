<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Tambahkan kolom tsvector
        DB::statement('ALTER TABLE documents ADD COLUMN tsv_title TSVECTOR');

        // Buat index GIN untuk pencarian full-text yang cepat
        DB::statement('CREATE INDEX documents_tsv_title_idx ON documents USING GIN(tsv_title)');

        // Isi nilai tsvector untuk data yang sudah ada
        DB::statement("UPDATE documents SET tsv_title = to_tsvector('simple', coalesce(title, ''))");

        // Buat trigger untuk meng-update tsvector saat data diubah
        DB::statement('
            CREATE TRIGGER documents_tsv_update BEFORE INSERT OR UPDATE ON documents
            FOR EACH ROW EXECUTE FUNCTION
            tsvector_update_trigger(tsv_title, \'pg_catalog.simple\', title)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Hapus trigger
        DB::statement('DROP TRIGGER IF EXISTS documents_tsv_update ON documents');

        // Hapus index
        DB::statement('DROP INDEX IF EXISTS documents_tsv_title_idx');

        // Hapus kolom
        DB::statement('ALTER TABLE documents DROP COLUMN IF EXISTS tsv_title');
    }
};

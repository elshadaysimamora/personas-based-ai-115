<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Document extends Model
{
    use HasFactory;

    protected $guarded = [];

    // Relasi dengan chunks
    public function chunks()
    {
        return $this->hasMany(DocumentChunk::class, 'document_id');
    }

    /**
     * Scope untuk mencari dokumen dengan full-text search PostgreSQL
     * Menggunakan plainto_tsquery untuk query natural language
     */
    public function scopeFullTextSearch($query, $searchTerm)
    {
        return $query->whereRaw("tsv_title @@ plainto_tsquery('simple', ?)", [$searchTerm])
            ->orderByRaw("ts_rank(tsv_title, plainto_tsquery('simple', ?)) DESC", [$searchTerm]);
    }

    /**
     * Scope untuk pencarian dengan to_tsquery (untuk operator OR dan wildcards)
     */
    public function scopeTsQuery($query, $tsQueryString)
    {
        return $query->whereRaw("tsv_title @@ to_tsquery('simple', ?)", [$tsQueryString])
            ->orderByRaw("ts_rank(tsv_title, to_tsquery('simple', ?)) DESC", [$tsQueryString]);
    }
}

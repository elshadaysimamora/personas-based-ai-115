<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Pgvector\Laravel\Vector;
use Pgvector\Laravel\HasNeighbors;
use Pgvector\Laravel\Distance;

class DocumentChunk extends Model
{
    use HasFactory;
    use HasNeighbors;

    protected $casts = [
        'embedding' => Vector::class,
        'metadata' => 'json'
    ];
    protected $guarded = [];


    // Konversi embedding ke array
    public function getEmbeddingAttribute($value)
    {
        return json_decode($value, true);
    }

    // Relasi balik ke dokumen
    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function defaultDistanceType()
    {
        return Distance::Cosine;
    }
}

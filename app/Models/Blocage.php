<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Blocage extends Model
{
    /**
     * Pas de created_at/updated_at : seule la colonne `cree_le` (voir migration) trace
     * la création, conformément au schéma demandé.
     */
    public $timestamps = false;

    protected $fillable = [
        'medecin_id',
        'date',
        'motif',
        'cree_le',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'cree_le' => 'datetime',
        ];
    }

    public function medecin(): BelongsTo
    {
        return $this->belongsTo(Medecin::class);
    }
}

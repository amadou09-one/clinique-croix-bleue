<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Disponibilite extends Model
{
    protected $fillable = [
        'medecin_id',
        'jour_semaine',
        'heure_debut',
        'heure_fin',
        'duree_creneau_min',
    ];

    public function medecin(): BelongsTo
    {
        return $this->belongsTo(Medecin::class);
    }
}

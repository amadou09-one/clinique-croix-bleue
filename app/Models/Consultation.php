<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consultation extends Model
{
    const CREATED_AT = 'cree_le';

    const UPDATED_AT = 'modifie_le';

    protected $fillable = [
        'rendez_vous_id',
        'medecin_id',
        'patient_id',
        'diagnostic',
        'observations',
    ];

    public function rendezVous(): BelongsTo
    {
        return $this->belongsTo(RendezVous::class);
    }

    public function medecin(): BelongsTo
    {
        return $this->belongsTo(Medecin::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }
}

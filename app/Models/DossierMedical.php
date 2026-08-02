<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DossierMedical extends Model
{
    protected $table = 'dossiers_medicaux';

    protected $fillable = [
        'patient_id',
        'groupe_sanguin',
        'poids_kg',
        'taille_cm',
        'tension',
        'allergies',
        'maj_par',
        'maj_le',
    ];

    protected function casts(): array
    {
        return [
            'poids_kg' => 'decimal:2',
            'taille_cm' => 'integer',
            'maj_le' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function majPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'maj_par');
    }
}

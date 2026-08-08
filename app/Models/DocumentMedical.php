<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentMedical extends Model
{
    protected $table = 'documents_medicaux';

    protected $fillable = [
        'patient_id',
        'rendez_vous_id',
        'type',
        'titre',
        'fichier_url',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function rendezVous(): BelongsTo
    {
        return $this->belongsTo(RendezVous::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'rendez_vous_id',
        'type',
        'canal',
        'contenu',
        'lu_le',
        'envoye_le',
    ];

    protected function casts(): array
    {
        return [
            'lu_le' => 'datetime',
            'envoye_le' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rendezVous(): BelongsTo
    {
        return $this->belongsTo(RendezVous::class);
    }
}

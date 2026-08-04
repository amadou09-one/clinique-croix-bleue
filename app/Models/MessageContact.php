<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageContact extends Model
{
    protected $table = 'messages_contact';

    protected $fillable = [
        'nom',
        'email',
        'telephone',
        'sujet',
        'message',
        'traite',
    ];

    protected function casts(): array
    {
        return [
            'traite' => 'boolean',
        ];
    }
}

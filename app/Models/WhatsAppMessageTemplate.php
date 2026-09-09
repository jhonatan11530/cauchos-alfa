<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppMessageTemplate extends Model
{
    protected $table = 'whatsapp_message_templates';

    protected $fillable = [
        'name',
        'message',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}

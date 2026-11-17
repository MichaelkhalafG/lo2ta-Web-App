<?php

namespace App\Models;

use App\Enums\MailState;
use Illuminate\Database\Eloquent\Model;

class MailOutbox extends Model
{
    protected $table = 'mail_outbox';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'state' => MailState::class,
            'attempts' => 'integer',
            'available_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
        ];
    }
}

<?php

namespace App\Enums;

enum MailState: string
{
    use HasLabel;

    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Dead = 'dead';
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A message from the contact form (PLAN 1.2, 2.6). Private; read in the phase 7 inbox. */
class ContactMessage extends Model
{
    protected $guarded = ['id'];
}

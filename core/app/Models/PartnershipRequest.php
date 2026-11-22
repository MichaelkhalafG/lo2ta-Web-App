<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A developer's partnership request from /developers (PLAN 1.2, 2.6). Private; read in the phase 7 inbox. */
class PartnershipRequest extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['projects_count' => 'integer'];
    }
}

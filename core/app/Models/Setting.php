<?php

namespace App\Models;

use App\Support\Activity\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/**
 * One stored value per key, as JSON text (a scalar: 100, true, "seller"). Types, limits and defaults are in
 * config/settings.php; read and write through App\Support\Settings\Settings, which encodes and decodes the value.
 */
class Setting extends Model
{
    use LogsActivity;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_provisional' => 'boolean',
        ];
    }
}

<?php

namespace App\Models;

use App\Enums\CommissionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The commission record of a deal (PLAN 2.4; G7, G8): base, rate and amount frozen when the deal opens; status and
 * reference only. Changed through named log lines (commission.*), admin only.
 */
class Commission extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CommissionStatus::class,
            'base_amount' => 'integer',
            'rate_bp' => 'integer',
            'amount' => 'integer',
            'vat_bp' => 'integer',
            'vat_amount' => 'integer',
            'invoiced_on' => 'immutable_date',
            'collected_on' => 'immutable_date',
        ];
    }

    /**
     * @return BelongsTo<Deal, $this>
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }
}

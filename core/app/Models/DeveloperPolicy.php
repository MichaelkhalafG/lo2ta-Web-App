<?php

namespace App\Models;

use App\Support\Activity\LogsActivity;
use App\Support\Cache\BumpsCacheOnWrite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A developer's own published terms (PLAN 3.6, J1, J3): each non-null column overrides the market value for units
 * of that developer. Used by the calculator and the exit report only once the client approves it.
 *
 * @property int $developer_id
 * @property string|null $penalty_basis
 * @property int|null $penalty_min_bp
 * @property int|null $penalty_max_bp
 * @property string|null $refund_rule
 * @property int|null $refund_years_fixed
 * @property int|null $late_fee_bp
 * @property string|null $transfer_fee_mode
 * @property int|null $transfer_fee_bp
 * @property int|null $transfer_fee_fixed
 * @property string $source_title
 * @property string $source_url
 * @property Carbon|null $source_published_on
 * @property Carbon $checked_on
 * @property Carbon|null $client_approved_at
 * @property int|null $client_approved_by
 * @property string|null $note
 */
class DeveloperPolicy extends Model
{
    use BumpsCacheOnWrite, LogsActivity;

    /** The settings a policy may override. */
    public const OVERRIDES = [
        'penalty_basis', 'penalty_min_bp', 'penalty_max_bp', 'refund_rule', 'refund_years_fixed', 'late_fee_bp',
        'transfer_fee_mode', 'transfer_fee_bp', 'transfer_fee_fixed',
    ];

    protected $guarded = ['id'];

    /**
     * @return list<string>
     */
    public static function cacheNamespaces(): array
    {
        return ['tools'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_published_on' => 'date',
            'checked_on' => 'date',
            'client_approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Developer, $this>
     */
    public function developer(): BelongsTo
    {
        return $this->belongsTo(Developer::class);
    }

    /**
     * Approved by the client, for an active developer: the only policies the calculator and the exit report use.
     *
     * @param  Builder<DeveloperPolicy>  $query
     */
    public function scopeApproved(Builder $query): void
    {
        $query->whereNotNull('client_approved_at')->whereHas('developer', static fn (Builder $q) => $q->where('is_active', true));
    }

    /**
     * The overrides this policy sets (column => value), non-null only.
     *
     * @return array<string, int|string>
     */
    public function overrides(): array
    {
        $out = [];
        foreach (self::OVERRIDES as $key) {
            $value = $this->getAttribute($key);
            if (is_int($value) || is_string($value)) {
                $out[$key] = $value;
            }
        }
        // A fee given as a share or an amount carries its own method, whatever the market default is.
        if (! isset($out['transfer_fee_mode'])) {
            if (isset($out['transfer_fee_bp'])) {
                $out['transfer_fee_mode'] = 'pct';
            } elseif (isset($out['transfer_fee_fixed'])) {
                $out['transfer_fee_mode'] = 'fixed';
            }
        }

        return $out;
    }
}

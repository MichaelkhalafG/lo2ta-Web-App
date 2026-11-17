<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DeliveryPreference;
use App\Enums\InstallmentFrequency;
use App\Enums\MainType;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * The fixed choice lists the public forms show, as code + label in the request's language. Labels come from
 * lang/{locale}/enums.php, so the English site never shows an Arabic value (PLAN 5.1 item 1).
 */
class ChoicesController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => [
            'main_types' => self::list(MainType::cases()),
            'installment_frequencies' => self::list(InstallmentFrequency::cases()),
            'delivery_preferences' => self::list(DeliveryPreference::cases()),
        ]]);
    }

    /**
     * @param  array<MainType|InstallmentFrequency|DeliveryPreference>  $cases
     * @return list<array{code: string, label: string}>
     */
    private static function list(array $cases): array
    {
        $out = [];
        foreach ($cases as $case) {
            $out[] = ['code' => $case->value, 'label' => $case->label()];
        }

        return $out;
    }
}

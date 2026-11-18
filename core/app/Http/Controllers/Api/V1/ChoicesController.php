<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DeliveryPreference;
use App\Enums\DeliveryStatus;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\Finishing;
use App\Enums\InstallmentFrequency;
use App\Enums\MainType;
use App\Enums\OverdueSettlement;
use App\Enums\SubType;
use App\Enums\UnitState;
use App\Http\Controllers\Controller;
use BackedEnum;
use Illuminate\Http\JsonResponse;

/**
 * The codes and labels the public site's forms offer, in the request's language (PLAN 5.1 item 1: never an Arabic
 * label on the English site). Sub-types carry the field matrix of PLAN 2.2.3, so the seller form hides exactly the
 * fields core clears; core still decides (StepRules::normalize).
 */
class ChoicesController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $subTypes = [];
        foreach (SubType::cases() as $sub) {
            $subTypes[] = [
                'code' => $sub->value,
                'label' => $sub->label(),
                'main_type' => $sub->mainType()->value,
                'has_floor' => $sub->hasFloor(),
                'has_rooms' => $sub->hasRooms(),
                'fixed_bedrooms' => $sub->fixedBedrooms(),
                'has_garden' => $sub->hasGarden(),
                'has_roof' => $sub->hasRoof(),
                'has_finishing' => $sub->hasFinishing(),
            ];
        }

        return response()->json(['data' => [
            'main_types' => self::list(MainType::cases()),
            'installment_frequencies' => self::list(InstallmentFrequency::cases()),
            'delivery_preferences' => self::list(DeliveryPreference::cases()),
            'sub_types' => $subTypes,
            'finishings' => self::list(Finishing::cases()),
            'delivery_statuses' => self::list(DeliveryStatus::cases()),
            'overdue_settlements' => self::list(OverdueSettlement::cases()),
            'document_types' => self::list(DocumentType::cases()),
            'document_statuses' => self::list(DocumentStatus::cases()),
            'unit_states' => self::list(UnitState::cases()),
        ]]);
    }

    /**
     * @param  array<BackedEnum&object>  $cases
     * @return list<array{code: string, label: string}>
     */
    private static function list(array $cases): array
    {
        $out = [];
        foreach ($cases as $case) {
            $label = method_exists($case, 'label') ? $case->label() : (string) $case->value;
            $out[] = ['code' => (string) $case->value, 'label' => is_string($label) ? $label : (string) $case->value];
        }

        return $out;
    }
}

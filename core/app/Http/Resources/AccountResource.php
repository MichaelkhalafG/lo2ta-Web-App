<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in user's own account, answered only to that user (auth:sanctum). Never part of a public answer.
 *
 * @mixin User
 */
class AccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'locale' => $user->preferredLocale(),
            'email_verified' => $user->hasVerifiedEmail(),
            'has_budget' => $user->budget()->exists(),
            'favorites_count' => $user->favorites()->count(),
        ];
    }
}

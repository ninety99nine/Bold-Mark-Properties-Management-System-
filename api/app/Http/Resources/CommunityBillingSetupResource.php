<?php

namespace App\Http\Resources;

use App\Models\CommunityBillingSetup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunityBillingSetupResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        $payload = [
            'id'           => $this->id,
            'community_id' => $this->community_id,
        ];

        foreach (CommunityBillingSetup::LEDGER_FIELDS as $field) {
            $payload[$field] = $this->{$field};
        }

        foreach (CommunityBillingSetup::BOOLEAN_FIELDS as $field) {
            $payload[$field] = (bool) $this->{$field};
        }

        $payload['created_at'] = $this->created_at?->toDateTimeString();
        $payload['updated_at'] = $this->updated_at?->toDateTimeString();

        return $payload;
    }
}

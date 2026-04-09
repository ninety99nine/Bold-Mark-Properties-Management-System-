<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceEmailEventResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'event_type'      => $this->event_type,
            'email'           => $this->email,
            'resend_email_id' => $this->resend_email_id,
            'occurred_at'     => $this->occurred_at?->toDateTimeString(),
        ];
    }
}

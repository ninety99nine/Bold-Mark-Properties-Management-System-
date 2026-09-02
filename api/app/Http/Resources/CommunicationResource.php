<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunicationResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'subject'          => $this->subject,
            'from_name'        => $this->from_name,
            'from_email'       => $this->from_email,
            'type'             => $this->type?->value,
            'status'           => $this->status,
            'sent_by_name'     => $this->sent_by_name,
            'recipient_groups' => $this->recipient_groups ?? [],
            'recipient_count'  => (int) $this->recipient_count,
            'sent_count'       => (int) $this->sent_count,
            'error_count'      => (int) $this->error_count,
            'is_bulk'          => (int) $this->recipient_count > 1,
            'sent_date'        => $this->created_at?->toDateTimeString(),

            'community'        => $this->whenLoaded('community', fn () => [
                'id'   => $this->community?->id,
                'name' => $this->community?->name,
                'code' => $this->community?->code,
            ]),

            // For a single-recipient send the Archive shows the recipient name +
            // a per-recipient status and enables Resend/Download.
            'recipients'       => CommunicationRecipientResource::collection($this->whenLoaded('recipients')),
        ];
    }
}

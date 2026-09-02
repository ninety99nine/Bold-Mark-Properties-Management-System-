<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitCommunicationResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'unit_id'          => $this->unit_id,
            'subject'          => $this->subject,
            'body'             => $this->body,
            'recipient_name'   => $this->recipient_name,
            'recipient_email'  => $this->recipient_email,
            'bcc'              => $this->bcc,
            'sent_by_name'     => $this->sent_by_name,
            'attachment_names' => $this->attachment_names ?? [],
            'created_at'       => $this->created_at?->toDateTimeString(),
        ];
    }
}

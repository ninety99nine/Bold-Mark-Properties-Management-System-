<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunicationRecipientResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'recipient_name'  => $this->recipient_name,
            'recipient_email' => $this->recipient_email,
            'status'          => $this->status,
            'error'           => $this->error,
            'view_token'      => $this->view_token,
        ];
    }
}

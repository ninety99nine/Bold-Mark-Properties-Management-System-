<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class CommunicationResources extends ResourceCollection
{
    public $collects = CommunicationResource::class;
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class MessageTemplateResources extends ResourceCollection
{
    public $collects = MessageTemplateResource::class;
}

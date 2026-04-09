<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class EstateResources extends ResourceCollection
{
    public $collects = EstateResource::class;
}

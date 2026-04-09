<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class OwnerResources extends ResourceCollection
{
    public $collects = OwnerResource::class;
}

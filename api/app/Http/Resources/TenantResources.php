<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class TenantResources extends ResourceCollection
{
    public $collects = TenantResource::class;
}

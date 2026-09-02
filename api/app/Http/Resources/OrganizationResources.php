<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class OrganizationResources extends ResourceCollection
{
    public $collects = OrganizationResource::class;
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class CustomerGroupResources extends ResourceCollection
{
    public $collects = CustomerGroupResource::class;
}

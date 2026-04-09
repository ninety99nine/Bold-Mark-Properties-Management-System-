<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class OrderResources extends ResourceCollection
{
    public $collects = OrderResource::class;
}

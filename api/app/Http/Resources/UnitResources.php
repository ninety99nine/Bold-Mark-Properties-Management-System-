<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class UnitResources extends ResourceCollection
{
    public $collects = UnitResource::class;
}

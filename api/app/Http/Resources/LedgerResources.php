<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class LedgerResources extends ResourceCollection
{
    public $collects = LedgerResource::class;
}

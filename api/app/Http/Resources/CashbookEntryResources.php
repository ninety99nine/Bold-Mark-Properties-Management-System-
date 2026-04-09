<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class CashbookEntryResources extends ResourceCollection
{
    public $collects = CashbookEntryResource::class;
}

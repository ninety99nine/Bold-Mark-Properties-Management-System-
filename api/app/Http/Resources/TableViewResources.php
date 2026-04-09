<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class TableViewResources extends ResourceCollection
{
    public $collects = TableViewResource::class;
}

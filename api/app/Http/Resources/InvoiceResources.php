<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class InvoiceResources extends ResourceCollection
{
    public $collects = InvoiceResource::class;
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class BankAccountResources extends ResourceCollection
{
    public $collects = BankAccountResource::class;
}

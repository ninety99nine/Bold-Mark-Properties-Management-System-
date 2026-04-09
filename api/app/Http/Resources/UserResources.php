<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class UserResources extends ResourceCollection
{
    public $collects = UserResource::class;
}

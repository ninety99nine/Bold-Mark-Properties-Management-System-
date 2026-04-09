<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class PostResources extends ResourceCollection
{
    public $collects = PostResource::class;
}

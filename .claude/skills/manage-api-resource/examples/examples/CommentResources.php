<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class CommentResources extends ResourceCollection
{
    public $collects = CommentResource::class;
}

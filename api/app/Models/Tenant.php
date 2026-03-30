<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'logo_url',
        'primary_color',
        'accent_color',
        'credentials',
        'copyright_name',
        'is_active',
    ];

    protected $casts = [
        'credentials' => 'array',
        'is_active' => 'boolean',
    ];

    public function getCopyrightNameAttribute(?string $value): string
    {
        return $value ?? $this->name;
    }
}

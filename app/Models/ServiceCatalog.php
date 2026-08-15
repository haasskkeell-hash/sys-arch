<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceCatalog extends Model
{
    protected $table = 'service_catalog';

    protected $fillable = [
        'code',
        'name',
        'category',
        'base_price',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}

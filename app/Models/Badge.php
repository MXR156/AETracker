<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Badge extends Model
{
    protected $fillable = ['name', 'category', 'boost_percent', 'cost', 'owned', 'acquired_on'];

    protected function casts(): array
    {
        return [
            'owned' => 'boolean',
            'boost_percent' => 'float',
            'cost' => 'float',
            'acquired_on' => 'date',
        ];
    }
}

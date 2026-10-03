<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImageOptimization extends Model
{
    protected $fillable = [
        'path',
        'original_bytes',
        'optimized_bytes',
        'optimized_mtime',
        'optimized_at',
    ];

    protected function casts(): array
    {
        return [
            'optimized_at' => 'datetime',
        ];
    }
}

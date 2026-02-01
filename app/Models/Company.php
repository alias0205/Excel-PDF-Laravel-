<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'template_path',
        'template_mapping',
        'template_type',
    ];

    protected $casts = [
        'template_mapping' => 'array',
        'template_type' => 'string',
    ];

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }
}

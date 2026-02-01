<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Staff extends Model
{
    use HasFactory;

    protected $table = 'staff';

    protected $fillable = [
        'company_id',
        'employee_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'department',
        'title',
        'hire_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'hire_date' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

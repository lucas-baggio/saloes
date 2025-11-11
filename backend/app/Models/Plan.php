<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'interval',
        'features',
        'max_establishments',
        'max_services',
        'max_employees',
        'is_popular',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'features' => 'array',
        'is_popular' => 'boolean',
        'is_active' => 'boolean',
        'max_establishments' => 'integer',
        'max_services' => 'integer',
        'max_employees' => 'integer',
    ];

    public function userPlans(): HasMany
    {
        return $this->hasMany(UserPlan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}


<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

class Establishment extends Model
{
    use HasFactory, Notifiable;
    protected $fillable = [
        'name',
        'description',
        'owner_id'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'employee_establishment', 'establishment_id', 'user_id')
            ->where('role', 'employee')
            ->withTimestamps();
    }
}

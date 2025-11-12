<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'service_id',
        'scheduling_id',
        'establishment_id',
        'user_id',
        'amount',
        'payment_method',
        'sale_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'sale_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Mutator para garantir que a data seja salva como string YYYY-MM-DD
     */
    public function setSaleDateAttribute($value)
    {
        if ($value instanceof \DateTime || $value instanceof \Carbon\Carbon) {
            $this->attributes['sale_date'] = $value->format('Y-m-d');
        } elseif (is_string($value)) {
            // Se já for string YYYY-MM-DD, usar diretamente
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $this->attributes['sale_date'] = $value;
            } else {
                // Tentar parsear e converter
                $this->attributes['sale_date'] = \Carbon\Carbon::parse($value)->format('Y-m-d');
            }
        } else {
            $this->attributes['sale_date'] = $value;
        }
    }

    /**
     * Accessor para retornar a data como string YYYY-MM-DD
     */
    public function getSaleDateAttribute($value)
    {
        if ($value instanceof \DateTime || $value instanceof \Carbon\Carbon) {
            return $value->format('Y-m-d');
        }
        return $value;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function scheduling(): BelongsTo
    {
        return $this->belongsTo(Scheduling::class);
    }

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }
}


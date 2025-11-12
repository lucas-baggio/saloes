<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'user_id',
        'percentage',
        'amount',
        'payment_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Mutator para garantir que a data seja salva como string YYYY-MM-DD
     */
    public function setPaymentDateAttribute($value)
    {
        if ($value === null || $value === '') {
            $this->attributes['payment_date'] = null;
            return;
        }

        if ($value instanceof \DateTime || $value instanceof \Carbon\Carbon) {
            $this->attributes['payment_date'] = $value->format('Y-m-d');
        } elseif (is_string($value)) {
            // Se já for string YYYY-MM-DD, usar diretamente
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $this->attributes['payment_date'] = $value;
            } else {
                // Tentar parsear e converter
                $this->attributes['payment_date'] = \Carbon\Carbon::parse($value)->format('Y-m-d');
            }
        } else {
            $this->attributes['payment_date'] = $value;
        }
    }

    /**
     * Accessor para retornar a data como string YYYY-MM-DD
     */
    public function getPaymentDateAttribute($value)
    {
        if (!$value) {
            return null;
        }
        if ($value instanceof \DateTime || $value instanceof \Carbon\Carbon) {
            return $value->format('Y-m-d');
        }
        return $value;
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}


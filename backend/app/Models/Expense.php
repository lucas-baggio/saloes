<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'establishment_id',
        'description',
        'category',
        'amount',
        'due_date',
        'payment_date',
        'payment_method',
        'status',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'payment_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Mutator para garantir que a data seja salva como string YYYY-MM-DD
     */
    public function setDueDateAttribute($value)
    {
        if ($value instanceof \DateTime || $value instanceof \Carbon\Carbon) {
            $this->attributes['due_date'] = $value->format('Y-m-d');
        } elseif (is_string($value)) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $this->attributes['due_date'] = $value;
            } else {
                $this->attributes['due_date'] = \Carbon\Carbon::parse($value)->format('Y-m-d');
            }
        } else {
            $this->attributes['due_date'] = $value;
        }
    }

    /**
     * Accessor para retornar a data como string YYYY-MM-DD
     */
    public function getDueDateAttribute($value)
    {
        if ($value instanceof \DateTime || $value instanceof \Carbon\Carbon) {
            return $value->format('Y-m-d');
        }
        return $value;
    }

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
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $this->attributes['payment_date'] = $value;
            } else {
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

    /**
     * Verificar se a despesa está vencida
     */
    public function isOverdue(): bool
    {
        if ($this->status === 'paid') {
            return false;
        }

        $dueDate = $this->due_date;
        if (!$dueDate) {
            return false;
        }

        if (is_string($dueDate)) {
            $dueDate = Carbon::parse($dueDate);
        }

        return $dueDate->isPast();
    }

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }
}


<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'user_plan_id',
        'payment_method',
        'status',
        'amount',
        'mercadopago_payment_id',
        'mercadopago_preference_id',
        'qr_code',
        'qr_code_base64',
        'barcode',
        'barcode_base64',
        'due_date',
        'payment_url',
        'transaction_id',
        'metadata',
        'error_message',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function userPlan(): BelongsTo
    {
        return $this->belongsTo(UserPlan::class);
    }
}


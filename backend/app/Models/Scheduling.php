<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Scheduling extends Model
{
    use HasFactory;

    protected $table = 'schedulings';

    protected $fillable = [
        'scheduled_date',
        'scheduled_time',
        'service_id',
        'establishment_id',
        'client_name',
        'status',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'scheduled_time' => 'string',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }
}

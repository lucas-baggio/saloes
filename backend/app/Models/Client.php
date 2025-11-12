<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'name',
        'phone',
        'email',
        'cpf',
        'birth_date',
        'address',
        'anamnesis',
        'notes',
        'photo',
        'allergies',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'allergies' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['photo_url'];

    /**
     * Retorna a URL completa da foto
     */
    public function getPhotoUrlAttribute(): ?string
    {
        $photo = $this->attributes['photo'] ?? null;

        if (!$photo) {
            return null;
        }

        // Se já for uma URL completa (http/https), retornar como está
        if (str_starts_with($photo, 'http://') ||
            str_starts_with($photo, 'https://') ||
            str_starts_with($photo, 'data:image')) {
            return $photo;
        }

        // Retornar URL relativa do storage (funciona tanto em localhost quanto em produção)
        // O frontend vai construir a URL completa baseada no ambiente
        return '/storage/' . $photo;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function schedulings(): HasMany
    {
        return $this->hasMany(Scheduling::class);
    }
}

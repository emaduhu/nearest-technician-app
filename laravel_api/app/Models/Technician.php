<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'name',
    'phone',
    'email',
    'password',
    'device_token',
    'skills',
    'image',
    'latitude',
    'longitude',
    'available',
    'rating',
    'last_seen_at',
])]
class Technician extends Model
{
    protected function casts(): array
    {
        return [
            'skills' => 'array',
            'latitude' => 'float',
            'longitude' => 'float',
            'available' => 'boolean',
            'rating' => 'float',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }
}

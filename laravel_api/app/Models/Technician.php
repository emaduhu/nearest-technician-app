<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'name',
    'nida',
    'phone',
    'email',
    'password',
    'device_token',
    'skills',
    'image',
    'nida_id_image',
    'face_image',
    'registration_review_status',
    'registration_review_note',
    'registration_reviewed_at',
    'registration_reviewed_by',
    'latitude',
    'longitude',
    'available',
    'client_requests_blocked',
    'client_requests_blocked_reason',
    'rating',
    'registration_fee_amount',
    'registration_fee_currency',
    'registration_payment_status',
    'registration_payment_order_reference',
    'registration_payment_id',
    'registration_payment_response',
    'registration_payment_requested_at',
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
            'client_requests_blocked' => 'boolean',
            'rating' => 'float',
            'registration_fee_amount' => 'integer',
            'registration_payment_response' => 'array',
            'registration_payment_requested_at' => 'datetime',
            'registration_reviewed_at' => 'datetime',
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'client_id',
    'technician_id',
    'skill',
    'description',
    'status',
    'client_latitude',
    'client_longitude',
    'technician_latitude_at_request',
    'technician_longitude_at_request',
    'distance_km',
    'response_message',
    'client_rating',
    'client_report',
    'responded_at',
    'completed_at',
])]
class ServiceRequest extends Model
{
    protected function casts(): array
    {
        return [
            'client_latitude' => 'float',
            'client_longitude' => 'float',
            'technician_latitude_at_request' => 'float',
            'technician_longitude_at_request' => 'float',
            'distance_km' => 'float',
            'client_rating' => 'integer',
            'responded_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }
}

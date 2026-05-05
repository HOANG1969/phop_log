<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingRoom extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'location',
        'capacity',
        'has_camera',
        'is_active',
    ];

    protected $casts = [
        'has_camera' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function bookings(): HasMany
    {
        return $this->hasMany(MeetingBooking::class);
    }
}

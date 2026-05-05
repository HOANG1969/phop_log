<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'meeting_room_id',
        'requested_by',
        'approved_by',
        'title',
        'start_at',
        'end_at',
        'organizer_name',
        'internal_attendees',
        'external_attendees',
        'meeting_link',
        'notes',
        'snacks_requested',
        'is_online',
        'status',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'snacks_requested' => 'boolean',
        'is_online' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(MeetingRoom::class, 'meeting_room_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

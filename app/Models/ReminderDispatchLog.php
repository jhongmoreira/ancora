<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderDispatchLog extends Model
{
    /** @use HasFactory<\Database\Factories\ReminderDispatchLogFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'reminder_id',
        'sent_date',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_date' => 'date',
            'sent_at' => 'datetime',
        ];
    }

    public function reminder(): BelongsTo
    {
        return $this->belongsTo(Reminder::class);
    }
}

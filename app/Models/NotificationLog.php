<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $table = 'notification_logs';
    protected $primaryKey = 'notification_id';
    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'notification_type',
        'related_model',
        'related_id',
        'email_sent_at',
        'email_read_at',
        'subject',
        'body',
        'status',
        'error_message',
    ];

    protected $casts = [
        'email_sent_at' => 'datetime',
        'email_read_at' => 'datetime',
    ];

    /**
     * Get the user this notification belongs to
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}

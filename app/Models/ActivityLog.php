<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';
    protected $primaryKey = 'activity_id';
    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'activity_type',
        'description',
        'model_type',
        'model_id',
        'ip_address',
        'user_agent',
    ];

    /**
     * Get the user who performed this activity
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}

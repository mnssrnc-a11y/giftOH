<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_logs';
    protected $primaryKey = 'log_id';
    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'changes',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the user who performed the action
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}

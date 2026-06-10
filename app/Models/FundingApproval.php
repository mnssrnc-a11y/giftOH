<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundingApproval extends Model
{
    protected $table = 'funding_approvals';
    protected $primaryKey = 'approval_id';
    public $timestamps = true;

    protected $fillable = [
        'request_id',
        'approved_by',
        'approval_status',
        'approval_notes',
        'ai_score',
        'ai_scoring_details',
        'decision_at',
    ];

    protected $casts = [
        'ai_score' => 'decimal:2',
        'ai_scoring_details' => 'array',
        'decision_at' => 'datetime',
    ];

    /**
     * Get the funding request for this approval
     */
    public function fundingRequest()
    {
        return $this->belongsTo(Funding::class, 'request_id', 'id');
    }

    /**
     * Get the user who made this approval decision
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by', 'user_id');
    }
}

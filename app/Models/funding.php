<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Funding extends Model
{
    protected $table = 'funding_request_tb';
    protected $primaryKey = 'id';
    public $timestamps = true;
    protected $fillable = [
        'user_id',
        'category_id',
        'status_id',
        'org_name',
        'contact_person',
        'contact_email',
        'contact_phone',
        'address',
        'tax_id',
        'mission',
        'category',
        'doc_image',
        'id_image',
        'financial_rprt',
        'barangay_clr',
        'amount_requested',
        'amount_paid',
        'approved_by',
        'ai_score',
        'ai_score_breakdown',
        'admin_notes',
        'approved_at',
        'rejected_at',
        'completed_at',
    ];

    protected $casts = [
        'amount_requested' => 'decimal:10',
        'amount_paid' => 'decimal:10',
        'ai_score' => 'decimal:10',
        'ai_score_breakdown' => 'array',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the user who submitted this funding request
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Get the category of this funding request
     */
    public function category()
    {
        return $this->belongsTo(FundingCategory::class, 'category_id', 'category_id');
    }

    /**
     * Get the status of this funding request
     */
    public function status()
    {
        return $this->belongsTo(RequestStatus::class, 'status_id', 'status_id');
    }

    /**
     * Get the user who approved this request
     */
    public function approvedByUser()
    {
        return $this->belongsTo(User::class, 'approved_by', 'user_id');
    }

    /**
     * Get all documents for this funding request
     */
    public function documents()
    {
        return $this->hasMany(FundingDocument::class, 'request_id', 'id');
    }

    /**
     * Get the approval record for this request
     */
    public function approval()
    {
        return $this->hasOne(FundingApproval::class, 'request_id', 'id');
    }

    /**
     * Get all approval records (history)
     */
    public function approvals()
    {
        return $this->hasMany(FundingApproval::class, 'request_id', 'id');
    }

    /**
     * Get the appeal for this request (if any)
     */
    public function appeal()
    {
        return $this->hasOne(FundingAppeal::class, 'original_request_id', 'id');
    }

    /**
     * Scope to get pending requests
     */
    public function scopePending($query)
    {
        return $query->where('status_id', 1);
    }

    /**
     * Scope to get approved requests
     */
    public function scopeApproved($query)
    {
        return $query->where('status_id', 2);
    }

    /**
     * Scope to get rejected requests
     */
    public function scopeRejected($query)
    {
        return $query->where('status_id', 3);
    }

    /**
     * Scope to get completed requests
     */
    public function scopeCompleted($query)
    {
        return $query->where('status_id', 4);
    }
}

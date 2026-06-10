<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundingDocument extends Model
{
    protected $table = 'funding_documents';
    protected $primaryKey = 'document_id';
    public $timestamps = true;

    protected $fillable = [
        'request_id',
        'document_type',
        'file_path',
        'file_size',
        'file_mime_type',
        'uploaded_at',
        'verified_at',
        'verified_by',
        'verification_status',
        'rejection_reason',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    /**
     * Get the funding request this document belongs to
     */
    public function fundingRequest()
    {
        return $this->belongsTo(Funding::class, 'request_id', 'id');
    }

    /**
     * Get the user who verified this document
     */
    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by', 'user_id');
    }
}

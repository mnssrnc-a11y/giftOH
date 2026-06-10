<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundingAppeal extends Model
{
    protected $table = 'funding_appeals';
    protected $primaryKey = 'appeal_id';
    public $timestamps = true;

    protected $fillable = [
        'original_request_id',
        'resubmitted_request_id',
        'reason_for_reapply',
        'previous_rejection_reason',
    ];

    /**
     * Get the original funding request
     */
    public function originalRequest()
    {
        return $this->belongsTo(Funding::class, 'original_request_id', 'id');
    }

    /**
     * Get the resubmitted funding request
     */
    public function resubmittedRequest()
    {
        return $this->belongsTo(Funding::class, 'resubmitted_request_id', 'id');
    }
}

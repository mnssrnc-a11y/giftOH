<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestStatus extends Model
{
    protected $table = 'request_status_tb';
    protected $primaryKey = 'status_id';
    public $timestamps = true;

    protected $fillable = [
        'status_name',
        'description',
    ];

    /**
     * Get all funding requests with this status
     */
    public function fundingRequests()
    {
        return $this->hasMany(Funding::class, 'status_id', 'status_id');
    }
}

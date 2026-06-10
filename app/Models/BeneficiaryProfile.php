<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BeneficiaryProfile extends Model
{
    protected $table = 'beneficiary_profiles';
    protected $primaryKey = 'beneficiary_id';
    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'organization_name',
        'organization_type',
        'verification_status',
        'verified_at',
        'verified_by',
        'annual_report_url',
        'tax_id',
        'representative_name',
        'representative_phone',
        'impact_description',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    /**
     * Get the user this profile belongs to
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Get the user who verified this profile
     */
    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by', 'user_id');
    }
}

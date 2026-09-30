<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantBackupKey extends Model
{
    protected $fillable = ['tenant_id', 'encrypted_dek'];
    protected $casts = ['tenant_id' => 'integer'];

    public function tenant()
    {
        return $this->belongsTo(Institute::class, 'tenant_id');
    }
}

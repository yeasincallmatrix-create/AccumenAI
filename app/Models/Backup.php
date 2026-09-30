<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Backup extends Model
{
    protected $fillable = [
        'tenant_id', 'owner_user_id', 'filename', 'size_bytes',
        'file_hmac', 'status', 'error_message', 'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'size_bytes'   => 'integer',
    ];

    public function tenant()
    {
        return $this->belongsTo(Institute::class, 'tenant_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function scopeCompleted($q)
    {
        return $q->where('status', 'completed');
    }
}

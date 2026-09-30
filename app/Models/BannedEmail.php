<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BannedEmail extends Model
{
    protected $table = 'banned_emails';

    protected $fillable = [
        'email', 'reason', 'banned_by',
        'banned_at', 'is_permanent', 'expires_at',
    ];

    protected $casts = [
        'banned_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_permanent' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('is_permanent', true)
                ->orWhere(function ($q2) {
                    $q2->where('is_permanent', false)
                        ->where(function ($q3) {
                            $q3->whereNull('expires_at')
                                ->orWhere('expires_at', '>', now());
                        });
                });
        });
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}

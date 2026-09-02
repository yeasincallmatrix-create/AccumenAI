<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Crypt;

class AiApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'capability',
        'name',
        'api_key',
        'base_url',
        'model',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function setApiKeyAttribute($value)
    {
        if ($value !== null && $value !== '') {
            $this->attributes['api_key'] = Crypt::encryptString($value);
        } elseif ($value === null) {
            $this->attributes['api_key'] = null;
        }
    }

    public function getApiKeyAttribute($value)
    {
        if ($value) {
            try {
                return Crypt::decryptString($value);
            } catch (\Throwable $e) {
                return $value;
            }
        }
        return null;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForProvider($query, $provider)
    {
        return $query->where('provider', $provider);
    }
}

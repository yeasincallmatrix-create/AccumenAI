<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiUsage extends Model
{
    protected $table = 'ai_usage';

    public $timestamps = true;

    protected $guarded = [];

    public const PERIOD_TYPE_DAILY = 'daily';

    public const PERIOD_TYPE_MONTHLY = 'monthly';
}

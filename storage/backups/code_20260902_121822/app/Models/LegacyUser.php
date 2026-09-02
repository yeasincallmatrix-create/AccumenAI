<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegacyUser extends Model
{
    protected $table = 'users';

    public $timestamps = true;

    protected $guarded = [];
}

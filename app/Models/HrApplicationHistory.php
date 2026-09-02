<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrApplicationHistory extends Model
{
    use TenantScoped;

    protected $table = 'hr_application_histories';
    protected $guarded = [];
    public $timestamps = true;

    public function application(): BelongsTo { return $this->belongsTo(HrApplication::class,'application_id'); }
    public function changer(): BelongsTo { return $this->belongsTo(InstituteUser::class,'changed_by'); }
}

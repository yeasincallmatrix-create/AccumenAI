<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Installment extends Model
{
    use Concerns\TenantScoped;

    protected $table = 'installments';

    public $timestamps = false;

    protected $fillable = [
        'institute_id',
        'invoice_id',
        'student_id',
        'installment_no',
        'amount',
        'due_date',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'paid_amount' => 'float',
            'due_date' => 'date',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}

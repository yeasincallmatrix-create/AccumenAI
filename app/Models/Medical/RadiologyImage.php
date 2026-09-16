<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class RadiologyImage extends Model
{
    protected $table = 'radiology_images';

    protected $fillable = [
        'radiology_order_id', 'file_path', 'thumbnail_path',
        'original_filename', 'mime_type', 'file_size', 'caption', 'uploaded_by',
    ];

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RadiologyOrder::class, 'radiology_order_id');
    }

    public function uploadedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function url(): string
    {
        return \Storage::url($this->file_path);
    }
}
